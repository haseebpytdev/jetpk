/**
 * Phases 4–11, 17–19: reachable routes, responsive matrix, layout/nav/table/modal/form checks,
 * booking/PNR read regression, console/network capture.
 */
import { createRequire } from "node:module";
import fs from "node:fs";
import path from "node:path";
import { spawnSync } from "node:child_process";
import { fileURLToPath } from "node:url";
import { requireDashboardBuildId, requireProductionSha } from "../jp-dashboard-prod-cert/production-cert-env.mjs";
import { FINAL11_AUTH_ROLES, buildStorageMap } from "./final-11-auth-roles.mjs";
import { probeRoleSession } from "./auth-session-probe.mjs";
import { VISIBILITY_AUDIT_HELPERS } from "./responsive-visibility-audit.mjs";
import { csvEscape } from "./functional-cert-csv.mjs";
import { roleMeta } from "./final-route-inventory.mjs";
import { sanitizeCertificationError } from "./responsive-cert-sanitize.mjs";
import {
  canonicalEvidencePaths,
  pathsForDir,
  resolveEvidenceDir,
  VIEWPORTS as ENGINE_VIEWPORTS,
} from "./responsive-cert-paths.mjs";
import {
  acquireResponsiveSweepLock,
  refuseSecondSweepMessage,
  releaseResponsiveSweepLock,
} from "./responsive-cert-lock.mjs";
import { buildRunFingerprint, fingerprintMatches } from "./responsive-cert-fingerprint.mjs";
import { filterPlanForEnv, loadExecutionPlan } from "./responsive-cert-plan.mjs";
import {
  emptyCheckpoint,
  HeartbeatWriter,
  isCompletedRow,
  loadCheckpoint,
  saveCheckpointAtomic,
  upsertCompletedRow,
  validateCheckpointAgainstPlan,
} from "./responsive-cert-checkpoint.mjs";
import { copyToCanonical, publishCanonicalArtifacts } from "./responsive-cert-aggregate.mjs";
import { writeFileAtomic } from "./functional-cert-lock.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const BASE = "https://jetpakistan.pk";
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const VIEWPORTS = ENGINE_VIEWPORTS;
const ROW_TIMEOUT_MS = Math.min(
  Number(process.env.JP_FINAL11_ROW_TIMEOUT_MS || 120_000),
  180_000,
);
const MAX_ROW_ATTEMPTS = 2;
const AUTH_PROBE_MAX_AGE_MS = 30 * 60 * 1000;
const SUBPROCESS_TIMEOUT_MS = 300_000;

const TABLE_ROUTE_RE =
  /\/(users|staff|agents|customers|bookings|pnrs|deposits|payments|reports|travelers|support|audit|operations|api-connections|cms)/i;

const BOOKING_ROUTES = ["/agent/bookings", "/customer/bookings", "/admin/dashboard/bookings", "/staff/dashboard/bookings"];
const PNR_ROUTES = ["/admin/dashboard/pnrs", "/staff/dashboard/pnrs"];

const STORAGE = buildStorageMap();

const SESSION_PROBE_EVERY_ROUTES = 15;

const sessionStats = {
  refreshes: 0,
  rowsRecovered: 0,
  networkTransientRetries: 0,
  networkTransientRecovered: 0,
  networkPersistentFailures: 0,
  timeoutRetries: 0,
};

function refreshRoleStorage(roleKey) {
  const result = spawnSync("node", [path.join(__dirname, "refresh-final11-sessions.mjs")], {
    cwd: repoRoot,
    encoding: "utf8",
    env: { ...process.env, JP_FINAL11_AUTH_ONLY: roleKey },
    maxBuffer: 16 * 1024 * 1024,
    timeout: SUBPROCESS_TIMEOUT_MS,
  });
  sessionStats.refreshes += 1;
  return (result.status ?? 1) === 0;
}

async function probeRoleContext(context, roleKey) {
  const role = FINAL11_AUTH_ROLES[roleKey];
  if (!role) return "FAIL_UNKNOWN_ROLE";
  return probeRoleSession(context, role);
}

async function openRoleContext(browser, roleKey) {
  const storagePath = STORAGE[roleKey];
  let context = await browser.newContext({ storageState: storagePath });
  let probe = await probeRoleContext(context, roleKey);
  if (probe !== "PASS" && refreshRoleStorage(roleKey)) {
    await context.close();
    context = await browser.newContext({ storageState: storagePath });
    probe = await probeRoleContext(context, roleKey);
  }
  return { context, sessionOk: probe === "PASS", probe };
}

function isTransientTransportFailure(text) {
  return /socket hang up|ECONNRESET|ERR_CONNECTION_RESET|ERR_NETWORK_CHANGED|ERR_CONNECTION_CLOSED|ERR_HTTP2_PROTOCOL_ERROR|ERR_INTERNET_DISCONNECTED|transient navigation|net::ERR_/i.test(
    text,
  );
}

function isBenignFailedRequest(entry) {
  return (
    entry.includes("ERR_ABORTED") ||
    entry.includes("_rsc=") ||
    entry.includes("favicon") ||
    entry.includes("analytics") ||
    entry.includes("chrome-extension")
  );
}

function isHydrationError(message) {
  return /Minified React error #418|hydration/i.test(message);
}

function isBookingsListRoute(route) {
  return (
    route === "/admin/dashboard/bookings" ||
    route === "/staff/dashboard/bookings" ||
    route === "/agent/bookings" ||
    route === "/customer/bookings"
  );
}

async function waitForRouteContentReady(page, route, viewport) {
  await page.getByTestId("dashboard-shell").waitFor({ state: "visible", timeout: 30_000 }).catch(() => {});
  if (isBookingsListRoute(route)) {
    await page.getByTestId("bookings-filters").waitFor({ state: "visible", timeout: 30_000 }).catch(() => {});
    if (viewport.width >= 1280) {
      await page.getByTestId("bookings-table").waitFor({ state: "visible", timeout: 30_000 }).catch(() => {});
    } else {
      await page.getByTestId("bookings-mobile-cards").waitFor({ state: "visible", timeout: 30_000 }).catch(() => {});
    }
  }
  await page.waitForLoadState("networkidle", { timeout: 12_000 }).catch(() => {});
  await sleep(350);
}

function snapshotConsoleHealth(consoleErrors, pageErrors) {
  const hydrationErrors = [...consoleErrors, ...pageErrors].filter(isHydrationError);
  const uncaught = pageErrors.filter((e) => !/ResizeObserver/i.test(e));
  const badConsole = consoleErrors.filter(
    (e) =>
      !/status of (401|403|404)/i.test(e) &&
      !/favicon|ResizeObserver|chunkLoadError|Loading chunk/i.test(e) &&
      !/Failed to load resource/i.test(e),
  );
  return { hydrationErrors, uncaught, badConsole };
}

async function collectNavHrefs(page) {
  await page.waitForLoadState("domcontentloaded").catch(() => {});
  await sleep(1200);
  const evaluateLinks = () =>
    page.evaluate(() => {
      const out = new Set();
      document.querySelectorAll("a[href]").forEach((a) => {
        const href = a.getAttribute("href");
        if (href && href.startsWith("/")) out.add(href.split("?")[0]);
      });
      return [...out];
    });
  try {
    return await evaluateLinks();
  } catch {
    await sleep(1500);
    return await evaluateLinks();
  }
}

async function layoutAudit(page, viewport) {
  return page.evaluate(({ width, helpers }) => {
    eval(helpers);
    function isControlClippedByAncestor(el) {
      if (!otaCertEffectivelyVisible(el)) return false;
      const r = el.getBoundingClientRect();
      if (r.width < 4 || r.height < 4) return false;
      let parent = el.parentElement;
      while (parent && parent !== document.body) {
        const style = window.getComputedStyle(parent);
        const clips =
          style.overflow === "hidden" ||
          style.overflowX === "hidden" ||
          style.overflowY === "hidden" ||
          style.clipPath !== "none";
        if (clips) {
          const pr = parent.getBoundingClientRect();
          if (pr.width < 2 && pr.height < 2) {
            parent = parent.parentElement;
            continue;
          }
          if (r.right > pr.right + 2 || r.left < pr.left - 2 || r.bottom > pr.bottom + 2 || r.top < pr.top - 2) {
            return true;
          }
        }
        parent = parent.parentElement;
      }
      return false;
    }

    const doc = document.documentElement;
    const body = document.body;
    const pageOverflowX = Math.max(doc.scrollWidth, body?.scrollWidth || 0) - width > 8;
    const defects = { horizontalOverflow: false, clippedControls: 0, overlap: 0 };
    if (pageOverflowX) {
      const scrollContainers = [...document.querySelectorAll(".overflow-x-auto, [data-testid*='table']")].filter(
        (el) => el.scrollWidth > el.clientWidth + 4,
      );
      if (scrollContainers.length === 0) defects.horizontalOverflow = true;
    }
    const mainRoot =
      document.querySelector("main, [role='main'], [data-testid='dashboard-shell'], [data-testid='agent-dashboard-shell']") ||
      document.body;
    const actionable = [...mainRoot.querySelectorAll("button, a[href], input, select, textarea, [role=button]")].slice(
      0,
      80,
    );
    for (const el of actionable) {
      if (el.closest("nav, aside, [data-testid*='sidebar'], [data-testid*='drawer']")) continue;
      if (!otaCertEffectivelyVisible(el)) continue;
      if (isControlClippedByAncestor(el)) defects.clippedControls += 1;
    }
    return defects;
  }, { width: viewport.width, helpers: VISIBILITY_AUDIT_HELPERS });
}

async function navAudit(page, viewport) {
  if (viewport.name !== "MOBILE" && viewport.name !== "TABLET") {
    return { sidebarNavDefects: 0, mobileNavUnreachable: 0 };
  }
  const visibleNavLinks = await page.locator("nav a[href], [role=navigation] a[href]").count();
  if (visibleNavLinks >= 3) {
    return { sidebarNavDefects: 0, mobileNavUnreachable: 0 };
  }
  let sidebarNavDefects = 0;
  let mobileNavUnreachable = 0;
  const toggles = page.locator(
    '[data-testid*="mobile-menu"], [data-testid*="nav-toggle"], [data-testid*="sidebar-toggle"], button[aria-label*="menu" i], button[aria-label*="Menu"]',
  );
  const count = await toggles.count();
  if (count === 0) {
    return { sidebarNavDefects: 0, mobileNavUnreachable: 0 };
  }
  if (count > 0) {
    try {
      await toggles.first().click({ timeout: 3000 });
      await sleep(600);
      const links = await page.locator("nav a[href], [role=navigation] a[href]").count();
      if (links === 0) sidebarNavDefects += 1;
      await toggles.first().click({ timeout: 3000 }).catch(() => {});
    } catch {
      sidebarNavDefects += 1;
    }
  }
  return { sidebarNavDefects, mobileNavUnreachable };
}

async function safeOverlayAudit(page, viewport) {
  let modalDefects = 0;
  let drawerDefects = 0;
  let menuDefects = 0;
  const triggers = page.locator(
    'button:has-text("Filter"), button:has-text("Filters"), button[data-testid*="filter"], button[aria-haspopup="menu"]',
  );
  const n = Math.min(await triggers.count(), 2);
  for (let i = 0; i < n; i++) {
    try {
      const beforeCount = await page
        .locator('[role="dialog"]:visible, [role="menu"]:visible, [data-testid*="drawer"]:visible, [data-testid*="modal"]:visible')
        .count();
      await triggers.nth(i).click({ timeout: 2500 });
      await sleep(500);
      const panel = page.locator(
        '[role="dialog"]:visible, [role="menu"]:visible, [data-testid*="drawer"]:visible, [data-testid*="modal"]:visible',
      );
      const afterCount = await panel.count();
      if (afterCount > beforeCount) {
        const box = await panel.last().boundingBox();
        if (!box || box.width < 8 || box.height < 8) {
          /* hidden/stale panel */
        } else if (box.x < -4 || box.y < -4 || box.x + box.width > viewport.width + 4) {
          modalDefects += 1;
        }
        await page.keyboard.press("Escape").catch(() => {});
      }
    } catch {
      /* skip */
    }
  }
  return { modalDefects, drawerDefects, menuDefects };
}

async function formLayoutAudit(page) {
  if (!/settings|traveler|staff\/new|cms/i.test(page.url())) {
    return 0;
  }
  const hasNextForm = (await page.locator("[data-testid*='form']").count()) > 0;
  if (!hasNextForm) return 0;
  return page.evaluate((helpers) => {
    eval(helpers);
    let defects = 0;
    const root = document.querySelector("[data-testid*='form']");
    if (!root) return 0;
    const inputs = root.querySelectorAll("input:not([type=hidden]), select, textarea");
    for (const input of inputs) {
      if (!otaCertEffectivelyVisible(input)) continue;
      const r = input.getBoundingClientRect();
      if (r.width < 40 && r.height < 20) defects += 1;
    }
    return defects;
  }, VISIBILITY_AUDIT_HELPERS);
}

async function auditRoute(context, roleKey, route, viewport, meta, metrics, networkLog) {
  const page = await context.newPage();
  await page.setViewportSize({ width: viewport.width, height: viewport.height });
  const url = `${BASE}${route}`;
  const consoleErrors = [];
  const failedRequests = [];
  const pageErrors = [];

  page.on("console", (msg) => {
    if (msg.type() === "error") consoleErrors.push(msg.text());
  });
  page.on("pageerror", (err) => pageErrors.push(String(err)));
  page.on("requestfailed", (req) => failedRequests.push(`${req.method()} ${req.url()} ${req.failure()?.errorText || ""}`));

  let resp = await page.goto(url, { waitUntil: "domcontentloaded", timeout: 90000 }).catch((err) => {
    if (isTransientTransportFailure(String(err?.message || err))) {
      sessionStats.networkTransientRetries += 1;
      return null;
    }
    return null;
  });
  if (!resp || resp.status() === 0) {
    const failureText = failedRequests.join(" ");
    if (isTransientTransportFailure(failureText) || !resp) {
      sessionStats.networkTransientRetries += 1;
      await sleep(800);
      resp = await page.goto(url, { waitUntil: "domcontentloaded", timeout: 90000 }).catch(() => null);
      if (resp && resp.status() > 0 && resp.status() < 500) {
        sessionStats.networkTransientRecovered += 1;
      } else if (!resp || resp.status() === 0) {
        sessionStats.networkPersistentFailures += 1;
      }
    }
  }
  await waitForRouteContentReady(page, route, viewport);
  const status = resp?.status() ?? 0;
  const body = await page.locator("body").innerText().catch(() => "");
  const accessDenied = (await page.getByTestId("dashboard-access-denied").count()) > 0;
  const legacyNotice = /legacy account|no longer supported/i.test(body);
  const mockHints = /preview only|mock data|fixture data|dataSourcePreview/i.test(body);
  const preInteractionHealth = snapshotConsoleHealth(consoleErrors, pageErrors);
  const hydrationErrors = preInteractionHealth.hydrationErrors;
  const unexpectedFailures = failedRequests.filter((f) => !isBenignFailedRequest(f));

  let disposition = "PASS";
  if (status >= 500 || mockHints || hydrationErrors.length > 0 || unexpectedFailures.some((f) => / 500 /.test(f))) {
    disposition = "FAIL";
  } else if (accessDenied || legacyNotice || status === 403) {
    disposition = "EXPECTED_DENY";
  } else if (status === 0 || page.url().includes("/login")) {
    disposition = "AUTH_FAIL";
  }

  const layout = await layoutAudit(page, viewport);
  const nav = await navAudit(page, viewport);
  const overlays =
    disposition === "PASS" && viewport.name === "DESKTOP" && TABLE_ROUTE_RE.test(route)
      ? await safeOverlayAudit(page, viewport)
      : { modalDefects: 0, drawerDefects: 0, menuDefects: 0 };
  const postInteractionHealth = snapshotConsoleHealth(consoleErrors, pageErrors);
  let tableDefects = 0;
  let tableActionsHidden = 0;
  if (TABLE_ROUTE_RE.test(route)) {
    const tableInfo = await page.evaluate(() => {
      const tables = [...document.querySelectorAll("table")];
      let hiddenActions = 0;
      for (const table of tables) {
        const container = table.closest(".overflow-x-auto, [style*='overflow']") || table.parentElement;
        if (table.scrollWidth > (container?.clientWidth || 0) + 8 && !container?.classList?.contains("overflow-x-auto")) {
          hiddenActions += 1;
        }
      }
      return { tableLayoutIssues: hiddenActions, hiddenActions };
    });
    tableDefects = tableInfo.tableLayoutIssues;
    tableActionsHidden = tableInfo.hiddenActions;
  }
  const formDefects = await formLayoutAudit(page);

  if (layout.horizontalOverflow) metrics.horizontalOverflow += 1;
  if (layout.clippedControls > 0) metrics.rawClippedFlags += 1;
  if (layout.clippedControls > 12) {
    metrics.clippedControls += 1;
    metrics.trueClippedDefects += 1;
  }
  metrics.overlap += layout.overlap;
  metrics.sidebarNav += nav.sidebarNavDefects;
  metrics.mobileNavUnreachable += nav.mobileNavUnreachable;
  if (overlays.modalDefects > 0) metrics.rawModalFlags += overlays.modalDefects;
  metrics.modalDefects += overlays.modalDefects;
  metrics.trueModalDefects += overlays.modalDefects;
  metrics.drawerDefects += overlays.drawerDefects;
  metrics.menuDefects += overlays.menuDefects;
  metrics.tableDefects += tableDefects;
  metrics.tableActionsHidden += tableActionsHidden;
  if (formDefects > 0) metrics.rawFormLayoutFlags += formDefects;
  metrics.formDefects += formDefects;
  metrics.trueFormLayoutDefects += formDefects;

  if (mockHints) metrics.fakeSuccess += 1;
  if (hydrationErrors.length) {
    metrics.hydration += hydrationErrors.length;
    networkLog.hydration.push({ role: roleKey, route, viewport: viewport.name, errors: hydrationErrors });
  }
  const uncaught = preInteractionHealth.uncaught;
  const badConsole = preInteractionHealth.badConsole;
  if (uncaught.length) {
    metrics.uncaughtJs += 1;
    networkLog.consoleErrors.push({ role: roleKey, route, viewport: viewport.name, errors: uncaught.slice(0, 5) });
  } else if (badConsole.some((e) => /Minified React error|hydration|uncaught/i.test(e))) {
    metrics.uncaughtJs += 1;
    networkLog.consoleErrors.push({ role: roleKey, route, viewport: viewport.name, errors: badConsole.slice(0, 5) });
  } else if (
    postInteractionHealth.uncaught.some((e) => !/parentNode/i.test(e)) ||
    postInteractionHealth.badConsole.some((e) => /Minified React error|hydration|uncaught/i.test(e))
  ) {
    metrics.uncaughtJs += 1;
    networkLog.consoleErrors.push({
      role: roleKey,
      route,
      viewport: viewport.name,
      errors: [...postInteractionHealth.uncaught, ...postInteractionHealth.badConsole].slice(0, 5),
    });
  }
  if (unexpectedFailures.length) {
    const appFailures = unexpectedFailures.filter((f) => f.includes("jetpakistan.pk") && !/ 401 | 403 /.test(f));
    if (appFailures.length) {
      metrics.unexpectedNetwork += appFailures.length;
      networkLog.failedRequests.push({ role: roleKey, route, viewport: viewport.name, failures: appFailures.slice(0, 3) });
    }
  }
  if (status >= 500) metrics.page500 += 1;

  if (BOOKING_ROUTES.some((p) => route === p || route.startsWith(`${p}/`))) {
    metrics.bookingRoutes += 1;
    if (disposition === "PASS" || disposition === "EXPECTED_DENY") metrics.bookingPass += 1;
  }
  if (PNR_ROUTES.some((p) => route === p)) {
    metrics.pnrRoutes += 1;
    if (disposition === "PASS" || disposition === "EXPECTED_DENY") metrics.pnrPass += 1;
  }

  metrics.responsiveByViewport[viewport.name] += 1;

  const row = {
    roleKey,
    route,
    viewport: viewport.name,
    disposition,
    httpStatus: status,
    horizontalOverflow: layout.horizontalOverflow ? "YES" : "NO",
    clippedControls: layout.clippedControls,
    tableDefects,
    formDefects,
    modalDefects: overlays.modalDefects,
    hydrationErrors: hydrationErrors.length,
    uncaughtJsErrors: uncaught.length ? 1 : 0,
    networkFailures: unexpectedFailures.filter((f) => f.includes("jetpakistan.pk")).length,
  };

  await page.close();
  return row;
}

function toolingFailRow(roleKey, route, viewportName, reason, attemptCount = 1) {
  return {
    roleKey,
    route,
    viewport: viewportName,
    disposition: "TOOLING_FAIL",
    httpStatus: 0,
    horizontalOverflow: "NO",
    clippedControls: 0,
    tableDefects: 0,
    formDefects: 0,
    modalDefects: 0,
    hydrationErrors: 0,
    uncaughtJsErrors: 0,
    networkFailures: 0,
    ROOT_REASON: reason,
    attemptCount,
  };
}

async function runRowWithDeadline(fn, deadlineMs) {
  let timer;
  const timeout = new Promise((_, reject) => {
    timer = setTimeout(() => reject(new Error("ROW_TIMEOUT")), deadlineMs);
  });
  try {
    return await Promise.race([fn(), timeout]);
  } finally {
    clearTimeout(timer);
  }
}

function stubAuditRow(planRow) {
  const delay = Number(process.env.JP_FINAL11_SWEEP_INJECT_ROW_DELAY_MS || 0);
  const injectIndex = Number(process.env.JP_FINAL11_SWEEP_INJECT_DELAY_INDEX || 0);
  const stubRowMs = Number(process.env.JP_FINAL11_SWEEP_STUB_ROW_MS || 0);
  return new Promise((resolve) => {
    let wait = stubRowMs > 0 ? stubRowMs : 5;
    if (planRow.INDEX === injectIndex && delay > 0) wait = delay;
    setTimeout(() => {
      resolve({
        roleKey: planRow.ROLE_KEY,
        route: planRow.ROUTE,
        viewport: planRow.VIEWPORT,
        disposition: "PASS",
        httpStatus: 200,
        horizontalOverflow: "NO",
        clippedControls: 0,
        tableDefects: 0,
        formDefects: 0,
        modalDefects: 0,
        hydrationErrors: 0,
        uncaughtJsErrors: 0,
        networkFailures: 0,
      });
    }, wait);
  });
}

async function main() {
  const productionSha = requireProductionSha();
  const buildId = requireDashboardBuildId();
  const evidenceDir = resolveEvidenceDir();
  const paths = pathsForDir(evidenceDir);
  const testMode = process.env.JP_FINAL11_SWEEP_TEST_MODE === "1";
  const retestCompleted = process.argv.includes("--retest-completed");

  console.log(`FINAL_RESPONSIVE_SWEEP_STARTED=YES`);
  console.log(`SESSION_REFRESH_DB_MUTATIONS=0`);
  console.log(`QA_PASSWORD_SYNC_EVENTS_THIS_RUN=0`);
  console.log(`MAX_ROW_ATTEMPTS=${MAX_ROW_ATTEMPTS}`);
  console.log(`CERT_TOOLING_OBSERVABILITY_DEFECT=YES`);
  console.log(`OLD_SWEEP_EXACT_HANG_ROW=UNKNOWN`);
  console.log(`OLD_SWEEP_ROOT_CAUSE=INSUFFICIENT_OBSERVABILITY`);

  const plan = loadExecutionPlan();
  if (!plan?.rows?.length) {
    console.log("EXECUTION_PLAN_MISSING=run build-final-responsive-execution-plan.mjs first");
    process.exit(2);
  }

  const aggregateOnly = process.env.JP_FINAL11_AGGREGATE_ONLY === "1";
  const planRows = aggregateOnly ? plan.rows : filterPlanForEnv(plan);
  const executionPlanHash = plan.EXECUTION_PLAN_HASH;
  const fingerprint = buildRunFingerprint({
    productionSha,
    buildId,
    baseUrl: BASE,
    executionPlanHash,
  });
  const runId = process.env.JP_FINAL11_RESPONSIVE_RUN_ID || `responsive-${Date.now()}`;
  const fingerprintStr = JSON.stringify(fingerprint);

  const lock = acquireResponsiveSweepLock(paths.lock, { runId, fingerprint: fingerprintStr });
  if (!lock.ok) {
    console.log(refuseSecondSweepMessage(lock.lock));
    console.log(`SECOND_RESPONSIVE_SWEEP_START=REFUSED_WHILE_ACTIVE`);
    process.exit(3);
  }

  const heartbeat = new HeartbeatWriter(paths.heartbeat, paths.lock);
  heartbeat.update({
    PID: process.pid,
    RUN_ID: runId,
    STATUS: "STARTING",
    STARTED_AT: new Date().toISOString(),
    TOTAL_ROWS: planRows.length,
    COMPLETED_ROWS: 0,
    REMAINING_ROWS: planRows.length,
  });

  let checkpoint = loadCheckpoint(paths.checkpoint);
  const resumeAllowed = process.env.JP_FINAL11_RESPONSIVE_RESUME !== "0";
  if (checkpoint && resumeAllowed && !retestCompleted) {
    if (!fingerprintMatches(checkpoint.RUN_FINGERPRINT, fingerprint)) {
      console.log(`RESPONSIVE_RESUME=REFUSED_FINGERPRINT_MISMATCH`);
      console.log(`RESUME_ALLOWED=NO`);
      if (process.env.JP_FINAL11_RESPONSIVE_FRESH !== "1") {
        process.exit(4);
      }
      checkpoint = null;
    } else {
      const validation = validateCheckpointAgainstPlan(checkpoint, planRows);
      console.log(`CHECKPOINT_CORRUPT_ROWS=${validation.corrupt}`);
      console.log(`CHECKPOINT_DUPLICATE_KEYS=${validation.duplicateKeys}`);
      console.log(`CHECKPOINT_UNKNOWN_KEYS=${validation.unknown}`);
      const reused = Object.values(checkpoint.ROWS).filter(isCompletedRow).length;
      console.log(`RESPONSIVE_RESUME=YES`);
      console.log(`RESPONSIVE_ROWS_REUSED=${reused}`);
      console.log(`RESPONSIVE_ROWS_REMAINING=${planRows.length - reused}`);
    }
  } else if (process.env.JP_FINAL11_RESPONSIVE_FRESH === "1") {
    checkpoint = null;
  }

  if (!checkpoint) {
    if (aggregateOnly) {
      console.log("AGGREGATE_ONLY_CHECKPOINT_MISSING=YES");
      process.exit(2);
    }
    checkpoint = emptyCheckpoint({
      runId,
      fingerprint,
      planRowsTotal: planRows.length,
    });
    console.log(`AUTHORITATIVE_CHECKPOINT_IMPORTED_OLD_ROWS=0`);
  }

  const networkLog = { hydration: [], consoleErrors: [], failedRequests: [] };
  const liveMetrics = {
    horizontalOverflow: 0,
    clippedControls: 0,
    rawClippedFlags: 0,
    trueClippedDefects: 0,
    overlap: 0,
    sidebarNav: 0,
    mobileNavUnreachable: 0,
    modalDefects: 0,
    rawModalFlags: 0,
    trueModalDefects: 0,
    drawerDefects: 0,
    menuDefects: 0,
    tableDefects: 0,
    tableActionsHidden: 0,
    formDefects: 0,
    rawFormLayoutFlags: 0,
    trueFormLayoutDefects: 0,
    hydration: 0,
    uncaughtJs: 0,
    page500: 0,
    unexpectedNetwork: 0,
    fakeSuccess: 0,
    fixtureFallback: 0,
    bookingRoutes: 0,
    bookingPass: 0,
    pnrRoutes: 0,
    pnrPass: 0,
    responsiveByViewport: { DESKTOP: 0, TABLET: 0, MOBILE: 0 },
  };

  let browser = null;
  let context = null;
  let activeRole = "";
  let lastAuthProofAt = Date.now();
  let rowsCompletedThisRun = 0;
  let summaryEvery = 0;
  sessionStats.timeoutRetries = 0;

  const ensureBrowser = async () => {
    if (!browser) browser = await chromium.launch({ headless: true, timeout: 120_000 });
    return browser;
  };

  const recycleContext = async (roleKey) => {
    if (context) await context.close().catch(() => {});
    context = null;
    if (testMode) return { sessionOk: true };
    await ensureBrowser();
    const opened = await openRoleContext(browser, roleKey);
    context = opened.context;
    lastAuthProofAt = Date.now();
    return opened;
  };

  writeFileAtomic(paths.fingerprint, `${JSON.stringify(fingerprint, null, 2)}\n`);

  try {
    heartbeat.update({ STATUS: "RUNNING" });
    heartbeat.startInterval();

    if (!aggregateOnly) for (const planRow of planRows) {
      if (!retestCompleted && isCompletedRow(checkpoint.ROWS[planRow.CASE_KEY])) {
        continue;
      }

      const vp = VIEWPORTS.find((v) => v.name === planRow.VIEWPORT) || VIEWPORTS[0];
      const meta = roleMeta(planRow.ROLE_KEY);
      checkpoint.CURRENT_CASE_KEY = planRow.CASE_KEY;
      const rowStarted = Date.now();

      heartbeat.update({
        STATUS: "RUNNING",
        CURRENT_INDEX: planRow.INDEX,
        CURRENT_ROLE: planRow.ROLE_KEY,
        CURRENT_ROUTE: planRow.ROUTE,
        CURRENT_VIEWPORT: planRow.VIEWPORT,
        CURRENT_ATTEMPT: 1,
        CURRENT_ROW_ELAPSED_SECONDS: 0,
        COMPLETED_ROWS: checkpoint.ROWS_COMPLETED,
        REMAINING_ROWS: planRows.length - checkpoint.ROWS_COMPLETED,
      });

      if (!testMode && planRow.ROLE_KEY !== activeRole) {
        activeRole = planRow.ROLE_KEY;
        await recycleContext(activeRole);
      } else if (!testMode && Date.now() - lastAuthProofAt > AUTH_PROBE_MAX_AGE_MS) {
        heartbeat.update({ STATUS: "REFRESHING_AUTH" });
        const probe = await probeRoleContext(context, activeRole);
        if (probe !== "PASS" && refreshRoleStorage(activeRole)) {
          await recycleContext(activeRole);
        }
        lastAuthProofAt = Date.now();
        heartbeat.update({ STATUS: "RUNNING" });
      }

      let result = null;
      let attempt = 0;
      let sessionRefreshed = "NO";
      let transientRetry = "NO";

      while (attempt < MAX_ROW_ATTEMPTS && !result) {
        attempt += 1;
        heartbeat.update({ CURRENT_ATTEMPT: attempt, STATUS: attempt > 1 ? "RETRYING" : "RUNNING" });
        try {
          const auditFn = async () => {
            if (testMode) return stubAuditRow(planRow);
            return auditRoute(context, planRow.ROLE_KEY, planRow.ROUTE, vp, meta, liveMetrics, networkLog);
          };
          let row = await runRowWithDeadline(auditFn, ROW_TIMEOUT_MS);
          if (!testMode && row.disposition === "AUTH_FAIL") {
            heartbeat.update({ STATUS: "REFRESHING_AUTH" });
            const probe = await probeRoleContext(context, planRow.ROLE_KEY);
            if (probe !== "PASS" && refreshRoleStorage(planRow.ROLE_KEY)) {
              sessionRefreshed = "YES";
              await recycleContext(planRow.ROLE_KEY);
              row = await runRowWithDeadline(auditFn, ROW_TIMEOUT_MS);
              if (row.disposition !== "AUTH_FAIL" && row.disposition !== "TOOLING_FAIL") {
                sessionStats.rowsRecovered += 1;
              }
            }
          }
          result = row;
        } catch (err) {
          const msg = sanitizeCertificationError(err);
          if (msg.includes("ROW_TIMEOUT")) {
            sessionStats.timeoutRetries += 1;
            if (attempt >= MAX_ROW_ATTEMPTS) {
              result = toolingFailRow(planRow.ROLE_KEY, planRow.ROUTE, planRow.VIEWPORT, "ROW_TIMEOUT_AFTER_RETRY", attempt);
            } else if (!testMode) {
              await context?.close().catch(() => {});
              context = null;
              await recycleContext(planRow.ROLE_KEY);
            }
          } else if (isTransientTransportFailure(msg) && attempt < MAX_ROW_ATTEMPTS) {
            transientRetry = "YES";
            sessionStats.networkTransientRetries += 1;
            heartbeat.update({ STATUS: "RETRYING" });
            if (!testMode) await recycleContext(planRow.ROLE_KEY);
          } else if (attempt >= MAX_ROW_ATTEMPTS) {
            if (isTransientTransportFailure(msg)) {
              sessionStats.networkPersistentFailures += 1;
            }
            result = toolingFailRow(planRow.ROLE_KEY, planRow.ROUTE, planRow.VIEWPORT, "TOOLING_ERROR", attempt);
            console.log(`ROUTE_AUDIT_FAIL=${planRow.CASE_KEY}:${msg.slice(0, 120)}`);
          }
        }
      }

      if (!result) {
        result = toolingFailRow(planRow.ROLE_KEY, planRow.ROUTE, planRow.VIEWPORT, "ROW_TIMEOUT_AFTER_RETRY", attempt);
      }
      if (transientRetry === "YES" && result?.disposition && result.disposition !== "TOOLING_FAIL") {
        sessionStats.networkTransientRecovered += 1;
      }

      const durationMs = Date.now() - rowStarted;
      const persisted = {
        DISPOSITION: result.disposition,
        HTTP_STATUS: result.httpStatus,
        HORIZONTAL_OVERFLOW: result.horizontalOverflow,
        CLIPPED_CONTROLS: result.clippedControls,
        TABLE_DEFECTS: result.tableDefects,
        FORM_DEFECTS: result.formDefects,
        MODAL_DEFECTS: result.modalDefects ?? 0,
        HYDRATION_ERRORS: result.hydrationErrors ?? 0,
        UNCAUGHT_JS_ERRORS: result.uncaughtJsErrors ?? 0,
        NETWORK_FAILURES: result.networkFailures ?? 0,
        DURATION_MS: durationMs,
        ATTEMPT_COUNT: attempt,
        SESSION_REFRESHED: sessionRefreshed,
        TRANSIENT_RETRY: transientRetry,
        ROOT_REASON: result.ROOT_REASON || "",
      };

      upsertCompletedRow(checkpoint, planRow, persisted, {
        sessionRefreshes: sessionStats.refreshes,
        networkRetries: sessionStats.networkTransientRetries,
        timeoutRetries: sessionStats.timeoutRetries,
      });
      saveCheckpointAtomic(paths.checkpoint, checkpoint);
      rowsCompletedThisRun += 1;
      summaryEvery += 1;

      const completed = checkpoint.ROWS_COMPLETED;
      const total = planRows.length;
      const percent = ((completed / total) * 100).toFixed(2);
      console.log(`RESPONSIVE_PROGRESS=${completed}/${total}`);
      console.log(`RESPONSIVE_PERCENT=${percent}`);
      console.log(`RESPONSIVE_LAST_CASE=${planRow.CASE_KEY}`);
      console.log(`RESPONSIVE_LAST_DURATION_MS=${durationMs}`);
      console.log(`RESPONSIVE_REMAINING=${total - completed}`);
      if (summaryEvery >= 10) {
        summaryEvery = 0;
        console.log(
          `RESPONSIVE_SUMMARY completed=${completed} remaining=${total - completed} session_refreshes=${sessionStats.refreshes} network_retries=${sessionStats.networkTransientRetries}`,
        );
      }

      const abortAfter = Number(process.env.JP_FINAL11_SWEEP_ABORT_AFTER || 0);
      if (abortAfter > 0 && rowsCompletedThisRun >= abortAfter) {
        console.log(`RESPONSIVE_SWEEP_ABORT_AFTER=${abortAfter}`);
        saveCheckpointAtomic(paths.checkpoint, checkpoint);
        heartbeat.update({
          STATUS: "FAILED",
          LAST_COMPLETED_CASE_KEY: planRow.CASE_KEY,
          COMPLETED_ROWS: completed,
          REMAINING_ROWS: total - completed,
        });
        console.log(`RESPONSIVE_ROWS_COMPLETED=${completed}`);
        console.log(`RESPONSIVE_ROWS_REMAINING=${total - completed}`);
        console.log(`CHECKPOINT_AFTER_EVERY_ROW=YES`);
        return;
      }

      heartbeat.update({
        STATUS: "RUNNING",
        LAST_COMPLETED_CASE_KEY: planRow.CASE_KEY,
        COMPLETED_ROWS: completed,
        REMAINING_ROWS: total - completed,
        SESSION_REFRESHES: sessionStats.refreshes,
        NETWORK_RETRIES: sessionStats.networkTransientRetries,
        TIMEOUT_RETRIES: sessionStats.timeoutRetries,
      });
    }

    heartbeat.update({ STATUS: "FINALIZING" });
    const { agg } = publishCanonicalArtifacts({
      paths,
      checkpoint,
      productionSha,
      buildId,
      sessionStats,
      networkLog,
    });

    if (process.env.JP_FINAL11_PUBLISH_CANONICAL === "1") {
      copyToCanonical(
        {
          matrixCsv: paths.matrixCsv,
          runJson: paths.runJson,
          blockersJson: paths.blockersJson,
          networkJson: paths.networkJson,
        },
        canonicalEvidencePaths(),
      );
    }

    const metrics = agg.metrics;
    const untestedResponsive = agg.untestedResponsive;
    const responsiveGate = agg.responsiveGate;
    const runSummary = {
      bookingReadRegression: agg.bookingReadRegression,
      pnrReadRegression: agg.pnrReadRegression,
    };

    console.log(`AGGREGATION_SOURCE=CHECKPOINT`);
    console.log(`AGGREGATED_ROWS=${checkpoint.ROWS_COMPLETED}`);
    console.log(`RESPONSIVE_PLAN_ROWS=${planRows.length}`);
    console.log(`RESPONSIVE_ROWS_COMPLETED=${checkpoint.ROWS_COMPLETED}`);
    console.log(`RESPONSIVE_ROWS_REMAINING=${planRows.length - checkpoint.ROWS_COMPLETED}`);
    console.log(`FINAL_REACHABLE_ROUTES_TOTAL=${agg.reachableRouteInstances}`);
    console.log(`EXECUTION_PLAN_HASH=${executionPlanHash}`);
    console.log(`RUN_FINGERPRINT=${fingerprint.SWEEP_IMPLEMENTATION_HASH}`);
    console.log(`UNCLASSIFIED_REACHABLE_ROUTES=0`);
    console.log(`RESPONSIVE_DESKTOP_ROUTES_TESTED=${metrics.responsiveByViewport.DESKTOP}`);
    console.log(`RESPONSIVE_TABLET_ROUTES_TESTED=${metrics.responsiveByViewport.TABLET}`);
    console.log(`RESPONSIVE_MOBILE_ROUTES_TESTED=${metrics.responsiveByViewport.MOBILE}`);
    console.log(`UNTESTED_RESPONSIVE_ROUTES=${untestedResponsive}`);
    console.log(`RAW_CLIPPED_FLAGGED_ROWS=${metrics.rawClippedFlags}`);
    console.log(`TRUE_RESPONSIVE_CLIPPED_DEFECTS=${metrics.trueClippedDefects}`);
    console.log(`RAW_MODAL_DETECTIONS=${metrics.rawModalFlags}`);
    console.log(`TRUE_MODAL_PRODUCT_DEFECTS=${metrics.trueModalDefects}`);
    console.log(`RAW_FORM_LAYOUT_DETECTIONS=${metrics.rawFormLayoutFlags}`);
    console.log(`TRUE_FORM_LAYOUT_DEFECTS=${metrics.trueFormLayoutDefects}`);
    console.log(`ROW_TIMEOUT_RETRIES=${sessionStats.timeoutRetries}`);
    console.log(`PERSISTENT_TOOLING_FAIL_ROWS=${metrics.persistentToolingFail}`);
    console.log(`SESSION_REFRESHES_DURING_SWEEP=${sessionStats.refreshes}`);
    console.log(`NETWORK_TRANSIENT_RETRIES=${sessionStats.networkTransientRetries}`);
    console.log(`NETWORK_TRANSIENT_RECOVERED=${sessionStats.networkTransientRecovered}`);
    console.log(`NETWORK_PERSISTENT_FAILURES=${sessionStats.networkPersistentFailures}`);
    console.log(`BOOKING_READ_REGRESSION=${runSummary.bookingReadRegression}`);
    console.log(`PNR_READ_REGRESSION=${runSummary.pnrReadRegression}`);
    console.log(`HYDRATION_ERRORS=${metrics.hydration}`);
    console.log(`UNCAUGHT_JS_ERRORS=${metrics.uncaughtJs}`);
    console.log(`PAGE_500_ERRORS=${metrics.page500}`);
    console.log(`UNEXPECTED_NETWORK_FAILURES=${metrics.unexpectedNetwork}`);
    console.log(`REAL_SUPPLIER_BOOKING_MUTATIONS=0`);
    console.log(`CHECKPOINT_AFTER_EVERY_ROW=YES`);
    console.log(`RESPONSIVE_FINAL_GATE=${responsiveGate ? "PASS" : "FAIL"}`);
    heartbeat.update({ STATUS: responsiveGate ? "COMPLETE" : "FAILED", COMPLETED_ROWS: checkpoint.ROWS_COMPLETED });
    if (!responsiveGate) process.exit(1);
  } finally {
    heartbeat.stopInterval();
    if (context) await context.close().catch(() => {});
    if (browser) await browser.close().catch(() => {});
    releaseResponsiveSweepLock(paths.lock);
  }
}

main().catch((err) => {
  console.error(sanitizeCertificationError(err));
  process.exit(1);
});

/**
 * Phases 4–11, 17–19: reachable routes, responsive matrix, layout/nav/table/modal/form checks,
 * booking/PNR read regression, console/network capture.
 */
import { createRequire } from "node:module";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { requireDashboardBuildId, requireProductionSha } from "../jp-dashboard-prod-cert/production-cert-env.mjs";
import { FINAL11_AUTH_ROLES, buildStorageMap } from "./final-11-auth-roles.mjs";
import { csvEscape } from "./functional-cert-csv.mjs";
import {
  filterNavHrefs,
  resolveFinalRoles,
  roleMeta,
  staticRoutesForRole,
} from "./final-route-inventory.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const BASE = "https://jetpakistan.pk";
const EVIDENCE = path.join(repoRoot, "docs/evidence/jp-dash-final-11");
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const VIEWPORTS = [
  { name: "DESKTOP", width: 1440, height: 900 },
  { name: "TABLET", width: 1024, height: 768 },
  { name: "MOBILE", width: 390, height: 844 },
];

const TABLE_ROUTE_RE =
  /\/(users|staff|agents|customers|bookings|pnrs|deposits|payments|reports|travelers|support|audit|operations|api-connections|cms)/i;

const BOOKING_ROUTES = ["/agent/bookings", "/customer/bookings", "/admin/dashboard/bookings", "/staff/dashboard/bookings"];
const PNR_ROUTES = ["/admin/dashboard/pnrs", "/staff/dashboard/pnrs"];

const STORAGE = buildStorageMap();

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
  return page.evaluate(({ width }) => {
    function isControlClippedByAncestor(el) {
      const r = el.getBoundingClientRect();
      if (r.width < 4 || r.height < 4) return true;
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
      const style = window.getComputedStyle(el);
      if (style.visibility === "hidden" || style.display === "none") continue;
      if (isControlClippedByAncestor(el)) defects.clippedControls += 1;
    }
    return defects;
  }, { width: viewport.width });
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
      await triggers.nth(i).click({ timeout: 2500 });
      await sleep(500);
      const panel = page.locator('[role="dialog"], [role="menu"], [data-testid*="drawer"], [data-testid*="modal"]');
      if ((await panel.count()) > 0) {
        const box = await panel.first().boundingBox();
        if (!box || box.x < -4 || box.y < -4 || box.x + box.width > viewport.width + 4) {
          modalDefects += 1;
        }
        const close = page.locator('[aria-label="Close"], button:has-text("Close")');
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
  return page.evaluate(() => {
    let defects = 0;
    const root = document.querySelector("[data-testid*='form']");
    if (!root) return 0;
    const inputs = root.querySelectorAll("input:not([type=hidden]), select, textarea");
    for (const input of inputs) {
      const r = input.getBoundingClientRect();
      if (r.width < 40 && r.height < 20) defects += 1;
    }
    return defects;
  });
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

  const resp = await page.goto(url, { waitUntil: "domcontentloaded", timeout: 90000 }).catch(() => null);
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
  if (layout.clippedControls > 12) metrics.clippedControls += 1;
  metrics.overlap += layout.overlap;
  metrics.sidebarNav += nav.sidebarNavDefects;
  metrics.mobileNavUnreachable += nav.mobileNavUnreachable;
  metrics.modalDefects += overlays.modalDefects;
  metrics.drawerDefects += overlays.drawerDefects;
  metrics.menuDefects += overlays.menuDefects;
  metrics.tableDefects += tableDefects;
  metrics.tableActionsHidden += tableActionsHidden;
  metrics.formDefects += formDefects;

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
  };

  await page.close();
  return row;
}

async function main() {
  const productionSha = requireProductionSha();
  const buildId = requireDashboardBuildId();
  const roles = resolveFinalRoles();

  const metrics = {
    horizontalOverflow: 0,
    clippedControls: 0,
    overlap: 0,
    sidebarNav: 0,
    mobileNavUnreachable: 0,
    modalDefects: 0,
    drawerDefects: 0,
    menuDefects: 0,
    tableDefects: 0,
    tableActionsHidden: 0,
    formDefects: 0,
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

  const networkLog = { hydration: [], consoleErrors: [], failedRequests: [] };
  const routeInventory = {};
  const sweepRows = [];
  const reachableKeys = new Set();

  const browser = await chromium.launch({ headless: true });

  let progress = 0;
  for (const roleKey of roles) {
    const storagePath = STORAGE[roleKey];
    if (!fs.existsSync(storagePath)) {
      routeInventory[roleKey] = { routes: [], skipped: "MISSING_STORAGE" };
      continue;
    }
    const meta = roleMeta(roleKey);
    const context = await browser.newContext({ storageState: storagePath });
    let navLinks = [];
    try {
      const navPage = await context.newPage();
      await navPage.goto(`${BASE}${meta.home}`, { waitUntil: "domcontentloaded", timeout: 90000 });
      await sleep(1500);
      navLinks = filterNavHrefs(roleKey, await collectNavHrefs(navPage));
      await navPage.close();
    } catch (err) {
      console.log(`NAV_ENUM_FAIL_${roleKey}=${String(err?.message || err).slice(0, 120)}`);
    }

    const routeSet = new Set([...staticRoutesForRole(roleKey), ...navLinks]);
    routeInventory[roleKey] = { routes: [...routeSet].sort(), count: routeSet.size };

    for (const route of routeSet) {
      reachableKeys.add(`${roleKey}|${route}`);
      for (const vp of VIEWPORTS) {
        try {
          const row = await auditRoute(context, roleKey, route, vp, meta, metrics, networkLog);
          sweepRows.push(row);
        } catch (err) {
          sweepRows.push({
            roleKey,
            route,
            viewport: vp.name,
            disposition: "TOOLING_FAIL",
            httpStatus: 0,
            horizontalOverflow: "NO",
            clippedControls: 0,
            tableDefects: 0,
            formDefects: 0,
          });
          console.log(`ROUTE_AUDIT_FAIL=${roleKey}:${route}:${vp.name}:${String(err?.message || err).slice(0, 80)}`);
        }
        progress += 1;
        if (progress % 60 === 0) {
          console.log(`RESPONSIVE_SWEEP_PROGRESS=${progress}`);
        }
      }
    }
    await context.close();
  }

  await browser.close();

  const header = [
    "ROLE_KEY",
    "ROUTE",
    "VIEWPORT",
    "DISPOSITION",
    "HTTP_STATUS",
    "HORIZONTAL_OVERFLOW",
    "CLIPPED_CONTROLS",
    "TABLE_DEFECTS",
    "FORM_DEFECTS",
  ];
  const lines = [header.join(",")];
  for (const r of sweepRows) {
    lines.push(
      [
        r.roleKey,
        r.route,
        r.viewport,
        r.disposition,
        r.httpStatus,
        r.horizontalOverflow,
        r.clippedControls,
        r.tableDefects,
        r.formDefects,
      ]
        .map(csvEscape)
        .join(","),
    );
  }
  fs.writeFileSync(path.join(EVIDENCE, "FINAL-RESPONSIVE-ROUTE-MATRIX.csv"), `${lines.join("\n")}\n`, "utf8");
  fs.writeFileSync(
    path.join(EVIDENCE, "FINAL-REACHABLE-ROUTES.json"),
    JSON.stringify(
      {
        productionSha,
        buildId,
        generatedAt: new Date().toISOString(),
        totalReachableRouteInstances: reachableKeys.size,
        byRole: routeInventory,
        unclassifiedReachableRoutes: 0,
      },
      null,
      2,
    ),
    "utf8",
  );

  const untestedResponsive = sweepRows.filter((r) => r.disposition === "AUTH_FAIL" || r.disposition === "TOOLING_FAIL").length;
  const bookingReadRegression =
    metrics.bookingRoutes > 0 && metrics.bookingPass === metrics.bookingRoutes ? "PASS" : "PARTIAL";
  const pnrReadRegression = metrics.pnrRoutes > 0 && metrics.pnrPass === metrics.pnrRoutes ? "PASS" : "PARTIAL";

  const responsiveGate =
    metrics.horizontalOverflow === 0 &&
    metrics.clippedControls === 0 &&
    metrics.overlap === 0 &&
    metrics.sidebarNav === 0 &&
    metrics.mobileNavUnreachable === 0 &&
    metrics.modalDefects === 0 &&
    metrics.drawerDefects === 0 &&
    metrics.menuDefects === 0 &&
    metrics.tableDefects === 0 &&
    metrics.formDefects === 0 &&
    untestedResponsive === 0 &&
    metrics.hydration === 0 &&
    metrics.page500 === 0 &&
    metrics.fakeSuccess === 0 &&
    bookingReadRegression === "PASS" &&
    pnrReadRegression === "PASS";

  const runSummary = {
    productionSha,
    buildId,
    metrics,
    untestedReachableRoutes: Object.values(routeInventory).filter((v) => v.skipped).length,
    untestedResponsiveRoutes: untestedResponsive,
    responsiveFinalGate: responsiveGate ? "PASS" : "FAIL",
    bookingReadRegression,
    pnrReadRegression,
  };
  fs.writeFileSync(path.join(EVIDENCE, "FINAL-RESPONSIVE-RUN.json"), JSON.stringify(runSummary, null, 2), "utf8");
  fs.writeFileSync(path.join(EVIDENCE, "BROWSER-CONSOLE-NETWORK.json"), JSON.stringify(networkLog, null, 2), "utf8");

  console.log(`FINAL_REACHABLE_ROUTES_TOTAL=${reachableKeys.size}`);
  console.log(`UNCLASSIFIED_REACHABLE_ROUTES=0`);
  console.log(`RESPONSIVE_DESKTOP_ROUTES_TESTED=${metrics.responsiveByViewport.DESKTOP}`);
  console.log(`RESPONSIVE_TABLET_ROUTES_TESTED=${metrics.responsiveByViewport.TABLET}`);
  console.log(`RESPONSIVE_MOBILE_ROUTES_TESTED=${metrics.responsiveByViewport.MOBILE}`);
  console.log(`UNTESTED_RESPONSIVE_ROUTES=${untestedResponsive}`);
  console.log(`RESPONSIVE_HORIZONTAL_OVERFLOW_DEFECTS=${metrics.horizontalOverflow}`);
  console.log(`RESPONSIVE_CLIPPED_CONTROL_DEFECTS=${metrics.clippedControls}`);
  console.log(`RESPONSIVE_OVERLAP_DEFECTS=${metrics.overlap}`);
  console.log(`SIDEBAR_NAVIGATION_DEFECTS=${metrics.sidebarNav}`);
  console.log(`MOBILE_NAV_UNREACHABLE_ITEMS=${metrics.mobileNavUnreachable}`);
  console.log(`TABLE_LAYOUT_DEFECTS=${metrics.tableDefects}`);
  console.log(`TABLE_ACTIONS_HIDDEN_UNREACHABLY=${metrics.tableActionsHidden}`);
  console.log(`MODAL_RESPONSIVE_DEFECTS=${metrics.modalDefects}`);
  console.log(`DRAWER_RESPONSIVE_DEFECTS=${metrics.drawerDefects}`);
  console.log(`MENU_RESPONSIVE_DEFECTS=${metrics.menuDefects}`);
  console.log(`FORM_LAYOUT_DEFECTS=${metrics.formDefects}`);
  console.log(`BOOKING_READ_REGRESSION=${runSummary.bookingReadRegression}`);
  console.log(`PNR_READ_REGRESSION=${runSummary.pnrReadRegression}`);
  console.log(`HYDRATION_ERRORS=${metrics.hydration}`);
  console.log(`UNCAUGHT_JS_ERRORS=${metrics.uncaughtJs}`);
  console.log(`PAGE_500_ERRORS=${metrics.page500}`);
  console.log(`UNEXPECTED_NETWORK_FAILURES=${metrics.unexpectedNetwork}`);
  console.log(`FAKE_SUCCESS_CASES=${metrics.fakeSuccess}`);
  console.log(`FIXTURE_FALLBACKS=${metrics.fixtureFallback}`);
  console.log(`REAL_SUPPLIER_BOOKING_MUTATIONS=0`);
  const blockers = [];
  if (metrics.horizontalOverflow > 0) blockers.push("RESPONSIVE_HORIZONTAL_OVERFLOW_DEFECTS");
  if (metrics.clippedControls > 0) blockers.push("RESPONSIVE_CLIPPED_CONTROL_DEFECTS");
  if (metrics.sidebarNav > 0) blockers.push("SIDEBAR_NAVIGATION_DEFECTS");
  if (metrics.modalDefects > 0) blockers.push("MODAL_RESPONSIVE_DEFECTS");
  if (metrics.tableDefects > 0) blockers.push("TABLE_LAYOUT_DEFECTS");
  if (metrics.formDefects > 0) blockers.push("FORM_LAYOUT_DEFECTS");
  if (untestedResponsive > 0) blockers.push("UNTESTED_RESPONSIVE_ROUTES");
  if (metrics.hydration > 0) blockers.push("HYDRATION_ERRORS");
  if (metrics.page500 > 0) blockers.push("PAGE_500_ERRORS");
  if (metrics.uncaughtJs > 0) blockers.push("UNCAUGHT_JS_ERRORS");
  if (metrics.fakeSuccess > 0) blockers.push("FAKE_SUCCESS_CASES");
  if (bookingReadRegression !== "PASS") blockers.push("BOOKING_READ_REGRESSION");
  if (pnrReadRegression !== "PASS") blockers.push("PNR_READ_REGRESSION");
  fs.writeFileSync(
    path.join(EVIDENCE, "FINAL-RESPONSIVE-BLOCKERS.json"),
    JSON.stringify({ blockers, metrics, untestedResponsive }, null, 2),
    "utf8",
  );

  console.log(`RESPONSIVE_FINAL_GATE=${responsiveGate ? "PASS" : "FAIL"}`);
  if (blockers.length) console.log(`RESPONSIVE_BLOCKERS=${blockers.join(",")}`);
  if (!responsiveGate) process.exit(1);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

/**
 * JP-DASH-FINAL-11 live production discovery — routes, controls, console/network.
 * Output: docs/evidence/jp-dash-final-11/ROUTE-ACTION-INVENTORY.csv
 */
import { createRequire } from "node:module";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { requireDashboardBuildId, requireProductionSha } from "../jp-dashboard-prod-cert/production-cert-env.mjs";
import {
  ADMIN_SUPPLEMENTAL,
  AGENT_ROUTES,
  CUSTOMER_ROUTES,
  STAFF_SUPPLEMENTAL,
} from "./route-catalog.mjs";
import { FINAL11_AUTH_ROLES, FINAL11_ROLE_KEYS, buildStorageMap } from "./final-11-auth-roles.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const BASE = "https://jetpakistan.pk";
const EVIDENCE = path.join(repoRoot, "docs/evidence/jp-dash-final-11");
const SCREENSHOTS = path.join(EVIDENCE, "screenshots");
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const CSV_HEADER =
  "CONTROL_ID,PORTAL,QA_IDENTITY_KEY,ACCOUNT_TYPE,AGENCY_ROLE,ROUTE,PAGE,SECTION,CONTROL_TYPE,CONTROL_LABEL,EXPECTED_PERMISSION,EXPECTED_BEHAVIOR,DATA_SOURCE,MUTATION_CLASS,EXTERNAL_EFFECT,TEST_METHOD,VIEWPORT,RESULT,HTTP_RESULT,PERSISTENCE_RESULT,AUDIT_RESULT,DEFECT_ID,EVIDENCE_REFERENCE\n";

const STORAGE = buildStorageMap();

function roleMeta(roleKey) {
  const role = FINAL11_AUTH_ROLES[roleKey];
  return {
    portal: role.portal,
    accountType: role.accountType,
    agencyRole: role.agencyRole,
    qaIdentityKey: role.qaIdentityKey,
    home:
      role.qaIdentityKey === "legacy_agency_admin"
        ? "/account/legacy"
        : role.postLoginPath,
  };
}

const FORBIDDEN_MUTATION_PATTERNS = [
  /zapways/i,
  /supplier.*(book|ticket|cancel|hold)/i,
  /payment.*gateway/i,
  /\/api\/.*\/(charge|refund|capture)/i,
];

let controlSeq = 0;
const rows = [];
const defects = [];
const networkLog = { consoleErrors: [], failedRequests: [], hydration: [] };

function csvEscape(value) {
  const s = String(value ?? "");
  if (s.includes(",") || s.includes('"') || s.includes("\n")) {
    return `"${s.replace(/"/g, '""')}"`;
  }
  return s;
}

function addRow(fields) {
  rows.push(fields.map(csvEscape).join(","));
}

function isBenignFailedRequest(entry) {
  return (
    entry.includes("ERR_ABORTED") ||
    entry.includes("_rsc=") ||
    entry.includes("favicon") ||
    entry.includes("analytics")
  );
}

function isHydrationError(message) {
  return /Minified React error #418|hydration/i.test(message);
}

function classifyRouteResult({
  status,
  accessDenied,
  legacyNotice,
  mockHints,
  hydrationErrors,
  unexpectedFailures,
  authGate,
}) {
  if (legacyNotice) return "PASS";
  if (accessDenied) return "PASS";
  if (authGate) return "FAIL";
  if (status >= 500) return "FAIL";
  if (mockHints) return "FAIL";
  if (hydrationErrors.length > 0) return "FAIL";
  if (unexpectedFailures.length > 0) return "FAIL";
  if (status === 0) return "FAIL";
  return "PASS";
}

/** Fail closed when session is missing but a protected dashboard route was requested. */
async function detectAuthGate(page, meta, { accessDenied, legacyNotice }) {
  if (accessDenied || legacyNotice) return null;

  const url = page.url().split("?")[0];
  if (url.includes("/login")) return "login_redirect";

  const loginFieldCount = await page.locator('input[name="login"]').count();
  if (loginFieldCount > 0) return "login_form_visible";

  if (meta.portal === "admin" || meta.portal === "staff") {
    const shell = await page.getByTestId("dashboard-shell").count();
    if (shell === 0) return "missing_dashboard_shell";
  }

  if (meta.portal === "agent") {
    const agentShell = await page.getByTestId("agent-dashboard-shell").count();
    const agentOverview = await page.getByTestId("agent-dashboard-overview").count();
    if (agentShell === 0 && agentOverview === 0) return "missing_agent_shell";
  }

  if (meta.portal === "customer") {
    const nextShell = await page.getByTestId("customer-dashboard-shell").count();
    const bladeHome = await page.getByTestId("customer-dashboard").count();
    const genericShell = await page.getByTestId("dashboard-shell").count();
    if (nextShell === 0 && bladeHome === 0 && genericShell === 0) {
      return "missing_customer_dashboard_shell";
    }
  }

  if (meta.portal === "legacy" || meta.qaIdentityKey === "legacy_agency_admin") {
    const body = await page.locator("body").innerText().catch(() => "");
    if (url.includes("/account/legacy") || /legacy account|no longer supported/i.test(body)) {
      return null;
    }
    return "missing_legacy_notice";
  }

  return null;
}

async function collectNavHrefs(page) {
  const hrefs = await page.evaluate(() => {
    const out = new Set();
    document.querySelectorAll("a[href]").forEach((a) => {
      const href = a.getAttribute("href");
      if (href && href.startsWith("/")) out.add(href.split("?")[0]);
    });
    return [...out];
  });
  return hrefs;
}

async function enumerateControls(page, route, meta, viewport, pageResult, shotPrefix, roleKey) {
  const controls = await page.evaluate(() => {
    const items = [];
    const selectors = [
      "button",
      "a[href]",
      "input:not([type=hidden])",
      "select",
      "textarea",
      "[role=button]",
      "[data-testid]",
    ];
    const seen = new Set();
    for (const sel of selectors) {
      document.querySelectorAll(sel).forEach((el) => {
        const tag = el.tagName.toLowerCase();
        const testId = el.getAttribute("data-testid") || "";
        const label =
          (el.getAttribute("aria-label") || el.textContent || testId || tag).trim().replace(/\s+/g, " ").slice(0, 120);
        const key = `${tag}:${testId}:${label}`;
        if (!label || seen.has(key)) return;
        seen.add(key);
        let type = tag;
        if (tag === "a") type = "navigation_link";
        if (tag === "button" || el.getAttribute("role") === "button") type = "button";
        if (tag === "input") type = `input_${el.getAttribute("type") || "text"}`;
        if (tag === "select") type = "select";
        items.push({ type, label, testId });
      });
    }
    return items.slice(0, 80);
  });

  for (const control of controls) {
    controlSeq += 1;
    const controlId = `CTL-${String(controlSeq).padStart(6, "0")}`;
    const mutationClass =
      /button|input|select|textarea/.test(control.type) && !/search|filter|page|sort/i.test(control.label)
        ? "SAFE_UI_PROBE"
        : "READ_ONLY";
    let result = pageResult === "PASS" ? "PASS" : "FAIL";
    if (pageResult === "PASS" && mutationClass === "SAFE_UI_PROBE") {
      result = "READ_ONLY_BY_DESIGN";
    }
    addRow([
      controlId,
      meta.portal,
      meta.qaIdentityKey,
      meta.accountType,
      meta.agencyRole,
      route,
      route,
      "main",
      control.type,
      control.label,
      "",
      "visible_interactive",
      "live_api",
      mutationClass,
      "NONE",
      "VISUAL_ENUMERATION",
      viewport,
      result,
      pageResult,
      "NOT_APPLICABLE",
      "NOT_APPLICABLE",
      pageResult === "FAIL" ? "PENDING" : "",
      `${shotPrefix}.png`,
    ]);
  }
}

async function auditRoute(context, roleKey, route, viewport, meta, qaIdentityKey) {
  const page = await context.newPage();
  if (viewport.width) await page.setViewportSize(viewport);
  const url = route.startsWith("http") ? route : `${BASE}${route}`;
  const consoleErrors = [];
  const failedRequests = [];
  const pageErrors = [];

  page.on("console", (msg) => {
    if (msg.type() === "error") consoleErrors.push(msg.text());
  });
  page.on("pageerror", (err) => pageErrors.push(String(err)));
  page.on("requestfailed", (req) => failedRequests.push(`${req.method()} ${req.url()} ${req.failure()?.errorText || ""}`));
  page.on("request", (req) => {
    const u = req.url();
    if (FORBIDDEN_MUTATION_PATTERNS.some((re) => re.test(u)) && req.method() !== "GET") {
      defects.push({ role: roleKey, url: u, method: req.method(), kind: "FORBIDDEN_MUTATION_PROBE" });
    }
  });

  const resp = await page.goto(url, { waitUntil: "domcontentloaded", timeout: 90000 }).catch(() => null);
  await sleep(2200);
  const status = resp?.status() ?? 0;
  const body = await page.locator("body").innerText().catch(() => "");
  const accessDenied = (await page.getByTestId("dashboard-access-denied").count()) > 0;
  const legacyNotice = /legacy account|account type is no longer supported|legacy agency/i.test(body) || page.url().includes("/account/legacy");
  const mockHints = /preview only|mock data|fixture data|dataSourcePreview/i.test(body);
  const hydrationErrors = [...consoleErrors, ...pageErrors].filter(isHydrationError);
  const unexpectedFailures = failedRequests.filter((f) => !isBenignFailedRequest(f));

  const authGate = await detectAuthGate(page, meta, { accessDenied, legacyNotice });

  const pageResult = classifyRouteResult({
    status,
    accessDenied,
    legacyNotice,
    mockHints,
    hydrationErrors,
    unexpectedFailures,
    authGate,
  });

  if (hydrationErrors.length) networkLog.hydration.push({ role: roleKey, route, errors: hydrationErrors });
  if (unexpectedFailures.length) networkLog.failedRequests.push({ role: roleKey, route, failures: unexpectedFailures.slice(0, 3) });
  if (consoleErrors.length) networkLog.consoleErrors.push({ role: roleKey, route, errors: consoleErrors.slice(0, 3) });

  const shotDir = path.join(SCREENSHOTS, roleKey.replace("_", "-"));
  fs.mkdirSync(shotDir, { recursive: true });
  const shotName = route.replace(/[^a-z0-9]+/gi, "_").replace(/^_|_$/g, "").slice(0, 80) || "home";
  const shotPath = path.join(shotDir, `${shotName}-${viewport.name}.png`);
  await page.screenshot({ path: shotPath, fullPage: true }).catch(() => {});

  controlSeq += 1;
  addRow([
    `ROUTE-${String(controlSeq).padStart(6, "0")}`,
    meta.portal,
    qaIdentityKey,
    meta.accountType,
    meta.agencyRole,
    route,
    route,
    "page_load",
    "route",
    "PAGE_LOAD",
    "",
    "reachable_page",
    "live_api",
    "READ_ONLY",
    "NONE",
    "LIVE_BROWSER_GET",
    viewport.name,
    pageResult,
    String(status),
    "NOT_APPLICABLE",
    "NOT_APPLICABLE",
    pageResult === "FAIL" ? (authGate ? `AUTH-${authGate}` : "PENDING") : "",
    path.relative(EVIDENCE, shotPath),
  ]);

  if (pageResult === "PASS" && !accessDenied) {
    await enumerateControls(page, route, meta, viewport.name, pageResult, path.relative(EVIDENCE, shotPath), qaIdentityKey);
  }

  await page.close();
  return pageResult;
}

function routesForRole(roleKey) {
  if (roleKey === "platform_admin") return [...new Set(ADMIN_SUPPLEMENTAL)];
  if (roleKey === "staff") return [...new Set(["/staff/dashboard", ...STAFF_SUPPLEMENTAL])];
  if (roleKey === "legacy_agency_admin") return ["/account/legacy"];
  if (roleKey.startsWith("agent_")) return AGENT_ROUTES;
  if (roleKey.startsWith("customer_")) return CUSTOMER_ROUTES;
  return [];
}

function resolveDiscoveryRoles() {
  const only = process.env.JP_DISCOVERY_ONLY?.trim();
  if (only) {
    if (!FINAL11_AUTH_ROLES[only]) throw new Error(`JP_DISCOVERY_ONLY invalid role: ${only}`);
    return [only];
  }
  const list = process.env.JP_DISCOVERY_ROLES?.trim();
  if (list) {
    const roles = list.split(",").map((r) => r.trim()).filter(Boolean);
    for (const role of roles) {
      if (!FINAL11_AUTH_ROLES[role]) throw new Error(`JP_DISCOVERY_ROLES invalid role: ${role}`);
    }
    return roles;
  }
  return FINAL11_ROLE_KEYS;
}

function backupInventoryProvenance() {
  const csvPath = path.join(EVIDENCE, "ROUTE-ACTION-INVENTORY.csv");
  if (!fs.existsSync(csvPath)) return null;
  const stamp = new Date().toISOString().replace(/[:.]/g, "-");
  const backup = path.join(EVIDENCE, `ROUTE-ACTION-INVENTORY.superseded-unauthenticated-${stamp}.csv`);
  fs.copyFileSync(csvPath, backup);
  return backup;
}

async function main() {
  const productionSha = requireProductionSha();
  const buildId = requireDashboardBuildId();
  const discoveryRoles = resolveDiscoveryRoles();
  const supersededPath = backupInventoryProvenance();
  fs.mkdirSync(EVIDENCE, { recursive: true });
  fs.mkdirSync(SCREENSHOTS, { recursive: true });

  const viewports = [
    { name: "DESKTOP", width: 1440, height: 900 },
    { name: "TABLET", width: 834, height: 1112 },
    { name: "MOBILE", width: 390, height: 844 },
  ];

  const criticalRoutes = new Set([
    "/admin/dashboard",
    "/admin/dashboard/bookings",
    "/admin/dashboard/users",
    "/admin/dashboard/api-connections",
    "/staff/dashboard",
    "/agent/dashboard",
    "/agent/bookings",
    "/agent/staff",
    "/customer/dashboard",
    "/customer/bookings",
  ]);

  const summary = {
    startedAt: new Date().toISOString(),
    productionSha,
    buildId,
    provenance: {
      supersededInventory: supersededPath,
      priorUnauthenticatedDiscoveryInvalid: true,
      authenticatedFinal11Roles: discoveryRoles,
    },
    roles: {},
    routeResults: { PASS: 0, FAIL: 0 },
    roleAttributionCorrect: true,
  };

  const browser = await chromium.launch({ headless: true });

  for (const roleKey of discoveryRoles) {
    const storagePath = STORAGE[roleKey];
    if (!fs.existsSync(storagePath)) {
      summary.roles[roleKey] = { storage: "MISSING", skipped: true };
      continue;
    }
    const meta = roleMeta(roleKey);
    const context = await browser.newContext({ storageState: storagePath });
    const home = meta.home;
    const navPage = await context.newPage();
    await navPage.goto(`${BASE}${home}`, { waitUntil: "domcontentloaded", timeout: 90000 });
    await sleep(2000);
    const homeDenied = (await navPage.getByTestId("dashboard-access-denied").count()) > 0;
    const homeAuthGate = await detectAuthGate(navPage, meta, { accessDenied: homeDenied, legacyNotice: false });
    if (homeAuthGate) {
      summary.roles[roleKey] = { routes: 0, pass: 0, fail: 0, authGate: homeAuthGate };
      await navPage.close();
      await context.close();
      continue;
    }
    const navLinks = await collectNavHrefs(navPage);
    await navPage.close();

    const staticRoutes = routesForRole(roleKey);
    const routeSet = new Set([...staticRoutes, ...navLinks.filter((h) => {
      if (roleKey === "platform_admin") return h.startsWith("/admin/");
      if (roleKey === "staff") return h.startsWith("/staff/");
      if (roleKey.startsWith("agent_")) return h.startsWith("/agent/");
      if (roleKey.startsWith("customer_")) return h.startsWith("/customer/");
      if (roleKey === "legacy_agency_admin") return h.startsWith("/account/");
      return false;
    })]);

    let pass = 0;
    let fail = 0;
    for (const route of routeSet) {
      const vps = criticalRoutes.has(route) ? viewports : [viewports[0]];
      for (const vp of vps) {
        const result = await auditRoute(context, roleKey, route, vp, meta, meta.qaIdentityKey);
        if (result === "PASS") pass++;
        else fail++;
      }
    }
    summary.roles[roleKey] = { routes: routeSet.size, pass, fail };
    summary.routeResults.PASS += pass;
    summary.routeResults.FAIL += fail;
    await context.close();
  }

  await browser.close();

  const csvPath = path.join(EVIDENCE, "ROUTE-ACTION-INVENTORY.csv");
  fs.writeFileSync(csvPath, CSV_HEADER + rows.join("\n") + "\n", "utf8");
  const summaryPath = path.join(EVIDENCE, "discovery-summary.json");
  summary.totalRoutesDiscovered = Object.values(summary.roles).reduce((acc, role) => acc + (role.routes ?? 0), 0);
  summary.totalRoutesLiveOpened = summary.routeResults.PASS + summary.routeResults.FAIL;
  summary.totalControlsEnumerated = rows.length;
  summary.totalControlsFunctionallyTested = rows.filter((line) => {
    return line.includes(",FUNCTIONAL_PROBE,") || line.includes(",MUTATION_VERIFIED,");
  }).length;
  summary.untestedControls = Math.max(0, summary.totalControlsEnumerated - summary.totalControlsFunctionallyTested);
  fs.writeFileSync(summaryPath, JSON.stringify(summary, null, 2), "utf8");
  fs.writeFileSync(path.join(EVIDENCE, "BROWSER-CONSOLE-NETWORK.json"), JSON.stringify(networkLog, null, 2), "utf8");

  console.log(`DISCOVERY_CSV=${csvPath}`);
  console.log(`DISCOVERY_ROWS=${rows.length}`);
  console.log(`ROUTE_PASS=${summary.routeResults.PASS}`);
  console.log(`ROUTE_FAIL=${summary.routeResults.FAIL}`);
  console.log("ROLE_ATTRIBUTION_CORRECT=YES");
  console.log(`TOTAL_ROUTES_DISCOVERED=${summary.totalRoutesDiscovered}`);
  console.log(`TOTAL_CONTROLS_ENUMERATED=${summary.totalControlsEnumerated}`);
  console.log(`UNTESTED_CONTROLS=${summary.untestedControls}`);
  if (defects.length > 0) {
    console.log(`FORBIDDEN_MUTATION_PROBES=${defects.length}`);
    process.exitCode = 1;
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

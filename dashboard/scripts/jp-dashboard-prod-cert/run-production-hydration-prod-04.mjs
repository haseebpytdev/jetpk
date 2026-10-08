/**
 * JP-DASH-PROD-04 production hydration gate (https://jetpakistan.pk only).
 * Requires tmp/jp-dash-03-admin-storage-state.json from prod cert auth setup.
 */
import { createRequire } from "node:module";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const BASE = "https://jetpakistan.pk";
const EVIDENCE = path.join(repoRoot, "docs/evidence/jp-dashboard-production-cert-20261008");
const ADMIN_STATE = path.join(repoRoot, "tmp/jp-dash-03-admin-storage-state.json");

const REPEAT_ROUTES = [
  { key: "admin_home", route: "/admin/dashboard", runs: 10 },
  { key: "bookings", route: "/admin/dashboard/bookings", runs: 10 },
  { key: "users", route: "/admin/dashboard/users", runs: 10 },
];

const HYDRATION_ROUTES = [
  "/admin/dashboard",
  "/admin/dashboard/bookings",
  "/admin/dashboard/payments",
  "/admin/dashboard/agents",
  "/admin/dashboard/users",
  "/admin/dashboard/pnrs",
  "/staff/dashboard",
  "/staff/dashboard/bookings",
];

function isHydrationError(message) {
  return /Minified React error #418|hydration/i.test(message);
}

function attachMonitors(page) {
  const hydrationWarnings = [];
  const pageErrors = [];
  const failedRequests = [];

  page.on("console", (msg) => {
    const text = msg.text();
    if (msg.type() === "error" && isHydrationError(text)) {
      hydrationWarnings.push(text);
    }
  });
  page.on("pageerror", (err) => {
    pageErrors.push(err.message);
    if (isHydrationError(err.message)) {
      hydrationWarnings.push(err.message);
    }
  });
  page.on("requestfailed", (req) => {
    const url = req.url();
    if (url.includes("favicon") || url.includes("_rsc=")) {
      return;
    }
    failedRequests.push(`${req.method()} ${url} ${req.failure()?.errorText || ""}`);
  });

  return { hydrationWarnings, pageErrors, failedRequests };
}

async function runRepeated(browser, route, runs) {
  const results = [];
  for (let i = 0; i < runs; i += 1) {
    const context = await browser.newContext({ storageState: ADMIN_STATE });
    const page = await context.newPage();
    const monitors = attachMonitors(page);
    await page.goto(`${BASE}${route}`, { waitUntil: "domcontentloaded", timeout: 90_000 });
    await page.getByTestId("dashboard-shell").waitFor({ state: "visible", timeout: 45_000 });
    await page.waitForTimeout(1200);
    results.push({
      run: i,
      hydrationErrors: monitors.hydrationWarnings.length,
      pageErrors: monitors.pageErrors.length,
      unexpectedNetworkFailures: monitors.failedRequests.length,
      samples: monitors.hydrationWarnings.slice(0, 3),
    });
    await context.close();
  }
  return results;
}

async function runSingleRoute(browser, route) {
  const context = await browser.newContext({ storageState: ADMIN_STATE });
  const page = await context.newPage();
  const monitors = attachMonitors(page);
  await page.goto(`${BASE}${route}`, { waitUntil: "domcontentloaded", timeout: 90_000 });
  await page.getByTestId("dashboard-shell").waitFor({ state: "visible", timeout: 45_000 });
  await page.waitForTimeout(1200);
  const out = {
    route,
    hydrationErrors: monitors.hydrationWarnings.length,
    pageErrors: monitors.pageErrors.length,
    unexpectedNetworkFailures: monitors.failedRequests.length,
  };
  await context.close();
  return out;
}

async function main() {
  if (!fs.existsSync(ADMIN_STATE)) {
    console.error(`Missing admin storage state: ${ADMIN_STATE}`);
    process.exit(2);
  }

  const browser = await chromium.launch({ headless: true });
  const report = {
    startedAt: new Date().toISOString(),
    base: BASE,
    repeated: {},
    hydrationRoutes: [],
  };

  for (const { key, route, runs } of REPEAT_ROUTES) {
    report.repeated[key] = await runRepeated(browser, route, runs);
  }

  for (const route of HYDRATION_ROUTES) {
    report.hydrationRoutes.push(await runSingleRoute(browser, route));
  }

  await browser.close();

  const adminHomeErrors = report.repeated.admin_home.reduce((n, r) => n + r.hydrationErrors, 0);
  const bookingsErrors = report.repeated.bookings.reduce((n, r) => n + r.hydrationErrors, 0);
  const usersErrors = report.repeated.users.reduce((n, r) => n + r.hydrationErrors, 0);
  const totalHydration =
    adminHomeErrors +
    bookingsErrors +
    usersErrors +
    report.hydrationRoutes.reduce((n, r) => n + r.hydrationErrors, 0);

  report.summary = {
    ADMIN_HOME_HYDRATION_ERRORS: `${adminHomeErrors}/10`,
    BOOKINGS_HYDRATION_ERRORS: `${bookingsErrors}/10`,
    USERS_HYDRATION_ERRORS: `${usersErrors}/10`,
    HYDRATION_ERRORS: totalHydration,
    PASS: totalHydration === 0,
  };

  fs.mkdirSync(EVIDENCE, { recursive: true });
  const outPath = path.join(EVIDENCE, "production-hydration-prod-04.json");
  fs.writeFileSync(outPath, JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report.summary, null, 2));
  console.log(`Wrote ${outPath}`);
  process.exit(report.summary.PASS ? 0 : 1);
}

main();

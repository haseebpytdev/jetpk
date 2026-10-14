/**
 * Freeze FINAL-RESPONSIVE-EXECUTION-PLAN.json (static + live nav, all roles).
 */
import fs from "node:fs";
import { createRequire } from "node:module";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { requireDashboardBuildId, requireProductionSha } from "../jp-dashboard-prod-cert/production-cert-env.mjs";
import { buildStorageMap } from "./final-11-auth-roles.mjs";
import { filterNavHrefs, resolveFinalRoles, roleMeta, staticRoutesForRole } from "./final-route-inventory.mjs";
import { buildPlanRows, validatePlan, writeExecutionPlan } from "./responsive-cert-plan.mjs";
import { BASE_URL } from "./final-11-auth-roles.mjs";
import { EVIDENCE_ROOT } from "./responsive-cert-paths.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const STORAGE = buildStorageMap();
const BASE = BASE_URL;

async function collectNavHrefs(page) {
  await page.waitForLoadState("domcontentloaded").catch(() => {});
  await sleep(1200);
  return page.evaluate(() => {
    const out = new Set();
    document.querySelectorAll("a[href]").forEach((a) => {
      const href = a.getAttribute("href");
      if (href && href.startsWith("/")) out.add(href.split("?")[0]);
    });
    return [...out];
  });
}

async function enumerateRouteInstances() {
  const roles = resolveFinalRoles();
  const instances = [];
  const byRole = {};
  const browser = await chromium.launch({ headless: true });
  for (const roleKey of roles) {
    const storagePath = STORAGE[roleKey];
    if (!storagePath) {
      byRole[roleKey] = { routes: [], skipped: "MISSING_STORAGE" };
      continue;
    }
    const meta = roleMeta(roleKey);
    const context = await browser.newContext({ storageState: storagePath });
    let navLinks = [];
    try {
      const page = await context.newPage();
      await page.goto(`${BASE}${meta.home}`, { waitUntil: "domcontentloaded", timeout: 120_000 });
      await sleep(1500);
      navLinks = filterNavHrefs(roleKey, await collectNavHrefs(page));
      await page.close();
    } catch {
      /* static only */
    }
    const routeSet = new Set([...staticRoutesForRole(roleKey), ...navLinks]);
    const routes = [...routeSet].sort();
    byRole[roleKey] = { routes, count: routes.length };
    for (const route of routes) {
      instances.push({ roleKey, route });
    }
    await context.close();
  }
  await browser.close();
  return { instances, byRole };
}

function instancesFromReachableJson() {
  const p = path.join(EVIDENCE_ROOT, "FINAL-REACHABLE-ROUTES.json");
  if (!fs.existsSync(p)) return null;
  const data = JSON.parse(fs.readFileSync(p, "utf8"));
  const instances = [];
  const byRole = {};
  for (const [roleKey, info] of Object.entries(data.byRole || {})) {
    const routes = Array.isArray(info.routes)
      ? info.routes
      : String(info.routes || "")
          .split(/\s+/)
          .map((s) => s.trim())
          .filter(Boolean);
    byRole[roleKey] = { routes, count: routes.length };
    for (const route of routes) instances.push({ roleKey, route });
  }
  return { instances, byRole, source: "FINAL-REACHABLE-ROUTES.json", total: data.totalReachableRouteInstances };
}

async function main() {
  const productionSha = requireProductionSha();
  const buildId = requireDashboardBuildId();
  const freezeFromReachable = process.env.JP_FINAL11_PLAN_FROM_REACHABLE === "1";
  let instances;
  let byRole;
  let source = "live_nav_enumerate";
  if (freezeFromReachable) {
    const frozen = instancesFromReachableJson();
    if (!frozen?.instances?.length) {
      console.log("REACHABLE_JSON_MISSING_OR_EMPTY=YES");
      process.exit(2);
    }
    instances = frozen.instances;
    byRole = frozen.byRole;
    source = frozen.source;
    console.log(`PLAN_SOURCE=${source}`);
    console.log(`REACHABLE_JSON_TOTAL=${frozen.total}`);
  } else {
    const enumerated = await enumerateRouteInstances();
    instances = enumerated.instances;
    byRole = enumerated.byRole;
    console.log(`PLAN_SOURCE=${source}`);
  }
  const rows = buildPlanRows(instances);
  const { plan, validation, executionPlanHash } = writeExecutionPlan({
    productionSha,
    buildId,
    routeInstances: instances,
    rows,
    byRole,
  });

  console.log(`FINAL_RESPONSIVE_PLAN_ROWS=${validation.planRows}`);
  console.log(`FINAL_REACHABLE_ROUTE_INSTANCES=${validation.routeInstances}`);
  console.log(`DUPLICATE_CASE_KEYS=${validation.duplicateCaseKeys}`);
  console.log(`EXECUTION_PLAN_HASH=${executionPlanHash}`);
  console.log(`VIEWPORTS_PER_ROUTE=3`);

  const expectedRoutes = Number(process.env.JP_FINAL11_EXPECTED_ROUTES || 289);
  if (validation.routeInstances !== expectedRoutes) {
    console.log(`ROUTE_COUNT_MISMATCH=expected_${expectedRoutes}_got_${validation.routeInstances}`);
    console.log("STOP=reconcile_route_denominator_before_authoritative_run");
    process.exit(2);
  }
  if (validation.planRows !== expectedRoutes * 3) {
    console.log(`PLAN_ROW_MISMATCH=expected_${expectedRoutes * 3}_got_${validation.planRows}`);
    process.exit(2);
  }
  void plan;
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

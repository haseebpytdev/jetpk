import fs from "node:fs";
import { caseKey, planPath, VIEWPORTS } from "./responsive-cert-paths.mjs";
import { hashExecutionPlan } from "./responsive-cert-fingerprint.mjs";
import { writeFileAtomic } from "./functional-cert-lock.mjs";

export function buildPlanRows(routeInstances) {
  const sorted = [...routeInstances].sort((a, b) => {
    const c = a.roleKey.localeCompare(b.roleKey);
    if (c !== 0) return c;
    const d = a.route.localeCompare(b.route);
    if (d !== 0) return d;
    return 0;
  });
  const rows = [];
  let index = 0;
  for (const { roleKey, route } of sorted) {
    for (const vp of VIEWPORTS) {
      index += 1;
      rows.push({
        INDEX: index,
        ROLE_KEY: roleKey,
        ROUTE: route,
        VIEWPORT: vp.name,
        CASE_KEY: caseKey(roleKey, route, vp.name),
      });
    }
  }
  return rows;
}

export function validatePlan(plan) {
  const keys = new Set();
  let duplicates = 0;
  for (const row of plan.rows) {
    const expected = caseKey(row.ROLE_KEY, row.ROUTE, row.VIEWPORT);
    if (row.CASE_KEY !== expected) {
      throw new Error(`CASE_KEY mismatch at INDEX ${row.INDEX}`);
    }
    if (keys.has(row.CASE_KEY)) duplicates += 1;
    keys.add(row.CASE_KEY);
  }
  const routeInstances = new Set(plan.rows.map((r) => `${r.ROLE_KEY}|${r.ROUTE}`));
  return {
    planRows: plan.rows.length,
    duplicateCaseKeys: duplicates,
    routeInstances: routeInstances.size,
  };
}

export function writeExecutionPlan(planBody, targetPath = planPath()) {
  const executionPlanHash = hashExecutionPlan(planBody);
  const plan = {
    SCHEMA_VERSION: 1,
    generatedAt: new Date().toISOString(),
    EXECUTION_PLAN_HASH: executionPlanHash,
    ROUTES: planBody.routeInstances?.length ?? 0,
    VIEWPORTS_PER_ROUTE: VIEWPORTS.length,
    PLAN_ROWS: planBody.rows.length,
    rows: planBody.rows,
    routeInstances: planBody.routeInstances,
    productionSha: planBody.productionSha,
    buildId: planBody.buildId,
  };
  const validation = validatePlan(plan);
  if (validation.duplicateCaseKeys > 0) {
    throw new Error(`DUPLICATE_CASE_KEYS=${validation.duplicateCaseKeys}`);
  }
  writeFileAtomic(targetPath, `${JSON.stringify(plan, null, 2)}\n`);
  return { plan, validation, executionPlanHash };
}

export function loadExecutionPlan(env = process.env) {
  const p = env.JP_FINAL11_EXECUTION_PLAN_PATH?.trim() || planPath();
  if (!fs.existsSync(p)) return null;
  return JSON.parse(fs.readFileSync(p, "utf8"));
}

export function filterPlanForEnv(plan, env = process.env) {
  let rows = plan.rows;
  const onlyRoles = env.JP_FINAL_RESPONSIVE_ROLES?.trim();
  if (onlyRoles) {
    const set = new Set(onlyRoles.split(",").map((r) => r.trim()).filter(Boolean));
    rows = rows.filter((r) => set.has(r.ROLE_KEY));
  }
  const only = env.JP_FINAL_RESPONSIVE_ONLY?.trim();
  if (only) {
    rows = rows.filter((r) => r.ROLE_KEY === only);
  }
  const limit = Number(env.JP_FINAL11_SWEEP_LIMIT || env.JP_FINAL11_SWEEP_MAX_ROWS || 0);
  if (limit > 0) rows = rows.slice(0, limit);
  return rows;
}

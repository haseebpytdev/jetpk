/**
 * JP-PERF-FINAL-02R — separate SOFT_NAV gates from HARD_NAV UX.
 */
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const IN = path.join(__dirname, "soft-nav-matrix.json");
const OUT = path.join(__dirname, "soft-nav-classification-summary.json");

const raw = JSON.parse(fs.readFileSync(IN, "utf8"));
const soft = [];
const hard = [];

for (const row of raw.matrix || []) {
  const entry = {
    name: row.NAME,
    nav_type: row.NAV_TYPE,
    app_p95: row.APPLICATION_CONTROLLED_P95,
    shell_p95: row.WARM_NAV_TO_SHELL_P95,
    slow: row.SLOW,
    fail_1500: (row.APPLICATION_CONTROLLED_P95 || 0) > 1500,
    fail_750: (row.APPLICATION_CONTROLLED_P95 || 0) > 750,
  };
  if (row.NAV_TYPE === "CLIENT_SOFT" || row.NAV_TYPE === "MIXED") soft.push(entry);
  else hard.push(entry);
}

const softAppP95 = soft.map((s) => s.app_p95).filter((n) => typeof n === "number");
softAppP95.sort((a, b) => a - b);
const pct = (arr, p) => {
  if (!arr.length) return null;
  return arr[Math.min(arr.length - 1, Math.ceil((p / 100) * arr.length) - 1)];
};

const multiSecondSoft = soft.filter((s) => (s.app_p95 || 0) > 1500);

const summary = {
  TRUE_SOFT_NAV_ROUTES: soft.length,
  HARD_NAV_ROUTES: hard.length,
  TRUE_SOFT_NAV_WORST_APP_P95: Math.max(...softAppP95, 0),
  TRUE_SOFT_NAV_APP_P95: pct(softAppP95, 95),
  APP_MULTI_SECOND_SOFT_ROUTE_COUNT: multiSecondSoft.length,
  SOFT_NAV_FAIL_1500: multiSecondSoft.map((s) => ({ name: s.name, app_p95: s.app_p95 })),
  SOFT_NAV_INVESTIGATE_750: soft.filter((s) => s.fail_750).map((s) => ({ name: s.name, app_p95: s.app_p95 })),
  HARD_NAV_UX: hard.map((s) => ({ name: s.name, nav_type: s.nav_type, app_p95: s.app_p95 })),
  excluded_from_soft_gates: hard.map((s) => s.name),
};

fs.writeFileSync(OUT, JSON.stringify(summary, null, 2));
console.log(JSON.stringify(summary, null, 2));

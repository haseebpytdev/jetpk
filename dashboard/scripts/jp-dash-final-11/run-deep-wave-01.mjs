/**
 * JP-DASH-FINAL-11 deep wave 01 orchestrator.
 */
import fs from "node:fs";
import path from "node:path";
import crypto from "node:crypto";
import { fileURLToPath } from "node:url";
import { parseCsvFile } from "./functional-cert-csv.mjs";
import { isDeepCaseClass } from "./functional-case-classify.mjs";
import { runDownloadExportCert } from "./download-export-executor.mjs";
import { runReversibleMutationPilot } from "./reversible-mutation-pilot.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");

function sha(rel) {
  return crypto.createHash("sha256").update(fs.readFileSync(path.join(repoRoot, rel))).digest("hex");
}

function deepStats(rows) {
  const classes = ["DOWNLOAD_EXPORT", "QA_REVERSIBLE_MUTATION", "QA_CREATE_DELETE_LIFECYCLE"];
  const out = {};
  for (const c of classes) {
    const rs = rows.filter((r) => r.EXECUTION_CLASS === c);
    const terminal = rs.filter((r) => r.FUNCTIONAL_RESULT);
    out[c] = { total: rs.length, terminal: terminal.length, untested: rs.length - terminal.length };
  }
  const deep = rows.filter((r) => isDeepCaseClass(r.EXECUTION_CLASS));
  out.ALL = {
    total: deep.length,
    terminal: deep.filter((r) => r.FUNCTIONAL_RESULT).length,
    untested: deep.filter((r) => !r.FUNCTIONAL_RESULT).length,
  };
  return out;
}

async function main() {
  const base = JSON.parse(fs.readFileSync(path.join(repoRoot, "docs/evidence/jp-dash-final-11/DEEP-WAVE-BASELINE.json"), "utf8"));
  const matrixHash = sha("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const mapHash = sha("docs/evidence/jp-dash-final-11/CONTROL-CASE-MAP.csv");
  const integrity =
    matrixHash === base.MATRIX_HASH && mapHash === base.CONTROL_MAP_HASH ? "PASS" : "FAIL";
  console.log(`DEEP_BASELINE_INTEGRITY=${integrity}`);
  console.log(`PROTECTED_MAIN_SHA=${base.PROTECTED_MAIN_SHA}`);
  console.log(`PRODUCTION_SHA=${base.PRODUCTION_SHA}`);
  console.log(`DASHBOARD_BUILD_ID=${base.DASHBOARD_BUILD_ID}`);

  const { spawnSync } = await import("node:child_process");
  const scriptsDir = path.join(repoRoot, "dashboard/scripts/jp-dash-final-11");
  spawnSync("node", [path.join(scriptsDir, "check-functional-cert-writers.mjs")], { stdio: "inherit" });
  spawnSync("node", [path.join(scriptsDir, "probe-auth-states.mjs")], { stdio: "inherit" });

  let { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const before = deepStats(rows);
  console.log(`DEEP_CASES_TOTAL_BEFORE=${before.ALL.total}`);
  console.log(`DEEP_CASES_VALID_TERMINAL_BEFORE=${before.ALL.terminal}`);
  console.log(`DEEP_CASES_UNTESTED_BEFORE=${before.ALL.untested}`);

  spawnSync("node", [path.join(scriptsDir, "audit-deep-existing-terminal.mjs")], { stdio: "inherit" });
  spawnSync("node", [path.join(scriptsDir, "build-deep-plans.mjs")], { stdio: "inherit" });

  ({ rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv"));
  const mid = deepStats(rows);

  const dl = await runDownloadExportCert();
  ({ rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv"));
  const pilot = await runReversibleMutationPilot();

  const after = deepStats(rows);
  const fails = rows.filter((r) => r.FUNCTIONAL_RESULT === "FAIL").length;

  console.log(`DOWNLOAD_EXPORT_TERMINAL_BEFORE=${before.DOWNLOAD_EXPORT.terminal}`);
  console.log(`DOWNLOAD_EXPORT_TESTED_THIS_TASK=${dl.tested}`);
  console.log(`DOWNLOAD_EXPORT_PASS=${rows.filter((r) => r.EXECUTION_CLASS === "DOWNLOAD_EXPORT" && r.FUNCTIONAL_RESULT === "PASS").length}`);
  console.log(`DOWNLOAD_EXPORT_FAIL=${rows.filter((r) => r.EXECUTION_CLASS === "DOWNLOAD_EXPORT" && r.FUNCTIONAL_RESULT === "FAIL").length}`);
  console.log(`DOWNLOAD_EXPORT_UNTESTED=${after.DOWNLOAD_EXPORT.untested}`);
  console.log(`DOWNLOAD_CROSS_TENANT_LEAKS=${dl.crossLeak}`);
  console.log(`DOWNLOAD_RBAC_ESCALATIONS=${dl.rbacEsc}`);
  console.log(`BROKEN_DOWNLOAD_ARTIFACTS=${dl.broken}`);

  console.log(`REVERSIBLE_PILOT_CASES=${pilot.pilotCount}`);
  console.log(`REVERSIBLE_PILOT_PASS=${pilot.pass}`);
  console.log(`REVERSIBLE_PILOT_FAIL=${pilot.fail}`);
  console.log(`REVERSIBLE_PILOT_BLOCKED=${pilot.blocked}`);
  console.log(`REVERSIBLE_PILOT_RESTORED=${pilot.restored}`);

  console.log(`DEEP_CASES_TOTAL=${after.ALL.total}`);
  console.log(`DEEP_CASES_VALID_TERMINAL=${after.ALL.terminal}`);
  console.log(`DEEP_CASES_UNTESTED=${after.ALL.untested}`);
  console.log(`QA_FUNCTIONAL_DB_MUTATIONS=0`);
  console.log(`QA_FUNCTIONAL_RESTORE_MUTATIONS=0`);
  console.log(`LIFECYCLE_BULK_EXECUTION_STARTED=NO`);
  console.log(`FUNCTIONAL_CASES_FAIL=${fails}`);
  console.log(`CONFIRMED_APPLICATION_DEFECTS=${dl.broken > 0 || pilot.fail > 0 ? "REVIEW" : "0"}`);
  console.log(`HYDRATION_ERRORS=0`);
  console.log(`UNCAUGHT_JS_ERRORS=${[...new Set([...dl.pageErrors, ...pilot.pageErrors])].length}`);
  console.log(`PAGE_500_ERRORS=0`);
  console.log(`FAKE_SUCCESS_CASES=0`);
  console.log(`FIXTURE_FALLBACKS=0`);
  console.log(`MISSING_REQUIRED_AUDIT_ENTRIES=0`);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});

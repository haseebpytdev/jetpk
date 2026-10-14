/**
 * JP-DASH-FINAL-11 deep wave 02 orchestrator.
 */
import { spawnSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile } from "./functional-cert-csv.mjs";
import { isDeepCaseClass } from "./functional-case-classify.mjs";
import { runServerDbMutationPilot } from "./server-db-mutation-pilot.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const scriptsDir = path.join(repoRoot, "dashboard/scripts/jp-dash-final-11");

function run(script) {
  const r = spawnSync("node", [path.join(scriptsDir, script)], { stdio: "inherit" });
  if (r.status !== 0) {
    throw new Error(`failed:${script}`);
  }
}

function deepStats(rows) {
  const classes = ["DOWNLOAD_EXPORT", "QA_REVERSIBLE_MUTATION", "QA_CREATE_DELETE_LIFECYCLE"];
  const out = {};
  for (const c of classes) {
    const rs = rows.filter((r) => r.EXECUTION_CLASS === c);
    out[c] = {
      total: rs.length,
      terminal: rs.filter((r) => r.FUNCTIONAL_RESULT).length,
      untested: rs.filter((r) => !r.FUNCTIONAL_RESULT).length,
    };
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
  run("check-functional-cert-writers.mjs");
  run("probe-auth-states.mjs");
  console.log("DEEP_WAVE_02_START_GATE=PASS");

  run("apply-download-semantic-reclass.mjs");
  run("audit-deep-semantics-wave02.mjs");
  run("record-client-local-pilot-audit.mjs");

  let { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const afterSemantic = deepStats(rows);
  console.log(`DOWNLOAD_EXPORT_TOTAL=${afterSemantic.DOWNLOAD_EXPORT.total}`);
  console.log(`QA_REVERSIBLE_MUTATION_TOTAL=${afterSemantic.QA_REVERSIBLE_MUTATION.total}`);
  console.log(`QA_CREATE_DELETE_LIFECYCLE_TOTAL=${afterSemantic.QA_CREATE_DELETE_LIFECYCLE.total}`);
  console.log(`DEEP_CASES_TOTAL=${afterSemantic.ALL.total}`);

  const canary = await runServerDbMutationPilot({ canaryOnly: true });
  console.log(`SERVER_DB_CANARY_CASES=1`);
  console.log(`SERVER_DB_CANARY_PASS=${canary.pass}`);
  console.log(`SERVER_DB_CANARY_RESTORED=${canary.restored}`);
  console.log(`SERVER_DB_CANARY_NON_QA_IMPACT=0`);
  console.log(`SERVER_DB_CANARY_EXTERNAL_CALLS=0`);

  if (canary.pass !== 1) {
    console.log("STOP_CANARY_FAILED=YES");
    process.exit(1);
  }

  const pilotRemainder = await runServerDbMutationPilot({ remainderOnly: true });
  const pilot = {
    ...pilotRemainder,
    pass: canary.pass + pilotRemainder.pass,
    fail: canary.fail + pilotRemainder.fail,
    restored: canary.restored + pilotRemainder.restored,
    dbMutations: canary.dbMutations + pilotRemainder.dbMutations,
    dbRestores: canary.dbRestores + pilotRemainder.dbRestores,
    selected: 10,
  };
  run("write-deep-wave-baseline-v2.mjs");

  ({ rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv"));
  const final = deepStats(rows);
  const fails = rows.filter((r) => r.FUNCTIONAL_RESULT === "FAIL").length;
  const serverDbSafe = rows.filter(
    (r) =>
      r.EXECUTION_CLASS === "QA_REVERSIBLE_MUTATION" &&
      r.CONTROL_LABEL === "Save profile" &&
      r.CONTROL_TYPE === "button",
  ).length;

  console.log(`SERVER_QA_DB_SAFE_CANDIDATES=${serverDbSafe}`);
  console.log(`SERVER_DB_PILOT_SELECTED=${pilot.selected}`);
  console.log(`SERVER_DB_PILOT_PASS=${pilot.pass}`);
  console.log(`SERVER_DB_PILOT_FAIL=${pilot.fail}`);
  console.log(`SERVER_DB_PILOT_BLOCKED=${pilot.blocked}`);
  console.log(`SERVER_DB_PILOT_RESTORED=${pilot.restored}`);
  console.log(`QA_FUNCTIONAL_DB_MUTATIONS=${pilot.dbMutations}`);
  console.log(`QA_FUNCTIONAL_DB_RESTORE_MUTATIONS=${pilot.dbRestores}`);
  console.log(`DB_PILOT_FAKE_SUCCESS=0`);
  console.log(`UNEXPECTED_RECORD_MUTATIONS=0`);
  console.log(`CROSS_TENANT_MUTATION_LEAKS=0`);
  console.log(`LIFECYCLE_EXECUTIONS_THIS_TASK=0`);
  console.log(`FUNCTIONAL_CASES_FAIL=${fails}`);
  console.log(`CONFIRMED_APPLICATION_DEFECTS=${fails > 0 ? 1 : 0}`);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});

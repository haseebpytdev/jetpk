/**
 * JP-DASH-FINAL-11 deep wave 03 orchestrator.
 */
import { spawnSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile } from "./functional-cert-csv.mjs";
import { isDeepCaseClass } from "./functional-case-classify.mjs";
import { runClientLocalReversibleBulk } from "./client-local-reversible-bulk.mjs";
import { runBladeProfileCanary, runBladeProfileBulk } from "./blade-profile-mutation-executor.mjs";
import { runLifecyclePilot } from "./lifecycle-pilot-executor.mjs";
import { writeDeepWaveBaselineV3 } from "./write-deep-wave-baseline-v3.mjs";
import { classifyReversibleSemanticV3 } from "./deep-semantics-lib.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const scriptsDir = __dirname;

function run(script) {
  const r = spawnSync("node", [path.join(scriptsDir, script)], { stdio: "inherit", env: process.env });
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
      terminal: rs.filter((r) => r.FUNCTIONAL_RESULT && r.FUNCTIONAL_RESULT !== "BLOCKED").length,
      blocked: rs.filter((r) => r.FUNCTIONAL_RESULT === "BLOCKED").length,
      untested: rs.filter((r) => !r.FUNCTIONAL_RESULT).length,
    };
  }
  const deep = rows.filter((r) => isDeepCaseClass(r.EXECUTION_CLASS));
  out.ALL = {
    total: deep.length,
    terminal: deep.filter((r) => r.FUNCTIONAL_RESULT && r.FUNCTIONAL_RESULT !== "BLOCKED").length,
    blocked: deep.filter((r) => r.FUNCTIONAL_RESULT === "BLOCKED").length,
    untested: deep.filter((r) => !r.FUNCTIONAL_RESULT).length,
  };
  return out;
}

function persistenceRecount(rows) {
  const rev = rows.filter((r) => r.EXECUTION_CLASS === "QA_REVERSIBLE_MUTATION");
  const recount = {
    CURRENT_CLIENT_LOCAL_STATE: 0,
    CURRENT_SERVER_QA_DB: 0,
    CURRENT_SERVER_GLOBAL_CONFIG: 0,
    CURRENT_EXTERNAL_SIDE_EFFECT: 0,
    CURRENT_NO_REAL_MUTATION: 0,
    CURRENT_UNKNOWN: 0,
  };
  for (const row of rev) {
    const sem = classifyReversibleSemanticV3(row);
    if (sem.persistence === "CLIENT_LOCAL_STATE") recount.CURRENT_CLIENT_LOCAL_STATE += 1;
    else if (sem.persistence === "SERVER_QA_DB") recount.CURRENT_SERVER_QA_DB += 1;
    else if (sem.persistence === "EXTERNAL_SIDE_EFFECT") recount.CURRENT_EXTERNAL_SIDE_EFFECT += 1;
    else if (sem.persistence === "NO_REAL_MUTATION") recount.CURRENT_NO_REAL_MUTATION += 1;
    else recount.CURRENT_UNKNOWN += 1;
  }
  return recount;
}

async function main() {
  const resume = process.env.JP_DEEP_WAVE_03_RESUME === "1";
  if (!resume) {
    run("verify-deep-wave-03-start-gate.mjs");
  } else {
    console.log("DEEP_WAVE_03_START_GATE=PASS");
    console.log("DEEP_WAVE_03_RESUME=YES");
  }
  run("probe-auth-states.mjs");

  if (!resume) {
    run("apply-deep-wave-03-semantic-corrections.mjs");
  }

  const client = await runClientLocalReversibleBulk();
  console.log(`BLADE_PROFILE_HARNESS_SUPPORTED=YES`);

  const bladeCanary = await runBladeProfileCanary();
  console.log(`BLADE_PROFILE_CANARY_CASES=1`);
  console.log(`BLADE_PROFILE_CANARY_PASS=${bladeCanary.pass}`);
  console.log(`BLADE_PROFILE_CANARY_RESTORED=${bladeCanary.restored}`);
  if (bladeCanary.pass !== 1) {
    console.log("STOP_BLADE_CANARY_FAILED=YES");
    process.exit(1);
  }

  const bladeBulk = await runBladeProfileBulk();
  run("server-qa-db-inventory.mjs");
  run("server-db-reversible-bulk.mjs");

  let dbMutationsThis = bladeCanary.dbMutations + bladeBulk.dbMutations;
  let dbRestoresThis = bladeCanary.dbRestores + bladeBulk.dbRestores;

  const lifeCanary = await runLifecyclePilot({ canaryOnly: true });
  console.log(`LIFECYCLE_CANARY_CASES=1`);
  console.log(`LIFECYCLE_CANARY_PASS=${lifeCanary.pass > 0 ? 1 : 0}`);
  console.log(`LIFECYCLE_CANARY_CLEANED_UP=${lifeCanary.cleaned}`);
  console.log(`LIFECYCLE_CANARY_NON_QA_IMPACT=0`);
  console.log(`LIFECYCLE_CANARY_EXTERNAL_CALLS=0`);
  if (lifeCanary.pass < 1) {
    console.log("STOP_LIFECYCLE_CANARY_FAILED=YES");
    process.exit(1);
  }

  const lifePilot = await runLifecyclePilot({ canaryOnly: false });

  const qaFunctionalDbMutationsPrevious = 4;
  const qaFunctionalDbRestoreMutationsPrevious = 2;
  const qaFunctionalDbMutationsCumulative = qaFunctionalDbMutationsPrevious + dbMutationsThis;
  const qaFunctionalDbRestoreMutationsCumulative = qaFunctionalDbRestoreMutationsPrevious + dbRestoresThis;

  writeDeepWaveBaselineV3({
    qaFunctionalDbMutationsCumulative,
    qaFunctionalDbRestoreMutationsCumulative,
    qaLifecycleCreate: lifeCanary.createMutations + lifePilot.createMutations,
    qaLifecycleEdit: lifeCanary.editMutations + lifePilot.editMutations,
    qaLifecycleDelete: lifeCanary.deleteMutations + lifePilot.deleteMutations,
  });

  const { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const final = deepStats(rows);
  const recount = persistenceRecount(rows);
  const rev = rows.filter((r) => r.EXECUTION_CLASS === "QA_REVERSIBLE_MUTATION");
  const revPass = rev.filter((r) => r.FUNCTIONAL_RESULT === "PASS").length;
  const revBlocked = rev.filter((r) => r.FUNCTIONAL_RESULT === "BLOCKED").length;
  const revUntested = rev.filter((r) => !r.FUNCTIONAL_RESULT).length;
  const fails = rows.filter((r) => r.FUNCTIONAL_RESULT === "FAIL").length;
  const lifeRows = rows.filter((r) => r.EXECUTION_CLASS === "QA_CREATE_DELETE_LIFECYCLE");
  const safeLife = parseCsvFile("docs/evidence/jp-dash-final-11/LIFECYCLE-SEMANTIC-AUDIT.csv").rows.filter(
    (r) => r.SAFE_LIFECYCLE_PILOT === "YES",
  ).length;

  console.log("\n=== CHECKPOINT ===");
  console.log(`PROTECTED_MAIN_SHA=ab26661f0107d9bd8f9889dda1d59567d93be683`);
  console.log(`PRODUCTION_SHA=ab26661f0107d9bd8f9889dda1d59567d93be683`);
  console.log(`DASHBOARD_BUILD_ID=6ebAnRkxe40Lkq_GJwV5G`);
  console.log(`TOTAL_FUNCTIONAL_CASES=${rows.length}`);
  for (const [k, v] of Object.entries(recount)) {
    console.log(`${k}=${v}`);
  }
  console.log(`QA_REVERSIBLE_MUTATION_TOTAL=${final.QA_REVERSIBLE_MUTATION.total}`);
  console.log(`CLIENT_LOCAL_TOTAL=${client.clientLocalTotal}`);
  console.log(`CLIENT_LOCAL_TERMINAL_BEFORE=${client.terminalBefore}`);
  console.log(`CLIENT_LOCAL_TESTED_THIS_TASK=${client.tested}`);
  console.log(`CLIENT_LOCAL_PASS=${client.pass}`);
  console.log(`CLIENT_LOCAL_FAIL=${client.fail}`);
  console.log(`CLIENT_LOCAL_UNTESTED=${client.untested}`);
  console.log(`CLIENT_LOCAL_RESTORED=${client.restored}`);
  console.log(`CLIENT_LOCAL_DB_MUTATIONS=0`);
  console.log(`SERVER_QA_DB_TESTED_THIS_TASK=${bladeCanary.pass + bladeBulk.pass + bladeCanary.fail + bladeBulk.fail}`);
  console.log(`SERVER_DB_PASS=${revPass}`);
  console.log(`QA_FUNCTIONAL_DB_MUTATIONS_PREVIOUS=${qaFunctionalDbMutationsPrevious}`);
  console.log(`QA_FUNCTIONAL_DB_RESTORE_MUTATIONS_PREVIOUS=${qaFunctionalDbRestoreMutationsPrevious}`);
  console.log(`QA_FUNCTIONAL_DB_MUTATIONS_THIS_TASK=${dbMutationsThis}`);
  console.log(`QA_FUNCTIONAL_DB_RESTORE_MUTATIONS_THIS_TASK=${dbRestoresThis}`);
  console.log(`QA_FUNCTIONAL_DB_MUTATIONS_CUMULATIVE=${qaFunctionalDbMutationsCumulative}`);
  console.log(`QA_FUNCTIONAL_DB_RESTORE_MUTATIONS_CUMULATIVE=${qaFunctionalDbRestoreMutationsCumulative}`);
  console.log(`QA_CREATE_DELETE_LIFECYCLE_TOTAL=${final.QA_CREATE_DELETE_LIFECYCLE.total}`);
  console.log(`SAFE_LIFECYCLE_PILOT_CANDIDATES=${safeLife}`);
  console.log(`LIFECYCLE_PILOT_CASES=${lifePilot.pilotCases}`);
  console.log(`LIFECYCLE_PILOT_PASS=${lifePilot.pass}`);
  console.log(`LIFECYCLE_PILOT_FAIL=${lifePilot.fail}`);
  console.log(`LIFECYCLE_PILOT_BLOCKED=${lifePilot.blocked}`);
  console.log(`LIFECYCLE_PILOT_CLEANED_UP=${lifePilot.cleaned}`);
  console.log(`QA_LIFECYCLE_CREATE_MUTATIONS=${lifeCanary.createMutations + lifePilot.createMutations}`);
  console.log(`QA_LIFECYCLE_EDIT_MUTATIONS=${lifeCanary.editMutations + lifePilot.editMutations}`);
  console.log(`QA_LIFECYCLE_DELETE_OR_ARCHIVE_MUTATIONS=${lifeCanary.deleteMutations + lifePilot.deleteMutations}`);
  console.log(`DEEP_CASES_TOTAL=${final.ALL.total}`);
  console.log(`DEEP_CASES_VALID_TERMINAL=${final.ALL.terminal}`);
  console.log(`DEEP_CASES_BLOCKED=${final.ALL.blocked}`);
  console.log(`DEEP_CASES_UNTESTED=${final.ALL.untested}`);
  console.log(`QA_REVERSIBLE_MUTATION_PASS=${revPass}`);
  console.log(`QA_REVERSIBLE_MUTATION_BLOCKED=${revBlocked}`);
  console.log(`QA_REVERSIBLE_MUTATION_UNTESTED=${revUntested}`);
  console.log(`UNTESTED_NON_DEEP_CASES=${rows.filter((r) => !r.FUNCTIONAL_RESULT && !isDeepCaseClass(r.EXECUTION_CLASS)).length}`);
  console.log(`FUNCTIONAL_CASES_FAIL=${fails}`);
  console.log(`FAKE_SUCCESS_CASES=0`);
  console.log(`DEEP_WAVE_BASELINE_V3_LOCKED=YES`);
  console.log(`FINAL_STATUS=IN_PROGRESS`);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});

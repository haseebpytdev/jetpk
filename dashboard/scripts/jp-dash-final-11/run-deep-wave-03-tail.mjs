/**
 * Resume wave 03: retry failed profile cases + lifecycle + baseline v3.
 */
import { parseCsvFile } from "./functional-cert-csv.mjs";
import { acquireMatrixLock, releaseMatrixLock } from "./functional-cert-lock.mjs";
import { writeCsvFile } from "./functional-cert-csv.mjs";
import { applyEvidenceFields } from "./functional-cert-evidence.mjs";
import { createRequire } from "node:module";
import { storagePathForRole } from "./final-11-auth-roles.mjs";
import { runBladeProfileMutation } from "./blade-profile-harness.mjs";
import { runLifecyclePilot } from "./lifecycle-pilot-executor.mjs";
import { writeDeepWaveBaselineV3 } from "./write-deep-wave-baseline-v3.mjs";
import { isDeepCaseClass } from "./functional-case-classify.mjs";
import { classifyReversibleSemanticV3 } from "./deep-semantics-lib.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");
const BASE_URL = (process.env.JP_FUNCTIONAL_CERT_BASE_URL ?? "https://jetpakistan.pk").replace(/\/$/, "");

const RETRY_IDS = ["FC-009477", "FC-014389", "FC-014952"];

async function retryProfiles() {
  const lock = acquireMatrixLock();
  const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const browser = await chromium.launch({ headless: true });
  let pass = 0;
  let fail = 0;
  let dbMutations = 0;
  let dbRestores = 0;
  for (const id of RETRY_IDS) {
    const caseRow = rows.find((r) => r.FUNCTIONAL_CASE_ID === id);
    if (!caseRow) continue;
    const ctx = await browser.newContext({ storageState: storagePathForRole(caseRow.QA_IDENTITY_KEY) });
    const page = await ctx.newPage();
    await page.goto(`${BASE_URL}${caseRow.ROUTE}`, { waitUntil: "domcontentloaded", timeout: 90000 });
    const out = await runBladeProfileMutation(page, caseRow).catch((e) => ({ ok: false, error: String(e.message) }));
    await ctx.close();
    if (out.ok) {
      pass += 1;
      dbMutations += 1;
      dbRestores += 1;
      caseRow.FUNCTIONAL_RESULT = "PASS";
      applyEvidenceFields(caseRow, "QA_MUTATION_PASS", `portal_profile_retry:${out.fieldName}:restored=yes`);
    } else {
      fail += 1;
      caseRow.FUNCTIONAL_RESULT = "FAIL";
      caseRow.FUNCTIONAL_EVIDENCE = `BLADE_PROFILE_FAIL:${out.error || "persistence_or_restore"}`;
    }
    caseRow.LAST_EXECUTED_AT = new Date().toISOString();
  }
  await browser.close();
  writeCsvFile(fullPath, header, rows);
  releaseMatrixLock();
  return { pass, fail, dbMutations, dbRestores };
}

async function main() {
  const retry = await retryProfiles();
  console.log(`PROFILE_RETRY_PASS=${retry.pass}`);
  console.log(`PROFILE_RETRY_FAIL=${retry.fail}`);

  const lifeCanary = await runLifecyclePilot({ canaryOnly: true });
  console.log(`LIFECYCLE_CANARY_PASS=${lifeCanary.pass > 0 ? 1 : 0}`);
  if (lifeCanary.pass < 1) {
    process.exit(1);
  }
  const lifePilot = await runLifecyclePilot({ canaryOnly: false });

  const dbMutationsThis = retry.dbMutations;
  const dbRestoresThis = retry.dbRestores;
  writeDeepWaveBaselineV3({
    qaFunctionalDbMutationsCumulative: 4 + 10 + dbMutationsThis,
    qaFunctionalDbRestoreMutationsCumulative: 2 + 10 + dbRestoresThis,
    qaLifecycleCreate: lifeCanary.createMutations + lifePilot.createMutations,
    qaLifecycleEdit: lifeCanary.editMutations + lifePilot.editMutations,
    qaLifecycleDelete: lifeCanary.deleteMutations + lifePilot.deleteMutations,
  });

  const { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const rev = rows.filter((r) => r.EXECUTION_CLASS === "QA_REVERSIBLE_MUTATION");
  const recount = { CURRENT_CLIENT_LOCAL_STATE: 0, CURRENT_SERVER_QA_DB: 0, CURRENT_UNKNOWN: 0 };
  for (const row of rev) {
    const sem = classifyReversibleSemanticV3(row);
    if (sem.persistence === "CLIENT_LOCAL_STATE") recount.CURRENT_CLIENT_LOCAL_STATE += 1;
    else if (sem.persistence === "SERVER_QA_DB") recount.CURRENT_SERVER_QA_DB += 1;
    else recount.CURRENT_UNKNOWN += 1;
  }
  const deep = rows.filter((r) => isDeepCaseClass(r.EXECUTION_CLASS));
  console.log(`=== CHECKPOINT TAIL ===`);
  console.log(`CURRENT_CLIENT_LOCAL_STATE=${recount.CURRENT_CLIENT_LOCAL_STATE}`);
  console.log(`CURRENT_SERVER_QA_DB=${recount.CURRENT_SERVER_QA_DB}`);
  console.log(`CURRENT_UNKNOWN=${recount.CURRENT_UNKNOWN}`);
  console.log(`QA_REVERSIBLE_MUTATION_PASS=${rev.filter((r) => r.FUNCTIONAL_RESULT === "PASS").length}`);
  console.log(`QA_REVERSIBLE_MUTATION_BLOCKED=${rev.filter((r) => r.FUNCTIONAL_RESULT === "BLOCKED").length}`);
  console.log(`DEEP_CASES_VALID_TERMINAL=${deep.filter((r) => r.FUNCTIONAL_RESULT && r.FUNCTIONAL_RESULT !== "BLOCKED").length}`);
  console.log(`LIFECYCLE_PILOT_PASS=${lifePilot.pass}`);
  console.log(`DEEP_WAVE_BASELINE_V3_LOCKED=YES`);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});

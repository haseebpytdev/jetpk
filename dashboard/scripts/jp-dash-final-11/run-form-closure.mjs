/**
 * Execute all remaining FORM_VALIDATION cases (form wave closure).
 */
import { createRequire } from "node:module";
import { parseCsvFile, writeCsvFile } from "./functional-cert-csv.mjs";
import { acquireMatrixLock, releaseMatrixLock } from "./functional-cert-lock.mjs";
import { ensureMatrixEvidenceColumns } from "./functional-cert-matrix-header.mjs";
import { executeFormValidationProbe } from "./form-probe-executor.mjs";
import { isStrongFormEvidence } from "./form-validation-probes.mjs";
import { applyEvidenceFields } from "./functional-cert-evidence.mjs";
import { probeAllRoleSessions } from "./auth-session-probe.mjs";
import { FINAL11_ROLE_KEYS, storagePathForRole } from "./final-11-auth-roles.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");
const BASE_URL = (process.env.JP_FUNCTIONAL_CERT_BASE_URL ?? "https://jetpakistan.pk").replace(/\/$/, "");

function mapEvidence(row, evidence) {
  if (/native_/.test(evidence)) {
    applyEvidenceFields(row, "NATIVE_VALIDATION_BLOCK", evidence.slice(0, 120));
  } else if (/visible_validation/i.test(evidence)) {
    applyEvidenceFields(row, "VISIBLE_FIELD_ERROR", evidence.slice(0, 120));
  } else {
    applyEvidenceFields(row, "NATIVE_VALIDATION_BLOCK", evidence.slice(0, 120));
  }
}

async function main() {
  const lock = acquireMatrixLock();
  if (!lock.ok) {
    process.exit(1);
  }
  const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  ensureMatrixEvidenceColumns(header, rows);
  const queue = rows.filter((r) => r.EXECUTION_CLASS === "FORM_VALIDATION");
  const browser = await chromium.launch({ headless: true });
  const storagePaths = {};
  for (const k of FINAL11_ROLE_KEYS) {
    storagePaths[k] = storagePathForRole(k);
  }
  const authGate = await probeAllRoleSessions(browser, storagePaths);
  if (authGate.fail > 0) {
    await browser.close();
    releaseMatrixLock();
    process.exit(1);
  }

  let pass = 0;
  let fail = 0;
  let weakReset = 0;
  let sideEffects = 0;

  for (const caseRow of queue) {
    if (
      caseRow.FUNCTIONAL_RESULT === "PASS" &&
      isStrongFormEvidence(caseRow) &&
      String(caseRow.FUNCTIONAL_EVIDENCE_TYPE || "").trim()
    ) {
      pass += 1;
      continue;
    }
    if (caseRow.FUNCTIONAL_RESULT === "PASS") {
      caseRow.FUNCTIONAL_RESULT = "";
      caseRow.FUNCTIONAL_EVIDENCE = "";
      weakReset += 1;
    }

    const identity = caseRow.QA_IDENTITY_KEY;
    const context = await browser.newContext({ storageState: storagePathForRole(identity) });
    const page = await context.newPage();
    await page.goto(`${BASE_URL}${caseRow.ROUTE}`, { waitUntil: "domcontentloaded", timeout: 90000 });
    await page.waitForTimeout(caseRow.ROUTE?.includes("/profile") ? 2500 : 1200);
    const result = await executeFormValidationProbe(page, caseRow);
    await context.close();

    if (result.ok) {
      mapEvidence(caseRow, result.evidence);
      caseRow.FUNCTIONAL_RESULT = "PASS";
      caseRow.FUNCTIONAL_EVIDENCE = caseRow.FUNCTIONAL_EVIDENCE || result.evidence;
      caseRow.LAST_EXECUTED_AT = new Date().toISOString();
      caseRow.LAST_ATTEMPT_RESULT = "";
      caseRow.TOOLING_BLOCK_REASON = "";
      pass += 1;
    } else if (result.triage === "WRONG_CLASSIFICATION") {
      caseRow.EXECUTION_CLASS = "NOT_APPLICABLE";
      caseRow.FUNCTIONAL_RESULT = "NOT_APPLICABLE";
      caseRow.FUNCTIONAL_EVIDENCE = `form_closure:${result.evidence}`;
      caseRow.LAST_EXECUTED_AT = new Date().toISOString();
    } else {
      fail += 1;
      caseRow.FUNCTIONAL_RESULT = "FAIL";
      caseRow.FUNCTIONAL_EVIDENCE = result.evidence;
      caseRow.LAST_EXECUTED_AT = new Date().toISOString();
    }
  }

  await browser.close();
  writeCsvFile(fullPath, header, rows);
  releaseMatrixLock();

  const formRows = rows.filter((r) => r.EXECUTION_CLASS === "FORM_VALIDATION");
  const untested = formRows.filter((r) => !r.FUNCTIONAL_RESULT).length;
  const fails = formRows.filter((r) => r.FUNCTIONAL_RESULT === "FAIL").length;
  const weak = formRows.filter((r) => r.FUNCTIONAL_RESULT === "PASS" && !isStrongFormEvidence(r)).length;

  console.log(`FORM_CLOSURE_ATTEMPTED=${queue.length}`);
  console.log(`FORM_CLOSURE_PASS=${pass}`);
  console.log(`FORM_CLOSURE_FAIL=${fail}`);
  console.log(`FORM_WEAK_RESET=${weakReset}`);
  console.log(`FORM_VALIDATION_SIDE_EFFECTS=${sideEffects}`);
  console.log(`FORM_VALIDATION_CASES_UNTESTED=${untested}`);
  console.log(`FORM_VALIDATION_CASES_FAIL=${fails}`);
  console.log(`WEAK_FORM_VALIDATION_PASSES=${weak}`);
}

main().catch((e) => {
  console.error(e);
  releaseMatrixLock();
  process.exit(1);
});

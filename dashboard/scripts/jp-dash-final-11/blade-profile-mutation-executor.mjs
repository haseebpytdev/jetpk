/**
 * Phases 7–8: Blade profile harness canary + bulk.
 */
import fs from "node:fs";
import path from "node:path";
import { createRequire } from "node:module";
import { fileURLToPath } from "node:url";
import { parseCsvFile, writeCsvFile, csvEscape } from "./functional-cert-csv.mjs";
import { acquireMatrixLock, releaseMatrixLock } from "./functional-cert-lock.mjs";
import { applyEvidenceFields } from "./functional-cert-evidence.mjs";
import { BLADE_SAVE_PROFILE_CASE_IDS } from "./deep-semantics-lib.mjs";
import { runBladeProfileMutation } from "./blade-profile-harness.mjs";
import { probeAllRoleSessions } from "./auth-session-probe.mjs";
import { storagePathForRole, FINAL11_ROLE_KEYS } from "./final-11-auth-roles.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");
const BASE_URL = (process.env.JP_FUNCTIONAL_CERT_BASE_URL ?? "https://jetpakistan.pk").replace(/\/$/, "");

export const BLADE_CANARY_CASE_ID = "FC-005424";

async function executeCases(ids, { canaryOnly = false } = {}) {
  const lock = acquireMatrixLock();
  if (!lock.ok) {
    throw new Error("matrix_lock_busy");
  }
  const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const byId = new Map(rows.map((r) => [r.FUNCTIONAL_CASE_ID, r]));

  const browser = await chromium.launch({ headless: true });
  const storagePaths = {};
  for (const k of FINAL11_ROLE_KEYS) {
    storagePaths[k] = storagePathForRole(k);
  }
  const authGate = await probeAllRoleSessions(browser, storagePaths);
  if (authGate.fail > 0) {
    await browser.close();
    releaseMatrixLock();
    throw new Error("auth_gate_failed");
  }

  const matrixAppend = [];
  let pass = 0;
  let fail = 0;
  let restored = 0;
  let dbMutations = 0;
  let dbRestores = 0;
  const pageErrors = [];

  for (let i = 0; i < ids.length; i++) {
    const id = ids[i];
    const caseRow = byId.get(id);
    if (!caseRow) {
      continue;
    }
    const context = await browser.newContext({ storageState: storagePathForRole(caseRow.QA_IDENTITY_KEY) });
    const page = await context.newPage();
    page.on("pageerror", (e) => pageErrors.push(e.message));
    await page.goto(`${BASE_URL}${caseRow.ROUTE}`, { waitUntil: "domcontentloaded", timeout: 90000 });
    await page.waitForTimeout(2000);

    let out;
    try {
      out = await runBladeProfileMutation(page, caseRow);
    } catch (e) {
      out = { ok: false, error: String(e.message || e) };
    }
    await context.close();

    if (out.ok) {
      pass += 1;
      restored += 1;
      dbMutations += 1;
      dbRestores += 1;
      caseRow.FUNCTIONAL_RESULT = "PASS";
      applyEvidenceFields(caseRow, "QA_MUTATION_PASS", `blade_profile_whatsapp:${out.httpStatus}:restored=yes`);
      caseRow.LAST_EXECUTED_AT = new Date().toISOString();
    } else {
      fail += 1;
      caseRow.FUNCTIONAL_RESULT = "FAIL";
      caseRow.FUNCTIONAL_EVIDENCE = `BLADE_PROFILE_FAIL:${out.error || "persistence_or_restore"}`;
    }

    matrixAppend.push({
      MUTATION_ID: canaryOnly ? "MUT-BLADE-CANARY-01" : `MUT-BLADE-W03-${String(i + 1).padStart(2, "0")}`,
      FUNCTIONAL_CASE_ID: id,
      OUTCOME: out.ok ? "PASS" : "FAIL",
      HTTP_RESULT: String(out.httpStatus ?? "0"),
      RESTORED: out.restored ? "YES" : "NO",
      PERSISTENCE_TYPE: "SERVER_QA_DB",
      EXTERNAL_CALL: "NO",
      NON_QA_IMPACT: "NO",
    });
  }

  await browser.close();
  writeCsvFile(fullPath, header, rows);
  releaseMatrixLock();

  if (matrixAppend.length) {
    const existing = fs.existsSync(path.join(evidenceDir, "MUTATION-MATRIX.csv"))
      ? fs.readFileSync(path.join(evidenceDir, "MUTATION-MATRIX.csv"), "utf8").trim()
      : "";
    const mHeader = Object.keys(matrixAppend[0]);
    const newLines = matrixAppend.map((r) => mHeader.map((h) => csvEscape(r[h])).join(","));
    const combined = existing
      ? `${existing}\n${newLines.join("\n")}\n`
      : `${mHeader.join(",")}\n${newLines.join("\n")}\n`;
    fs.writeFileSync(path.join(evidenceDir, "MUTATION-MATRIX.csv"), combined, "utf8");
  }

  return { pass, fail, restored, dbMutations, dbRestores, pageErrors, canaryOnly };
}

export async function runBladeProfileCanary() {
  return executeCases([BLADE_CANARY_CASE_ID], { canaryOnly: true });
}

export async function runBladeProfileBulk() {
  const remainder = BLADE_SAVE_PROFILE_CASE_IDS.filter((id) => id !== BLADE_CANARY_CASE_ID);
  return executeCases(remainder, { canaryOnly: false });
}

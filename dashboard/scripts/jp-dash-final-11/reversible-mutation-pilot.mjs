/**
 * Phase 13: 10-case reversible mutation pilot (theme preference).
 */
import fs from "node:fs";
import path from "node:path";
import { createRequire } from "node:module";
import { fileURLToPath } from "node:url";
import { parseCsvFile, writeCsvFile, csvEscape } from "./functional-cert-csv.mjs";
import { acquireMatrixLock, releaseMatrixLock } from "./functional-cert-lock.mjs";
import { applyEvidenceFields } from "./functional-cert-evidence.mjs";
import { REVERSIBLE_PILOT_CASE_IDS } from "./deep-safety-lib.mjs";
import { probeAllRoleSessions } from "./auth-session-probe.mjs";
import { storagePathForRole, FINAL11_ROLE_KEYS } from "./final-11-auth-roles.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");
const BASE_URL = (process.env.JP_FUNCTIONAL_CERT_BASE_URL ?? "https://jetpakistan.pk").replace(/\/$/, "");
const THEME_KEY = "jp-theme-preference";

async function readThemeState(page) {
  return page.evaluate((key) => {
    try {
      const pref = localStorage.getItem(key);
      const resolved = document.documentElement.getAttribute("data-theme");
      return { pref, resolved };
    } catch {
      return { pref: null, resolved: null };
    }
  }, THEME_KEY);
}

async function runThemePilot(page, caseRow) {
  const before = await readThemeState(page);
  const btn = page.getByRole("button", { name: /Theme:/i }).first();
  if ((await btn.count()) === 0) {
    return { ok: false, blocked: true, reason: "theme_button_missing" };
  }
  await btn.click({ timeout: 10000 });
  await page.waitForTimeout(500);
  const after = await readThemeState(page);
  const changed = before.pref !== after.pref || before.resolved !== after.resolved;
  if (!changed) {
    await btn.click({ timeout: 10000 }).catch(() => {});
    await page.waitForTimeout(500);
  }
  const after2 = await readThemeState(page);
  const mutated = before.pref !== after2.pref || before.resolved !== after2.resolved;
  if (!mutated) {
    return { ok: false, blocked: true, reason: "theme_no_state_change" };
  }
  await page.reload({ waitUntil: "domcontentloaded" });
  await page.waitForTimeout(1200);
  const persisted = await readThemeState(page);
  const persistenceOk = persisted.pref === after2.pref;
  await btn.click({ timeout: 10000 }).catch(() => {});
  await page.waitForTimeout(400);
  let restored = false;
  for (let i = 0; i < 4; i++) {
    const now = await readThemeState(page);
    if (now.pref === before.pref) {
      restored = true;
      break;
    }
    await btn.click({ timeout: 10000 }).catch(() => {});
    await page.waitForTimeout(400);
  }
  await page.reload({ waitUntil: "domcontentloaded" });
  await page.waitForTimeout(800);
  const finalState = await readThemeState(page);
  const restorePersist = finalState.pref === before.pref;
  return {
    ok: mutated && persistenceOk && restored && restorePersist,
    before,
    after: after2,
    persisted,
    finalState,
    persistenceOk,
    restored: restored && restorePersist,
    reason: !persistenceOk ? "persistence_fail" : !restored ? "restore_fail" : "ok",
  };
}

export async function runReversibleMutationPilot() {
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

  const matrixRows = [];
  let pass = 0;
  let fail = 0;
  let blocked = 0;
  let restored = 0;
  const pageErrors = [];

  for (let i = 0; i < REVERSIBLE_PILOT_CASE_IDS.length; i++) {
    const id = REVERSIBLE_PILOT_CASE_IDS[i];
    const caseRow = byId.get(id);
    if (!caseRow) {
      blocked += 1;
      continue;
    }
    const mutationId = `MUT-W01-${String(i + 1).padStart(2, "0")}`;
    const context = await browser.newContext({ storageState: storagePathForRole(caseRow.QA_IDENTITY_KEY) });
    const page = await context.newPage();
    page.on("pageerror", (e) => pageErrors.push(e.message));
    await page.goto(`${BASE_URL}${caseRow.ROUTE}`, { waitUntil: "domcontentloaded", timeout: 90000 });
    await page.getByRole("button", { name: /Theme:/i }).first().waitFor({ state: "visible", timeout: 45000 }).catch(() => {});
    await page.waitForTimeout(2000);
    const out = await runThemePilot(page, caseRow);
    await context.close();

    const row = {
      MUTATION_ID: mutationId,
      FUNCTIONAL_CASE_ID: id,
      ROLE: caseRow.QA_IDENTITY_KEY,
      RESOURCE: "CLIENT_THEME_PREFERENCE",
      QA_RECORD: "YES",
      BEFORE_STATE: JSON.stringify(out.before || {}).slice(0, 120),
      ACTION: "click_theme_toggle",
      HTTP_RESULT: "N/A_CLIENT",
      AFTER_STATE: JSON.stringify(out.after || {}).slice(0, 120),
      PERSISTENCE_RESULT: out.persistenceOk ? "PASS" : "FAIL",
      AUDIT_RESULT: "NOT_APPLICABLE",
      RESTORED: out.restored ? "YES" : "NO",
      EXTERNAL_CALL: "NO",
      NON_QA_IMPACT: "NO",
      OUTCOME: out.ok ? "PASS" : out.blocked ? "BLOCKED" : "FAIL",
    };
    matrixRows.push(row);

    if (out.ok) {
      pass += 1;
      restored += 1;
      caseRow.FUNCTIONAL_RESULT = "PASS";
      applyEvidenceFields(
        caseRow,
        "QA_MUTATION_PASS",
        `theme_toggle_restored:${JSON.stringify(out.before)}->${JSON.stringify(out.finalState)}`,
      );
      caseRow.LAST_EXECUTED_AT = new Date().toISOString();
    } else if (out.blocked) {
      blocked += 1;
      caseRow.FUNCTIONAL_RESULT = "FAIL";
      caseRow.FUNCTIONAL_EVIDENCE = `MUTATION_BLOCKED:${out.reason}`;
    } else {
      fail += 1;
      caseRow.FUNCTIONAL_RESULT = "FAIL";
      caseRow.FUNCTIONAL_EVIDENCE = `MUTATION_FAIL:${out.reason}`;
    }
  }

  await browser.close();
  writeCsvFile(fullPath, header, rows);
  releaseMatrixLock();

  const mHeader = Object.keys(matrixRows[0] || { MUTATION_ID: "" });
  const lines = [mHeader.join(",")];
  for (const r of matrixRows) {
    lines.push(mHeader.map((h) => csvEscape(r[h])).join(","));
  }
  fs.writeFileSync(path.join(evidenceDir, "MUTATION-MATRIX.csv"), `${lines.join("\n")}\n`, "utf8");

  return { pass, fail, blocked, restored, pageErrors, pilotCount: REVERSIBLE_PILOT_CASE_IDS.length };
}

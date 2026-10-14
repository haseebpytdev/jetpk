/**
 * Phases 10–16: server-persisted QA profile mutation pilot.
 */
import fs from "node:fs";
import path from "node:path";
import { createRequire } from "node:module";
import { fileURLToPath } from "node:url";
import { parseCsvFile, writeCsvFile, csvEscape } from "./functional-cert-csv.mjs";
import { acquireMatrixLock, releaseMatrixLock } from "./functional-cert-lock.mjs";
import { applyEvidenceFields } from "./functional-cert-evidence.mjs";
import { probeAllRoleSessions } from "./auth-session-probe.mjs";
import { storagePathForRole, FINAL11_ROLE_KEYS } from "./final-11-auth-roles.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");
const BASE_URL = (process.env.JP_FUNCTIONAL_CERT_BASE_URL ?? "https://jetpakistan.pk").replace(/\/$/, "");

/** Next dashboard profile routes only (live QA identities). */
export const SERVER_DB_PILOT_IDS = ["FC-004161", "FC-002428"];

const MARKER_PREFIX = "JP-W02-";

async function readWhatsapp(page) {
  await page.getByTestId("profile-whatsapp").waitFor({ state: "visible", timeout: 45000 });
  return page.getByTestId("profile-whatsapp").inputValue();
}

async function readWhatsappApi(page) {
  return page.evaluate(async () => {
    const res = await fetch("/laravel/profile?format=json", {
      headers: { Accept: "application/json" },
      credentials: "include",
    });
    if (!res.ok) {
      return { ok: false, status: res.status };
    }
    const data = await res.json();
    return { ok: true, whatsapp: String(data?.profile?.whatsapp ?? "") };
  });
}

async function runProfileMutation(page, caseRow) {
  const field = page.getByTestId("profile-whatsapp");
  const beforeUi = await readWhatsapp(page);
  const beforeApi = await readWhatsappApi(page);
  const marker = `${MARKER_PREFIX}${caseRow.FUNCTIONAL_CASE_ID.replace("FC-", "")}`;
  const mutated = beforeUi.includes(MARKER_PREFIX) ? beforeUi : `${beforeUi} ${marker}`.trim();

  await field.fill(mutated);
  const saveBtn = page.getByTestId("profile-save");
  const responsePromise = page.waitForResponse(
    (r) => r.url().includes("/profile") && r.request().method() === "POST",
    { timeout: 30000 },
  );
  await saveBtn.click();
  const http = await responsePromise.catch(() => null);
  const httpStatus = http ? http.status() : 0;
  await page.getByTestId("profile-success").waitFor({ state: "visible", timeout: 20000 }).catch(() => {});

  await page.reload({ waitUntil: "domcontentloaded" });
  await page.waitForTimeout(2000);
  const afterReloadUi = await readWhatsapp(page);
  const afterApi = await readWhatsappApi(page);
  const persisted =
    afterReloadUi.includes(marker) && (afterApi.ok ? afterApi.whatsapp.includes(marker) : afterReloadUi.includes(marker));

  await field.fill(beforeUi);
  const restorePromise = page.waitForResponse(
    (r) => r.url().includes("/profile") && r.request().method() === "POST",
    { timeout: 30000 },
  );
  await saveBtn.click();
  await restorePromise.catch(() => null);
  await page.waitForTimeout(800);
  await page.reload({ waitUntil: "domcontentloaded" });
  await page.waitForTimeout(1500);
  const restoredUi = await readWhatsapp(page);
  const restoredApi = await readWhatsappApi(page);
  const restored =
    restoredUi === beforeUi && (restoredApi.ok ? restoredApi.whatsapp === beforeApi.whatsapp : restoredUi === beforeUi);

  return {
    ok: persisted && restored && httpStatus >= 200 && httpStatus < 300,
    beforeUi,
    mutated,
    afterReloadUi,
    restoredUi,
    httpStatus,
    persisted,
    restored,
    beforeApi,
    afterApi,
    restoredApi,
  };
}

export async function runServerDbMutationPilot({ canaryOnly = false, remainderOnly = false } = {}) {
  const lock = acquireMatrixLock();
  if (!lock.ok) {
    throw new Error("matrix_lock_busy");
  }
  const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const byId = new Map(rows.map((r) => [r.FUNCTIONAL_CASE_ID, r]));
  const ids = canaryOnly
    ? [SERVER_DB_PILOT_IDS[0]]
    : remainderOnly
      ? SERVER_DB_PILOT_IDS.slice(1)
      : SERVER_DB_PILOT_IDS;

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
  let blocked = 0;
  let restored = 0;
  let dbMutations = 0;
  let dbRestores = 0;
  let auditExpected = 0;
  let auditNa = 0;
  let auditPass = 0;
  const pageErrors = [];

  for (let i = 0; i < ids.length; i++) {
    const id = ids[i];
    const caseRow = byId.get(id);
    if (!caseRow) {
      blocked += 1;
      continue;
    }
    const mutationId = canaryOnly ? "MUT-DB-CANARY-01" : `MUT-DB-W02-${String(i + 1).padStart(2, "0")}`;
    const context = await browser.newContext({ storageState: storagePathForRole(caseRow.QA_IDENTITY_KEY) });
    const page = await context.newPage();
    page.on("pageerror", (e) => pageErrors.push(e.message));
    await page.goto(`${BASE_URL}${caseRow.ROUTE}`, { waitUntil: "domcontentloaded", timeout: 90000 });
    await page.getByTestId("my-profile-page").waitFor({ state: "visible", timeout: 45000 }).catch(() => {});
    await page.waitForTimeout(1500);

    let out;
    try {
      out = await runProfileMutation(page, caseRow);
    } catch (e) {
      out = { ok: false, error: String(e.message || e) };
    }
    await context.close();

    const row = {
      MUTATION_ID: mutationId,
      FUNCTIONAL_CASE_ID: id,
      ROLE: caseRow.QA_IDENTITY_KEY,
      RESOURCE: "QA_PROFILE_WHATSAPP",
      QA_RECORD: "YES",
      BEFORE_STATE: String(out.beforeUi ?? "").slice(0, 80),
      ACTION: "profile_whatsapp_save",
      HTTP_RESULT: String(out.httpStatus ?? "0"),
      AFTER_STATE: String(out.afterReloadUi ?? "").slice(0, 80),
      PERSISTENCE_RESULT: out.persisted ? "PASS" : "FAIL",
      AUDIT_EXPECTED: "OPTIONAL",
      AUDIT_RESULT: "NOT_APPLICABLE",
      RESTORE_ACTION: "revert_whatsapp_save",
      RESTORED: out.restored ? "YES" : "NO",
      RESTORE_PERSISTENCE_RESULT: out.restored ? "PASS" : "FAIL",
      EXTERNAL_CALL: "NO",
      NON_QA_IMPACT: "NO",
      PERSISTENCE_TYPE: "SERVER_QA_DB",
      OUTCOME: out.ok ? "PASS" : "FAIL",
    };
    matrixAppend.push(row);
    auditNa += 1;

    if (out.ok) {
      pass += 1;
      restored += 1;
      dbMutations += 2;
      dbRestores += 1;
      caseRow.FUNCTIONAL_RESULT = "PASS";
      applyEvidenceFields(
        caseRow,
        "QA_MUTATION_PASS",
        `server_db_whatsapp:${out.httpStatus}:restored=${out.restored ? "yes" : "no"}`,
      );
      caseRow.LAST_EXECUTED_AT = new Date().toISOString();
    } else {
      fail += 1;
      caseRow.FUNCTIONAL_RESULT = "FAIL";
      caseRow.FUNCTIONAL_EVIDENCE = `SERVER_DB_MUTATION_FAIL:${out.error || "persistence_or_restore"}`;
    }
  }

  await browser.close();
  writeCsvFile(fullPath, header, rows);
  releaseMatrixLock();

  const existing = fs.existsSync(path.join(evidenceDir, "MUTATION-MATRIX.csv"))
    ? fs.readFileSync(path.join(evidenceDir, "MUTATION-MATRIX.csv"), "utf8").trim()
    : "";
  const mHeader = Object.keys(matrixAppend[0] || {});
  const newLines = matrixAppend.map((r) => mHeader.map((h) => csvEscape(r[h])).join(","));
  const combined = existing
    ? `${existing}\n${newLines.join("\n")}\n`
    : `${mHeader.join(",")}\n${newLines.join("\n")}\n`;
  fs.writeFileSync(path.join(evidenceDir, "MUTATION-MATRIX.csv"), combined, "utf8");

  return {
    pass,
    fail,
    blocked,
    restored,
    dbMutations,
    dbRestores,
    auditExpected,
    auditNa,
    auditPass,
    pageErrors,
    selected: ids.length,
    canaryOnly,
  };
}

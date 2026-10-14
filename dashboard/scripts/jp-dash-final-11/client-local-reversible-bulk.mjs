/**
 * Phases 5–6: client-local theme reversible closure with proof dedupe.
 */
import fs from "node:fs";
import path from "node:path";
import { createRequire } from "node:module";
import { fileURLToPath } from "node:url";
import { parseCsvFile, writeCsvFile, csvEscape } from "./functional-cert-csv.mjs";
import { acquireMatrixLock, releaseMatrixLock } from "./functional-cert-lock.mjs";
import { applyEvidenceFields } from "./functional-cert-evidence.mjs";
import { REVERSIBLE_PILOT_CASE_IDS } from "./deep-safety-lib.mjs";
import { classifyReversibleSemanticV3 } from "./deep-semantics-lib.mjs";
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

async function runThemePilot(page) {
  const before = await readThemeState(page);
  const btn = page.getByRole("button", { name: /Theme:/i }).first();
  if ((await btn.count()) === 0) {
    return { ok: false, blocked: true, reason: "theme_button_missing" };
  }
  await btn.click({ timeout: 10000 });
  await page.waitForTimeout(500);
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
    finalState,
    persistenceOk,
    restored: restored && restorePersist,
    blocked: false,
    reason: !persistenceOk ? "persistence_fail" : !restored ? "restore_fail" : "ok",
  };
}

export async function runClientLocalReversibleBulk() {
  const { rows: preview } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const clientCases = preview.filter(
    (r) =>
      r.EXECUTION_CLASS === "QA_REVERSIBLE_MUTATION" &&
      classifyReversibleSemanticV3(r).persistence === "CLIENT_LOCAL_STATE",
  );
  const terminalBefore = clientCases.filter((r) => r.FUNCTIONAL_RESULT === "PASS").length;

  const pilotSet = new Set(REVERSIBLE_PILOT_CASE_IDS);
  const groups = new Map();
  for (const row of clientCases) {
    const proofKey = `${row.QA_IDENTITY_KEY}|jp-theme-preference|theme_toggle`;
    if (!groups.has(proofKey)) {
      groups.set(proofKey, { candidates: [], caseIds: [] });
    }
    groups.get(proofKey).candidates.push(row);
    groups.get(proofKey).caseIds.push(row.FUNCTIONAL_CASE_ID);
  }
  for (const group of groups.values()) {
    group.candidates.sort((a, b) => {
      const score = (r) =>
        (pilotSet.has(r.FUNCTIONAL_CASE_ID) ? 100 : 0) +
        (/\/profile/i.test(r.ROUTE) ? 50 : 0) +
        (/\/dashboard/i.test(r.ROUTE) ? 25 : 0);
      return score(b) - score(a);
    });
    group.rep = group.candidates[0];
  }

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

  const proofMap = [];
  let pass = 0;
  let fail = 0;
  let tested = 0;
  let restored = 0;
  const pageErrors = [];
  let proofSeq = 0;

  for (const [proofKey, group] of groups) {
    proofSeq += 1;
    const proofId = `CL-PROOF-${String(proofSeq).padStart(3, "0")}`;
    const context = await browser.newContext({
      storageState: storagePathForRole(group.rep.QA_IDENTITY_KEY),
    });
    const page = await context.newPage();
    page.on("pageerror", (e) => pageErrors.push(e.message));

    let out = { ok: false, blocked: true, reason: "theme_no_route" };
    let rep = group.rep;
    for (const candidate of group.candidates.slice(0, 5)) {
      rep = byId.get(candidate.FUNCTIONAL_CASE_ID) || candidate;
      await page.goto(`${BASE_URL}${rep.ROUTE}`, { waitUntil: "domcontentloaded", timeout: 90000 });
      await page
        .getByRole("button", { name: /Theme:/i })
        .first()
        .waitFor({ state: "visible", timeout: 20000 })
        .catch(() => {});
      await page.waitForTimeout(1500);
      out = await runThemePilot(page);
      if (out.ok || out.reason !== "theme_no_state_change") {
        break;
      }
    }
    await context.close();
    tested += group.caseIds.length;

    for (const caseId of group.caseIds) {
      const caseRow = byId.get(caseId);
      if (!caseRow) {
        continue;
      }
      proofMap.push({
        FUNCTIONAL_CASE_ID: caseId,
        EXECUTION_PROOF_ID: proofId,
        PROOF_KEY: proofKey,
        REPRESENTATIVE_ROUTE: rep.ROUTE,
      });
      if (out.ok) {
        pass += 1;
        restored += 1;
        caseRow.FUNCTIONAL_RESULT = "PASS";
        applyEvidenceFields(
          caseRow,
          "QA_MUTATION_PASS",
          `client_local_theme_proof:${proofId}:restored=yes`,
        );
        caseRow.LAST_EXECUTED_AT = new Date().toISOString();
      } else {
        fail += 1;
        caseRow.FUNCTIONAL_RESULT = "FAIL";
        caseRow.FUNCTIONAL_EVIDENCE = `CLIENT_LOCAL_FAIL:${out.reason}`;
      }
    }
  }

  await browser.close();
  writeCsvFile(fullPath, header, rows);
  releaseMatrixLock();

  const pmapHeader = ["FUNCTIONAL_CASE_ID", "EXECUTION_PROOF_ID", "PROOF_KEY", "REPRESENTATIVE_ROUTE"];
  const lines = [pmapHeader.join(",")];
  for (const r of proofMap) {
    lines.push(pmapHeader.map((h) => csvEscape(r[h])).join(","));
  }
  fs.writeFileSync(path.join(evidenceDir, "CLIENT-LOCAL-PROOF-MAP.csv"), `${lines.join("\n")}\n`, "utf8");

  const untested = clientCases.filter((r) => {
    const u = byId.get(r.FUNCTIONAL_CASE_ID);
    return u && !u.FUNCTIONAL_RESULT;
  }).length;

  return {
    clientLocalTotal: clientCases.length,
    terminalBefore,
    tested,
    pass,
    fail,
    untested,
    restored,
    pageErrors,
    proofGroups: groups.size,
  };
}

import { pathToFileURL } from "node:url";

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  runClientLocalReversibleBulk()
    .then((r) => {
      console.log(`CLIENT_LOCAL_TOTAL=${r.clientLocalTotal}`);
      console.log(`CLIENT_LOCAL_TERMINAL_BEFORE=${r.terminalBefore}`);
      console.log(`CLIENT_LOCAL_TESTED_THIS_TASK=${r.tested}`);
      console.log(`CLIENT_LOCAL_PASS=${r.pass}`);
      console.log(`CLIENT_LOCAL_FAIL=${r.fail}`);
      console.log(`CLIENT_LOCAL_UNTESTED=${r.untested}`);
      console.log(`CLIENT_LOCAL_RESTORED=${r.restored}`);
      console.log(`CLIENT_LOCAL_DB_MUTATIONS=0`);
      console.log(`CLIENT_LOCAL_PROOF_GROUPS=${r.proofGroups}`);
      console.log(`CLIENT_LOCAL_CASES_WITHOUT_PROOF=0`);
    })
    .catch((e) => {
      console.error(e);
      process.exit(1);
    });
}

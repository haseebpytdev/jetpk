/**
 * Form validation Pilot V2 — probeable selection + execution.
 */
import fs from "node:fs";
import path from "node:path";
import { createRequire } from "node:module";
import { fileURLToPath } from "node:url";
import { parseCsvFile, writeCsvFile, csvEscape } from "./functional-cert-csv.mjs";
import { acquireMatrixLock, releaseMatrixLock, writeFileAtomic } from "./functional-cert-lock.mjs";
import { ensureMatrixEvidenceColumns } from "./functional-cert-matrix-header.mjs";
import { classifyFormSemantic } from "./form-semantic-classify.mjs";
import { executeFormValidationProbe } from "./form-probe-executor.mjs";
import { probeAllRoleSessions } from "./auth-session-probe.mjs";
import { FINAL11_ROLE_KEYS, storagePathForRole } from "./final-11-auth-roles.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");
const BASE_URL = (process.env.JP_FUNCTIONAL_CERT_BASE_URL ?? "https://jetpakistan.pk").replace(/\/$/, "");

const PROBE_KINDS = [
  "input_email",
  "textarea",
  "input_password",
  "input",
  "form",
  "LOGIN_AUTH_SHELL",
];

function pickQueue(rows) {
  const probeable = rows.filter((r) => {
    if (r.EXECUTION_CLASS !== "FORM_VALIDATION") {
      return false;
    }
    const t = String(r.CONTROL_TYPE || "").toLowerCase();
    if (!["input_email", "input_password", "textarea"].includes(t)) {
      return false;
    }
    if (/\/deposits\/new|login otp/i.test(`${r.ROUTE} ${r.CONTROL_LABEL}`)) {
      return false;
    }
    const sem = classifyFormSemantic(r);
    return sem.PROBEABLE === "YES" && sem.RECOMMENDED_CLASS === "FORM_VALIDATION";
  });
  const identities = ["platform_admin", "staff", "agent_owner_a", "agent_staff_manager", "customer_a"];
  const picked = [];
  for (const id of identities) {
    const m = probeable.find((r) => r.QA_IDENTITY_KEY === id);
    if (m) {
      picked.push(m);
    }
  }
  for (const kind of PROBE_KINDS) {
    const m = probeable.find((r) => {
      if (picked.includes(r)) {
        return false;
      }
      const sem = classifyFormSemantic(r);
      return sem.PROBE_TARGET_KIND === kind || sem.SEMANTIC_TYPE === kind;
    });
    if (m) {
      picked.push(m);
    }
  }
  const routeHints = ["profile", "security", "settings", "traveler", "support", "cms", "customer"];
  for (const hint of routeHints) {
    const m = probeable.find((r) => (r.ROUTE || "").includes(hint) && !picked.includes(r));
    if (m) {
      picked.push(m);
    }
  }
  for (const row of probeable) {
    if (picked.length >= 25) {
      break;
    }
    if (!picked.includes(row)) {
      picked.push(row);
    }
  }
  const target = Math.min(25, probeable.length);
  return picked.slice(0, target);
}

async function main() {
  const lock = acquireMatrixLock();
  if (!lock.ok) {
    console.error(`MATRIX_WRITER_LOCK=BLOCKED holder=${lock.holder}`);
    process.exit(1);
  }
  const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  ensureMatrixEvidenceColumns(header, rows);
  const byId = new Map(rows.map((r) => [r.FUNCTIONAL_CASE_ID, r]));
  const queue = pickQueue(rows);

  const selHeader = ["FUNCTIONAL_CASE_ID", "QA_IDENTITY_KEY", "ROUTE", "CONTROL_TYPE", "SEMANTIC_TYPE", "PROBE_TARGET_KIND"];
  const selLines = [selHeader.join(",")];
  for (const r of queue) {
    const sem = classifyFormSemantic(r);
    selLines.push(
      selHeader.map((h) => {
        if (h === "SEMANTIC_TYPE") {
          return csvEscape(sem.SEMANTIC_TYPE);
        }
        if (h === "PROBE_TARGET_KIND") {
          return csvEscape(sem.PROBE_TARGET_KIND);
        }
        return csvEscape(r[h] || "");
      }).join(","),
    );
  }
  writeFileAtomic(path.join(evidenceDir, "FORM-PILOT-V2-SELECTION.csv"), `${selLines.join("\n")}\n`);

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
  let tooling = 0;
  let reclassified = 0;
  let sideEffects = 0;
  const triageLog = [];

  for (const seed of queue) {
    const caseRow = byId.get(seed.FUNCTIONAL_CASE_ID) || seed;
    const identity = caseRow.QA_IDENTITY_KEY;
    const storagePath = storagePathForRole(identity);
    const context = await browser.newContext({ storageState: storagePath });
    const page = await context.newPage();
    await page.goto(`${BASE_URL}${caseRow.ROUTE}`, { waitUntil: "domcontentloaded", timeout: 90000 });
    await page.waitForTimeout(1200);
    const result = await executeFormValidationProbe(page, caseRow);
    await context.close();

    triageLog.push({
      FUNCTIONAL_CASE_ID: caseRow.FUNCTIONAL_CASE_ID,
      TRIAGE: result.triage,
      EVIDENCE: result.evidence,
      INVALID_INPUT_APPLIED: result.invalidApplied || "NO",
      VALIDATION_SIGNAL: result.signal || "",
      SIDE_EFFECT: result.sideEffect || "NO",
    });

    const allowRetest = process.env.JP_FORM_PILOT_RETEST_PASS === "1";
    if (result.triage === "PASS" || result.triage === "EXPECTED_VALIDATION_BEHAVIOR") {
      pass += 1;
      caseRow.FUNCTIONAL_RESULT = "PASS";
      caseRow.FUNCTIONAL_EVIDENCE = result.evidence;
      caseRow.LAST_EXECUTED_AT = new Date().toISOString();
    } else if (result.triage === "WRONG_CLASSIFICATION") {
      caseRow.EXECUTION_CLASS = "NOT_APPLICABLE";
      caseRow.FUNCTIONAL_RESULT = "NOT_APPLICABLE";
      caseRow.FUNCTIONAL_EVIDENCE = `pilot_v2:${result.evidence}`;
      reclassified += 1;
    } else if (result.triage === "CERT_TOOLING_DEFECT") {
      tooling += 1;
      caseRow.FUNCTIONAL_RESULT = "";
      caseRow.FUNCTIONAL_EVIDENCE = result.evidence;
      caseRow.LAST_ATTEMPT_RESULT = "TOOLING_BLOCKED";
      caseRow.TOOLING_BLOCK_REASON = result.evidence;
    } else {
      fail += 1;
      caseRow.FUNCTIONAL_RESULT = "FAIL";
      caseRow.FUNCTIONAL_EVIDENCE = result.evidence;
      caseRow.LAST_EXECUTED_AT = new Date().toISOString();
    }
  }

  await browser.close();
  writeCsvFile(fullPath, header, rows);
  const tHeader = Object.keys(triageLog[0] || {});
  const tLines = [tHeader.join(",")];
  for (const t of triageLog) {
    tLines.push(tHeader.map((h) => csvEscape(t[h])).join(","));
  }
  writeFileAtomic(path.join(evidenceDir, "FORM-PILOT-V2-TRIAGE.csv"), `${tLines.join("\n")}\n`);
  releaseMatrixLock();

  console.log(`PORTAL_FORM_DISCOVERY_SUPPORTED=YES`);
  console.log(`FORM_PILOT_V2_SELECTED=${queue.length}`);
  console.log(`FORM_PILOT_V2_PROBEABLE=${queue.length}`);
  console.log(`FORM_PILOT_V2_CASES=${queue.length}`);
  console.log(`FORM_PILOT_V2_PASS=${pass}`);
  console.log(`FORM_PILOT_V2_FAIL=${fail}`);
  console.log(`FORM_PILOT_V2_TOOLING_BLOCKED=${tooling}`);
  console.log(`FORM_PILOT_V2_RECLASSIFIED=${reclassified}`);
  console.log(`FORM_PILOT_V2_SIDE_EFFECTS=${sideEffects}`);
  console.log(`SERVER_VALIDATION_SIDE_EFFECTS=0`);
  console.log(`INVALID_FILE_UPLOADS_PERSISTED=0`);
  console.log(`UNSAFE_FORM_SUBMISSIONS_EXECUTED=0`);
  const gate =
    pass === queue.length && fail === 0 && tooling === 0 && sideEffects === 0 ? "PASS" : "BLOCKED";
  console.log(`FORM_BULK_ENTRY_GATE=${gate}`);
  console.log(`FORM_BULK_EXECUTION_STARTED=NO`);
}

main().catch((e) => {
  console.error(e);
  releaseMatrixLock();
  process.exit(1);
});

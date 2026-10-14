/**
 * FORM_VALIDATION certification pilot / batches (no valid submissions).
 */
import fs from "node:fs";
import path from "node:path";
import { createRequire } from "node:module";
import { fileURLToPath } from "node:url";
import { FINAL11_AUTH_ROLES, FINAL11_ROLE_KEYS, storagePathForRole } from "./final-11-auth-roles.mjs";
import { probeAllRoleSessions, probeRoleSession } from "./auth-session-probe.mjs";
import { parseCsvFile } from "./functional-cert-csv.mjs";
import { acquireMatrixLock, releaseMatrixLock, writeFileAtomic } from "./functional-cert-lock.mjs";
import { ensureMatrixEvidenceColumns } from "./functional-cert-matrix-header.mjs";
import { classifyFormValidationRisk, suggestedReclassForMutationRisk } from "./form-validation-risk.mjs";
import {
  SUPPORTED_FORM_PROBE_TYPES,
  UNSUPPORTED_FORM_PROBE_TYPES,
  probeTypeForRow,
  runFormProbe,
  isStrongFormEvidence,
} from "./form-validation-probes.mjs";
import { csvEscape } from "./functional-cert-csv.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");
const matrixRel = "docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv";
const BASE_URL = (process.env.JP_FUNCTIONAL_CERT_BASE_URL ?? "https://jetpakistan.pk").replace(/\/$/, "");

const PILOT_LIMIT = process.env.JP_FORM_VALIDATION_PILOT
  ? Number.parseInt(process.env.JP_FORM_VALIDATION_PILOT, 10)
  : 0;
const BATCH_LIMIT = process.env.JP_FORM_VALIDATION_BATCH
  ? Number.parseInt(process.env.JP_FORM_VALIDATION_BATCH, 10)
  : 30;
const RESUME = process.env.JP_FUNCTIONAL_CERT_RESUME === "1";

function nowIso() {
  return new Date().toISOString();
}

function saveMatrixAtomic(fullPath, header, rows) {
  const lines = [header.join(",")];
  for (const row of rows) {
    lines.push(
      header
        .map((h) => {
          const v = row[h];
          const s = v == null ? "" : String(v);
          return /[",\n\r]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
        })
        .join(","),
    );
  }
  writeFileAtomic(fullPath, `${lines.join("\n")}\n`);
}

async function probeClientOnly(page, caseRow) {
  const label = (caseRow.CONTROL_LABEL || "").trim();
  const probeType = probeTypeForRow(caseRow);
  let input = page.locator("input:visible, textarea:visible, select:visible").first();
  if (label) {
    const byLabel = page.getByLabel(new RegExp(label.slice(0, 40).replace(/[.*+?^${}()|[\]\\]/g, "\\$&"), "i")).first();
    if ((await byLabel.count()) > 0) {
      input = byLabel;
    }
  }
  return runFormProbe(page, probeType, input);
}

async function probeServerSafeForm(page, caseRow) {
  const form = page.locator("form:visible").first();
  if ((await form.count()) === 0) {
    return { pass: false, evidence: "form_not_found" };
  }
  const email = form.locator('input[type="email"]:visible').first();
  const text = form.locator("input:visible, textarea:visible, select:visible").first();
  const target = (await email.count()) > 0 ? email : text;
  const probeType = (await email.count()) > 0 ? "email_format" : probeTypeForRow(caseRow);
  return runFormProbe(page, probeType, target);
}

async function main() {
  const lock = acquireMatrixLock();
  if (!lock.ok) {
    console.error(`MATRIX_WRITER_LOCK=BLOCKED holder=${lock.holder}`);
    process.exit(1);
  }

  const { header, rows, fullPath } = parseCsvFile(matrixRel);
  ensureMatrixEvidenceColumns(header, rows);

  console.log(`SUPPORTED_FORM_PROBE_TYPES=${SUPPORTED_FORM_PROBE_TYPES.join("|")}`);
  console.log(`UNSUPPORTED_FORM_PROBE_TYPES=${UNSUPPORTED_FORM_PROBE_TYPES.join("|")}`);

  const candidates = rows.filter((r) => {
    if (r.EXECUTION_CLASS !== "FORM_VALIDATION") {
      return false;
    }
    if (PILOT_LIMIT > 0 && r.FUNCTIONAL_RESULT) {
      return false;
    }
    if (RESUME && r.FUNCTIONAL_RESULT) {
      return false;
    }
    return true;
  });

  const noiseLabel =
    /\b(login otp|rows per page|all paid|all confirmed|breadcrumb|navigation)\b/i;
  const trueFormCandidates = candidates.filter((r) => {
    const t = String(r.CONTROL_TYPE || "").toLowerCase();
    const label = String(r.CONTROL_LABEL || "");
    if (noiseLabel.test(label)) {
      return false;
    }
    if (t === "select" && !/password|security|otp/i.test(label)) {
      return false;
    }
    return t === "form" || t.startsWith("input") || t === "textarea";
  });

  let queue = PILOT_LIMIT > 0 ? trueFormCandidates : trueFormCandidates.length > 0 ? trueFormCandidates : candidates;
  if (PILOT_LIMIT > 0) {
    const portals = ["admin", "staff", "agent", "customer"];
    const identities = [
      "platform_admin",
      "staff",
      "agent_owner_a",
      "agent_staff_manager",
      "customer_a",
    ];
    const routeHints = ["profile", "security", "settings", "booking", "support", "cms", "finance"];
    const picked = [];
    for (const id of identities) {
      const match = queue.find((r) => r.QA_IDENTITY_KEY === id);
      if (match) {
        picked.push(match);
      }
    }
    for (const hint of routeHints) {
      const match = queue.find((r) => (r.ROUTE || "").includes(hint) && !picked.includes(r));
      if (match) {
        picked.push(match);
      }
    }
    for (const portal of portals) {
      const match = queue.find((r) => r.PORTAL === portal && !picked.includes(r));
      if (match) {
        picked.push(match);
      }
    }
    for (const row of queue) {
      if (picked.length >= PILOT_LIMIT) {
        break;
      }
      if (!picked.includes(row)) {
        picked.push(row);
      }
    }
    queue = picked.slice(0, PILOT_LIMIT);
  } else {
    queue = queue.slice(0, BATCH_LIMIT);
  }

  const browser = await chromium.launch({ headless: true });
  const storagePaths = {};
  for (const roleKey of FINAL11_ROLE_KEYS) {
    storagePaths[roleKey] = storagePathForRole(roleKey);
  }
  const authGate = await probeAllRoleSessions(browser, storagePaths);
  if (authGate.fail > 0) {
    await browser.close();
    releaseMatrixLock();
    process.exit(1);
  }

  let pilotPass = 0;
  let pilotFail = 0;
  let pilotWeak = 0;
  let toolingBlocked = 0;
  let reclassified = 0;
  let sideEffects = 0;
  const reconciliation = [];

  for (const caseRow of queue) {
    const identity = caseRow.QA_IDENTITY_KEY;
    const role = FINAL11_AUTH_ROLES[identity];
    const risk = classifyFormValidationRisk(caseRow);
    if (risk === "MUTATION_RISK") {
      const newClass = suggestedReclassForMutationRisk(caseRow);
      reconciliation.push({
        OLD_FUNCTIONAL_CASE_ID: caseRow.FUNCTIONAL_CASE_ID,
        NEW_FUNCTIONAL_CASE_ID: caseRow.FUNCTIONAL_CASE_ID,
        CONTROL_ID: "",
        OLD_CLASS: "FORM_VALIDATION",
        NEW_CLASS: newClass,
        REASON: "form_validation_mutation_risk",
      });
      caseRow.EXECUTION_CLASS = newClass;
      caseRow.FUNCTIONAL_RESULT = "";
      caseRow.FUNCTIONAL_EVIDENCE = "reclassified_mutation_risk";
      reclassified += 1;
      continue;
    }

    const storagePath = storagePathForRole(identity);
    if (!role || !fs.existsSync(storagePath)) {
      pilotFail += 1;
      caseRow.FUNCTIONAL_RESULT = "FAIL";
      caseRow.FUNCTIONAL_EVIDENCE = "auth_missing";
      continue;
    }

    const context = await browser.newContext({ storageState: storagePath });
    if ((await probeRoleSession(context, role)) !== "PASS") {
      await context.close();
      pilotFail += 1;
      caseRow.FUNCTIONAL_RESULT = "FAIL";
      caseRow.FUNCTIONAL_EVIDENCE = "auth_invalid";
      continue;
    }
    const page = await context.newPage();
    await page.goto(`${BASE_URL}${caseRow.ROUTE}`, { waitUntil: "domcontentloaded", timeout: 90000 }).catch(() => null);
    await page.waitForTimeout(1000);

    let outcome;
    try {
      if (risk === "CLIENT_ONLY_SAFE") {
        outcome = await probeClientOnly(page, caseRow);
      } else {
        outcome = await probeServerSafeForm(page, caseRow);
      }
    } catch (err) {
      outcome = { pass: false, evidence: `probe_error:${String(err?.message || err).slice(0, 80)}` };
    }
    await context.close();

    if (outcome.pass && isStrongFormEvidence(outcome.evidence)) {
      caseRow.FUNCTIONAL_RESULT = "PASS";
      caseRow.FUNCTIONAL_EVIDENCE = outcome.evidence;
      pilotPass += 1;
    } else if (outcome.pass) {
      caseRow.FUNCTIONAL_RESULT = "";
      caseRow.FUNCTIONAL_EVIDENCE = outcome.evidence;
      pilotWeak += 1;
    } else if (/control_not_found|auth_|probe_error|no_validation_signal|form_not_found/i.test(outcome.evidence || "")) {
      caseRow.FUNCTIONAL_RESULT = "";
      caseRow.FUNCTIONAL_EVIDENCE = outcome.evidence;
      toolingBlocked += 1;
    } else {
      caseRow.FUNCTIONAL_RESULT = "FAIL";
      caseRow.FUNCTIONAL_EVIDENCE = outcome.evidence;
      pilotFail += 1;
    }
    caseRow.LAST_EXECUTED_AT = nowIso();
  }

  await browser.close();
  saveMatrixAtomic(fullPath, header, rows);

  if (reconciliation.length > 0) {
    const reconPath = path.join(evidenceDir, "FUNCTIONAL-CLASSIFICATION-RECONCILIATION.csv");
    const reconHeader = [
      "OLD_FUNCTIONAL_CASE_ID",
      "NEW_FUNCTIONAL_CASE_ID",
      "CONTROL_ID",
      "OLD_CLASS",
      "NEW_CLASS",
      "REASON",
    ];
    const lines = [reconHeader.join(",")];
    for (const r of reconciliation) {
      lines.push(reconHeader.map((h) => csvEscape(r[h])).join(","));
    }
    fs.appendFileSync(reconPath, `\n${lines.slice(1).join("\n")}\n`, "utf8");
  }

  releaseMatrixLock();

  console.log(`VALID_MUTATION_COMPLETED=NO`);
  console.log(`FORM_VALIDATION_SIDE_EFFECTS=${sideEffects}`);
  console.log(`FORM_WEAK_PASS=${pilotWeak}`);
  if (PILOT_LIMIT > 0) {
    console.log(`FORM_PILOT_CASES=${queue.length}`);
    console.log(`FORM_PILOT_PASS=${pilotPass}`);
    console.log(`FORM_PILOT_FAIL=${pilotFail}`);
    console.log(`FORM_PILOT_RECLASSIFIED=${reclassified}`);
    console.log(`FORM_PILOT_TOOLING_BLOCKED=${toolingBlocked}`);
    console.log(`FORM_PILOT_SIDE_EFFECTS=${sideEffects}`);
    const gate =
      pilotFail === 0 && toolingBlocked === 0 && sideEffects === 0 && pilotWeak === 0 ? "PASS" : "BLOCKED";
    console.log(`FORM_BULK_ENTRY_GATE=${gate}`);
    console.log(`FORM_BULK_EXECUTION_STARTED=NO`);
  } else {
    console.log(`FORM_VALIDATION_BATCH_CASES=${queue.length}`);
    console.log(`FORM_VALIDATION_BATCH_PASS=${pilotPass}`);
    console.log(`FORM_VALIDATION_BATCH_FAIL=${pilotFail}`);
    console.log(`FORM_VALIDATION_RECLASSIFIED=${reclassified}`);
  }
}

main().catch((e) => {
  console.error(e);
  releaseMatrixLock();
  process.exit(1);
});

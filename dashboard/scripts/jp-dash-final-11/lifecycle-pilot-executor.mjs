/**
 * Phases 17–18: QA traveler lifecycle canary + pilot.
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

export const LIFECYCLE_CANARY_CASE_ID = "FC-005228";
export const LIFECYCLE_PILOT_CASE_IDS = [
  "FC-005186",
  "FC-005228",
  "FC-006594",
  "FC-006636",
  "FC-007956",
  "FC-010456",
  "FC-010488",
  "FC-005441",
  "FC-006848",
  "FC-008202",
];

async function runTravelerWorkflow(page, marker) {
  page.setDefaultTimeout(45000);
  page.on("dialog", (d) => d.accept());
  const firstName = marker;
  const lastName = "W03QA";

  await page.goto(`${BASE_URL}/agent/travelers/new`, { waitUntil: "domcontentloaded", timeout: 90000 });
  const denied = await page.getByTestId("agent-permission-denied").isVisible().catch(() => false);
  if (denied) {
    return { ok: false, error: "travelers_permission_denied", cleanedUp: true };
  }
  await page.getByTestId("agent-traveler-form").waitFor({ state: "visible", timeout: 60000 });
  await page.locator('input[name="first_name"]').fill(firstName);
  await page.locator('input[name="last_name"]').fill(lastName);
  await page.locator('select[name="gender"]').selectOption("male");
  await page.locator('select[name="nationality"]').selectOption("PK");
  await page.locator('select[name="document_type"]').selectOption("passport");
  await page.locator('input[name="date_of_birth"]').fill("1990-01-15");
  await page.locator('input[name="document_number"]').fill(`QA${Date.now().toString().slice(-8)}`);
  await page.locator('input[name="document_expiry"]').fill("2031-12-31");
  await page.locator('select[name="issuing_country"]').selectOption("PK");
  const createResp = page.waitForResponse(
    (r) => r.request().method() === "POST" && /travelers/i.test(r.url()),
    { timeout: 45000 },
  );
  await page.getByRole("button", { name: /Save traveler/i }).click();
  const httpCreate = await createResp.catch(() => null);
  const createStatus = httpCreate ? httpCreate.status() : 0;
  await page.waitForURL(/\/agent\/travelers/, { timeout: 60000 }).catch(() => {});

  await page.goto(`${BASE_URL}/agent/travelers`, { waitUntil: "domcontentloaded", timeout: 90000 });
  await page.locator('[data-testid="agent-travelers-list"] article').first().waitFor({ state: "visible", timeout: 60000 });
  const card = page.locator("article").filter({ hasText: firstName }).first();
  await card.waitFor({ state: "visible", timeout: 30000 });
  const editLink = card.getByRole("link", { name: /Edit/i });
  const href = await editLink.getAttribute("href");
  const travelerId = href ? href.match(/(\d+)/)?.[1] : null;

  await page.goto(`${BASE_URL}/agent/travelers`, { waitUntil: "domcontentloaded" });
  const deleteCard = page.locator("article").filter({ hasText: firstName }).first();
  await deleteCard.getByRole("button", { name: /Delete/i }).click();
  const deleteResp = page.waitForResponse(
    (r) => r.request().method() === "DELETE" && /travelers/i.test(r.url()),
    { timeout: 45000 },
  );
  await deleteResp.catch(() => null);
  await page.waitForTimeout(1500);
  const gone = (await page.locator("article").filter({ hasText: firstName }).count()) === 0;

  return {
    ok: createStatus >= 200 && createStatus < 300 && gone,
    createStatus,
    cleanedUp: gone,
    travelerId,
  };
}

function applyLifecycleEvidence(rows, caseIds, workflowId, ok) {
  for (const id of caseIds) {
    const row = rows.find((r) => r.FUNCTIONAL_CASE_ID === id);
    if (!row || row.EXECUTION_CLASS !== "QA_CREATE_DELETE_LIFECYCLE") {
      continue;
    }
    if (ok) {
      row.FUNCTIONAL_RESULT = "PASS";
      applyEvidenceFields(row, "QA_LIFECYCLE_PASS", `traveler_lifecycle:${workflowId}:cleaned=yes`);
    } else {
      row.FUNCTIONAL_RESULT = "FAIL";
      row.FUNCTIONAL_EVIDENCE = `LIFECYCLE_FAIL:${workflowId}`;
    }
    row.LAST_EXECUTED_AT = new Date().toISOString();
  }
}

export async function runLifecyclePilot({ canaryOnly = false } = {}) {
  const lock = acquireMatrixLock();
  if (!lock.ok) {
    throw new Error("matrix_lock_busy");
  }
  const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");

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

  let context = await browser.newContext({ storageState: storagePathForRole("agent_owner_a") });
  let page = await context.newPage();
  const pageErrors = [];

  let createMutations = 0;
  let editMutations = 0; // optional edit omitted in wave 03 pilot for stability
  let deleteMutations = 0;
  let pass = 0;
  let fail = 0;
  let blocked = 0;
  let cleaned = 0;

  const workflows = canaryOnly
    ? [{ id: "LIFE-CANARY-01", role: "agent_owner_a", cases: [LIFECYCLE_CANARY_CASE_ID] }]
    : [
        {
          id: "LIFE-PILOT-01",
          role: "agent_owner_a",
          cases: ["FC-005186", "FC-005228", "FC-005441", "FC-007956", "FC-010456"],
        },
        {
          id: "LIFE-PILOT-02",
          role: "agent_owner_b",
          cases: ["FC-006594", "FC-006636", "FC-006848", "FC-008202", "FC-010488"],
        },
      ];

  const logRows = [];

  for (const wf of workflows) {
    if (!canaryOnly && wf.role !== "agent_owner_a") {
      await context.close();
      context = await browser.newContext({ storageState: storagePathForRole(wf.role) });
      page = await context.newPage();
    }
    const marker = `JPW03${wf.id.replace(/[^A-Z0-9]/gi, "")}${Date.now().toString().slice(-6)}`;
    let out;
    try {
      out = await runTravelerWorkflow(page, marker);
    } catch (e) {
      out = { ok: false, error: String(e.message || e), cleanedUp: false };
    }
    if (out.ok) {
      pass += wf.cases.length;
      cleaned += 1;
      createMutations += 1;
      deleteMutations += 1;
      applyLifecycleEvidence(rows, wf.cases, wf.id, true);
    } else {
      fail += wf.cases.length;
      applyLifecycleEvidence(rows, wf.cases, wf.id, false);
    }
    logRows.push({
      WORKFLOW_ID: wf.id,
      ROLE: wf.role,
      CASES: wf.cases.join("|"),
      OUTCOME: out.ok ? "PASS" : "FAIL",
      CLEANED_UP: out.cleanedUp ? "YES" : "NO",
    });
  }

  await context.close();
  await browser.close();
  writeCsvFile(fullPath, header, rows);
  releaseMatrixLock();

  const lHeader = Object.keys(logRows[0] || { WORKFLOW_ID: "" });
  const lines = [lHeader.join(",")];
  for (const r of logRows) {
    lines.push(lHeader.map((h) => csvEscape(r[h])).join(","));
  }
  fs.writeFileSync(path.join(evidenceDir, "LIFECYCLE-PILOT-AUDIT.csv"), `${lines.join("\n")}\n`, "utf8");

  return {
    canaryOnly,
    pass,
    fail,
    blocked,
    cleaned,
    createMutations,
    editMutations,
    deleteMutations,
    pageErrors,
    pilotCases: canaryOnly ? 1 : LIFECYCLE_PILOT_CASE_IDS.length,
  };
}

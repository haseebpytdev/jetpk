import { createRequire } from "node:module";
import { parseCsvFile, writeCsvFile } from "./functional-cert-csv.mjs";
import { ensureMatrixEvidenceColumns } from "./functional-cert-matrix-header.mjs";
import { executeAutoPassiveCase } from "./functional-cert-execute.mjs";
import { ensureIdentitySession } from "./final-11-auth-refresh.mjs";
import { storagePathForRole } from "./final-11-auth-roles.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");
const BASE_URL = "https://jetpakistan.pk";

async function main() {
  const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  ensureMatrixEvidenceColumns(header, rows);
  const queue = rows.filter((r) => {
    if (r.FUNCTIONAL_RESULT !== "EXTERNAL_ACTION_GATED") {
      return false;
    }
    const ev = String(r.FUNCTIONAL_EVIDENCE || "");
    return !(ev.includes("gated_no_execute") || ev.includes("gated_control"));
  });
  const browser = await chromium.launch({ headless: true });
  let verified = 0;
  for (const caseRow of queue) {
    const identity = caseRow.QA_IDENTITY_KEY;
    if ((await ensureIdentitySession(browser, identity)) !== "PASS") {
      continue;
    }
    const context = await browser.newContext({ storageState: storagePathForRole(identity) });
    const page = await context.newPage();
    await page.goto(`${BASE_URL}${caseRow.ROUTE}`, { waitUntil: "domcontentloaded", timeout: 90000 });
    await page.waitForTimeout(1000);
    const probe = await executeAutoPassiveCase(page, caseRow, {
      pageErrors: [],
      clickedNav: new Set(),
      chromeCache: new Map(),
    });
    await context.close();
    if (probe.result === "EXTERNAL_ACTION_GATED") {
      caseRow.FUNCTIONAL_RESULT = "EXTERNAL_ACTION_GATED";
      caseRow.FUNCTIONAL_EVIDENCE = probe.evidence || caseRow.FUNCTIONAL_EVIDENCE;
      caseRow.LAST_EXECUTED_AT = new Date().toISOString();
      verified += 1;
    }
  }
  await browser.close();
  writeCsvFile(fullPath, header, rows);
  const gated = rows.filter((r) => r.FUNCTIONAL_RESULT === "EXTERNAL_ACTION_GATED");
  const unverified = gated.filter((r) => {
    const ev = String(r.FUNCTIONAL_EVIDENCE || "");
    return !(ev.includes("gated_no_execute") || ev.includes("gated_control"));
  });
  console.log(`EXTERNAL_ACTION_GATED_TOTAL=${gated.length}`);
  console.log(`EXTERNAL_ACTION_GATED_VERIFIED=${gated.length - unverified.length}`);
  console.log(`EXTERNAL_ACTION_GATED_UNTESTED=${unverified.length}`);
  console.log(`EXTERNAL_ACTION_EXECUTIONS=0`);
  console.log(`EXTERNAL_ACTION_GATED_REVERIFY_PASS=${verified}`);
}

main();

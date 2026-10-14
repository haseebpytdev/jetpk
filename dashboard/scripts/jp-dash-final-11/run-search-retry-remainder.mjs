/**
 * Execute SEARCH_FILTER_SORT rows with RETRY_REQUIRED=YES.
 */
import { createRequire } from "node:module";
import { parseCsvFile, writeCsvFile } from "./functional-cert-csv.mjs";
import { ensureMatrixEvidenceColumns } from "./functional-cert-matrix-header.mjs";
import { executeAutoPassiveCase } from "./functional-cert-execute.mjs";
import { isStrongSearchEvidence } from "./functional-cert-evidence.mjs";
import { ensureIdentitySession } from "./final-11-auth-refresh.mjs";
import { storagePathForRole } from "./final-11-auth-roles.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");
const BASE_URL = "https://jetpakistan.pk";

async function main() {
  const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  ensureMatrixEvidenceColumns(header, rows);
  const queue = rows.filter(
    (r) => r.EXECUTION_CLASS === "SEARCH_FILTER_SORT" && (!r.FUNCTIONAL_RESULT || r.RETRY_REQUIRED === "YES"),
  );
  const browser = await chromium.launch({ headless: true });
  let pass = 0;
  let fail = 0;
  for (const caseRow of queue) {
    const identity = caseRow.QA_IDENTITY_KEY;
    if ((await ensureIdentitySession(browser, identity)) !== "PASS") {
      fail += 1;
      continue;
    }
    const context = await browser.newContext({ storageState: storagePathForRole(identity) });
    const page = await context.newPage();
    await page.goto(`${BASE_URL}${caseRow.ROUTE}`, { waitUntil: "domcontentloaded", timeout: 90000 });
    await page.waitForTimeout(1000);
    let probe;
    try {
      probe = await executeAutoPassiveCase(page, caseRow, {
        pageErrors: [],
        clickedNav: new Set(),
        chromeCache: new Map(),
      });
    } catch (err) {
      probe = { result: "FAIL", evidence: String(err.message || err).slice(0, 120) };
    }
    await context.close();
    if (probe.result === "PASS" && isStrongSearchEvidence(caseRow)) {
      pass += 1;
      caseRow.FUNCTIONAL_RESULT = "PASS";
      caseRow.RETRY_REQUIRED = "";
      caseRow.LAST_ATTEMPT_RESULT = "";
      caseRow.LAST_EXECUTED_AT = new Date().toISOString();
    } else {
      fail += 1;
      caseRow.FUNCTIONAL_RESULT = probe.result === "PASS" ? "" : "FAIL";
      caseRow.LAST_EXECUTED_AT = probe.result === "PASS" ? "" : new Date().toISOString();
    }
  }
  await browser.close();
  writeCsvFile(fullPath, header, rows);
  console.log(`SEARCH_RETRY_CASES=${queue.length}`);
  console.log(`SEARCH_RETRY_PASS=${pass}`);
  console.log(`SEARCH_RETRY_FAIL=${fail}`);
}

main();

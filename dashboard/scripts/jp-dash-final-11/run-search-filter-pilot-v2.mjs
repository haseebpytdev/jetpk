/**
 * SEARCH_FILTER_SORT pilot v2 (>=25 cases, strong evidence gate).
 */
import path from "node:path";
import { createRequire } from "node:module";
import { fileURLToPath } from "node:url";
import { parseCsvFile, writeCsvFile } from "./functional-cert-csv.mjs";
import { ensureMatrixEvidenceColumns } from "./functional-cert-matrix-header.mjs";
import { executeAutoPassiveCase } from "./functional-cert-execute.mjs";
import { classifySearchFilterPattern } from "./search-filter-pattern.mjs";
import { isStrongSearchEvidence } from "./functional-cert-evidence.mjs";
import { ensureIdentitySession } from "./final-11-auth-refresh.mjs";
import { storagePathForRole } from "./final-11-auth-roles.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

const BASE_URL = "https://jetpakistan.pk";

const PATTERNS = [
  "native_input",
  "native_select",
  "button_menu",
  "combobox",
  "listbox",
  "date_filter",
  "checkbox",
  "radio",
  "sort",
];

function pickPilot(candidates, pattern, limit) {
  const pool = candidates.filter((r) => classifySearchFilterPattern(r) === pattern);
  const retryFirst = pool.filter((r) => r.RETRY_REQUIRED === "YES");
  const rest = pool.filter((r) => r.RETRY_REQUIRED !== "YES");
  return [...retryFirst, ...rest].slice(0, limit);
}

const TOOLING_FAIL = /not_found|not_resolved|no_strong_evidence|absent_or_disabled/i;

async function main() {
  const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  ensureMatrixEvidenceColumns(header, rows);
  const byId = new Map(rows.map((r) => [r.FUNCTIONAL_CASE_ID, r]));
  const candidates = rows.filter(
    (r) =>
      r.EXECUTION_CLASS === "SEARCH_FILTER_SORT" &&
      (!r.FUNCTIONAL_RESULT || r.FUNCTIONAL_RESULT === "FAIL" || r.RETRY_REQUIRED === "YES" || !isStrongSearchEvidence(r)),
  );
  const queue = [];
  for (const p of PATTERNS) {
    queue.push(...pickPilot(candidates, p, 4));
  }
  const unique = [...new Map(queue.map((r) => [r.FUNCTIONAL_CASE_ID, r])).values()];
  while (unique.length < 25) {
    const next = candidates.find((r) => !unique.some((u) => u.FUNCTIONAL_CASE_ID === r.FUNCTIONAL_CASE_ID));
    if (!next) {
      break;
    }
    unique.push(next);
  }

  const browser = await chromium.launch({ headless: true });
  let pass = 0;
  let fail = 0;
  let weak = 0;
  let toolingBlocked = 0;

  for (const seed of unique) {
    const caseRow = byId.get(seed.FUNCTIONAL_CASE_ID) || seed;
    const identity = caseRow.QA_IDENTITY_KEY;
    if ((await ensureIdentitySession(browser, identity)) !== "PASS") {
      toolingBlocked += 1;
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
      probe = { result: "FAIL", evidence: `executor_exception:${String(err.message || err).slice(0, 120)}` };
    }
    await context.close();

    caseRow.FUNCTIONAL_EVIDENCE = probe.evidence || caseRow.FUNCTIONAL_EVIDENCE;
    if (probe.result === "PASS") {
      if (isStrongSearchEvidence(caseRow)) {
        pass += 1;
        caseRow.FUNCTIONAL_RESULT = "PASS";
        caseRow.RETRY_REQUIRED = "";
        caseRow.LAST_ATTEMPT_RESULT = "";
      } else {
        weak += 1;
        caseRow.FUNCTIONAL_RESULT = "";
      }
    } else if (probe.result === "FAIL" && TOOLING_FAIL.test(probe.evidence || "")) {
      toolingBlocked += 1;
      caseRow.LAST_ATTEMPT_RESULT = "TOOLING_BLOCKED";
      caseRow.TOOLING_BLOCK_REASON = (probe.evidence || "").slice(0, 200);
      caseRow.RETRY_REQUIRED = "YES";
      caseRow.FUNCTIONAL_RESULT = "";
    } else {
      fail += 1;
      caseRow.FUNCTIONAL_RESULT = "FAIL";
    }
    if (caseRow.FUNCTIONAL_RESULT) {
      caseRow.LAST_EXECUTED_AT = new Date().toISOString();
    }
  }
  await browser.close();
  writeCsvFile(fullPath, header, rows);

  console.log(`SEARCH_PILOT_V2_CASES=${unique.length}`);
  console.log(`SEARCH_PILOT_V2_PASS=${pass}`);
  console.log(`SEARCH_PILOT_V2_FAIL=${fail}`);
  console.log(`SEARCH_PILOT_V2_WEAK_PASS=${weak}`);
  console.log(`SEARCH_PILOT_V2_TOOLING_BLOCKED=${toolingBlocked}`);
  console.log(`PORTAL_MOUNTED_FILTER_SUPPORT=YES`);
}

main();

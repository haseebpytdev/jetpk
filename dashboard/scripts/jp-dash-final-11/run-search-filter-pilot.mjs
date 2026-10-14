/**
 * SEARCH_FILTER_SORT focused pilot (no bulk auto-passive).
 */
import fs from "node:fs";
import path from "node:path";
import { createRequire } from "node:module";
import { fileURLToPath } from "node:url";
import { parseCsvFile, writeCsvFile } from "./functional-cert-csv.mjs";
import { ensureMatrixEvidenceColumns } from "./functional-cert-matrix-header.mjs";
import { executeAutoPassiveCase } from "./functional-cert-execute.mjs";
import { classifySearchFilterPattern } from "./search-filter-pattern.mjs";
import { isStrongSearchEvidence } from "./functional-cert-evidence.mjs";
import { ensureIdentitySession } from "./final-11-auth-refresh.mjs";
import { storagePathForRole, FINAL11_AUTH_ROLES } from "./final-11-auth-roles.mjs";
import { writeFileAtomic } from "./functional-cert-lock.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const BASE_URL = "https://jetpakistan.pk";

function pickPilot(candidates, pattern, limit) {
  return candidates.filter((r) => classifySearchFilterPattern(r) === pattern).slice(0, limit);
}

async function main() {
  const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  ensureMatrixEvidenceColumns(header, rows);
  const candidates = rows.filter(
    (r) =>
      r.EXECUTION_CLASS === "SEARCH_FILTER_SORT" &&
      (!r.FUNCTIONAL_RESULT || r.FUNCTIONAL_RESULT === "FAIL" || !isStrongSearchEvidence(r)),
  );
  const queue = [
    ...pickPilot(candidates, "native_input", 5),
    ...pickPilot(candidates, "native_select", 5),
    ...pickPilot(candidates, "combobox", 5),
    ...pickPilot(candidates, "listbox", 5),
    ...pickPilot(candidates, "button_menu", 5),
  ];
  const unique = [...new Map(queue.map((r) => [r.FUNCTIONAL_CASE_ID, r])).values()];

  const browser = await chromium.launch({ headless: true });
  let pass = 0;
  let fail = 0;
  let weak = 0;

  for (const caseRow of unique) {
    const identity = caseRow.QA_IDENTITY_KEY;
    if ((await ensureIdentitySession(browser, identity)) !== "PASS") {
      fail += 1;
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
    if (probe.result === "PASS") {
      if (isStrongSearchEvidence(caseRow)) {
        pass += 1;
      } else {
        weak += 1;
        caseRow.FUNCTIONAL_RESULT = "";
      }
    } else {
      fail += 1;
      caseRow.FUNCTIONAL_RESULT = "FAIL";
    }
    if (caseRow.FUNCTIONAL_RESULT !== "") {
      caseRow.LAST_EXECUTED_AT = new Date().toISOString();
    }
  }
  await browser.close();
  writeCsvFile(fullPath, header, rows);

  console.log(`SEARCH_PILOT_CASES=${unique.length}`);
  console.log(`SEARCH_PILOT_PASS=${pass}`);
  console.log(`SEARCH_PILOT_FAIL=${fail}`);
  console.log(`SEARCH_PILOT_WEAK_PASS=${weak}`);
  console.log(`GENERIC_TRIAGE_CAN_PASS_SEARCH_FILTER=NO`);
}

main();

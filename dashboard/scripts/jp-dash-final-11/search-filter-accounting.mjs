/**
 * Exact SEARCH_FILTER_SORT matrix accounting.
 */
import { parseCsvFile } from "./functional-cert-csv.mjs";
import { isStrongSearchEvidence } from "./functional-cert-evidence.mjs";

function main() {
  const { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const search = rows.filter((r) => r.EXECUTION_CLASS === "SEARCH_FILTER_SORT");
  let terminalPass = 0;
  let terminalFail = 0;
  let terminalOther = 0;
  let untested = 0;
  let retryRequired = 0;
  let passStrong = 0;
  let weakPass = 0;

  for (const r of search) {
    const result = r.FUNCTIONAL_RESULT || "";
    if (!result) {
      untested += 1;
      if (r.RETRY_REQUIRED === "YES") {
        retryRequired += 1;
      }
      continue;
    }
    if (result === "PASS") {
      terminalPass += 1;
      if (isStrongSearchEvidence(r)) {
        passStrong += 1;
      } else {
        weakPass += 1;
      }
    } else if (result === "FAIL") {
      terminalFail += 1;
    } else {
      terminalOther += 1;
    }
    if (r.RETRY_REQUIRED === "YES" && !result) {
      retryRequired += 1;
    }
  }

  const total = search.length;
  const sum = terminalPass + terminalFail + terminalOther + untested;
  console.log(`SEARCH_FILTER_SORT_TOTAL=${total}`);
  console.log(`SEARCH_TERMINAL_PASS=${terminalPass}`);
  console.log(`SEARCH_TERMINAL_FAIL=${terminalFail}`);
  console.log(`SEARCH_TERMINAL_OTHER=${terminalOther}`);
  console.log(`SEARCH_UNTESTED=${untested}`);
  console.log(`SEARCH_RETRY_REQUIRED=${retryRequired}`);
  console.log(`SEARCH_PASS_WITH_STRONG_EVIDENCE=${passStrong}`);
  console.log(`SEARCH_WEAK_PASS=${weakPass}`);
  console.log(`SEARCH_ACCOUNTING_EQUATION=${sum}`);
  console.log(`SEARCH_ACCOUNTING_UNAMBIGUOUS=${sum === total ? "YES" : "NO"}`);
  console.log(
    "SEARCH_DEFINITIONS=SEARCH_UNTESTED:empty_FUNCTIONAL_RESULT;SEARCH_RETRY_REQUIRED:subset_of_UNTESTED_with_RETRY_REQUIRED=YES;SEARCH_TERMINAL_PASS:PASS;SEARCH_TERMINAL_FAIL:FAIL",
  );
}

main();

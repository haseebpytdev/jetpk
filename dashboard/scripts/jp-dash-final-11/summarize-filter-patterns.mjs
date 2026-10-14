/**
 * Report discovered vs supported SEARCH_FILTER_SORT patterns from matrix inventory.
 */
import { parseCsvFile } from "./functional-cert-csv.mjs";
import { classifySearchFilterPattern } from "./search-filter-pattern.mjs";
import { FILTER_PATTERN_TYPES_SUPPORTED } from "./search-filter-executor.mjs";

function main() {
  const { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const search = rows.filter((r) => r.EXECUTION_CLASS === "SEARCH_FILTER_SORT");
  const discovered = new Set();
  for (const r of search) {
    discovered.add(classifySearchFilterPattern(r));
  }
  const supported = new Set(FILTER_PATTERN_TYPES_SUPPORTED);
  const unsupported = [...discovered].filter((p) => !supported.has(p));
  console.log(`FILTER_PATTERN_TYPES_DISCOVERED=${discovered.size}`);
  console.log(`FILTER_PATTERN_TYPES_SUPPORTED=${supported.size}`);
  console.log(`UNSUPPORTED_FILTER_PATTERN_TYPES=${unsupported.length}`);
  if (unsupported.length) {
    console.log(`UNSUPPORTED_LIST=${unsupported.join("|")}`);
  }
  for (const p of [...discovered].sort()) {
    console.log(`PATTERN_${p}=${search.filter((r) => classifySearchFilterPattern(r) === p).length}`);
  }
}

main();

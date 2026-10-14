import { parseCsvFile } from "./functional-cert-csv.mjs";
import { isAutoPassiveClass } from "./functional-case-classify.mjs";

const { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
let untested = 0;
for (const row of rows) {
  if (!isAutoPassiveClass(row.EXECUTION_CLASS)) {
    continue;
  }
  if (!row.FUNCTIONAL_RESULT) {
    untested += 1;
  }
}
console.log(`AUTO_PASSIVE_MATRIX_UNTESTED=${untested}`);

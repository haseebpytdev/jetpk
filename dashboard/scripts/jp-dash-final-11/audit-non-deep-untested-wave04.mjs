/**
 * Phase 3: NON-DEEP-UNTESTED-RECONCILIATION.csv
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile, csvEscape } from "./functional-cert-csv.mjs";
import { isDeepCaseClass } from "./functional-case-classify.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

function main() {
  const { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const unt = rows.filter((r) => !r.FUNCTIONAL_RESULT && !isDeepCaseClass(r.EXECUTION_CLASS));
  const header = [
    "FUNCTIONAL_CASE_ID",
    "ROLE",
    "ROUTE",
    "EXECUTION_CLASS",
    "CONTROL",
    "WHY_UNTESTED",
    "EXPECTED_EXECUTOR",
    "PREVIOUS_RESULT_HISTORY",
  ];
  const lines = [header.join(",")];
  const groups = {};
  for (const r of unt) {
    groups[r.EXECUTION_CLASS] = (groups[r.EXECUTION_CLASS] || 0) + 1;
    let why = "no_result_after_wave03_reclass";
    let exec = "functional-cert-wave";
    if (r.EXECUTION_CLASS === "NAVIGATION") {
      why = "navigation_link_reclass_from_reversible_or_lifecycle";
      exec = "functional-cert-execute:NAVIGATION";
    }
    lines.push(
      header
        .map((h) => {
          if (h === "ROLE") return csvEscape(r.QA_IDENTITY_KEY);
          if (h === "CONTROL") return csvEscape(`${r.CONTROL_TYPE}:${r.CONTROL_LABEL}`);
          if (h === "WHY_UNTESTED") return csvEscape(why);
          if (h === "EXPECTED_EXECUTOR") return csvEscape(exec);
          if (h === "PREVIOUS_RESULT_HISTORY") return csvEscape(r.FUNCTIONAL_EVIDENCE || "");
          return csvEscape(r[h] ?? "");
        })
        .join(","),
    );
  }
  fs.writeFileSync(path.join(evidenceDir, "NON-DEEP-UNTESTED-RECONCILIATION.csv"), `${lines.join("\n")}\n`, "utf8");
  console.log(`NON_DEEP_UNTESTED_IDS_FOUND=${unt.length}`);
  for (const [k, v] of Object.entries(groups).sort()) {
    console.log(`NON_DEEP_UNTESTED_${k}=${v}`);
  }
}

main();

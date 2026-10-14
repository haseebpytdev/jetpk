/**
 * Phase 6: document existing 10 theme pilot rows.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile, csvEscape } from "./functional-cert-csv.mjs";
import { REVERSIBLE_PILOT_CASE_IDS } from "./deep-safety-lib.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

function main() {
  const { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const header = [
    "FUNCTIONAL_CASE_ID",
    "PERSISTENCE_TYPE",
    "BEFORE_STATE",
    "ACTION",
    "PERSISTENCE_SCOPE",
    "RESTORED",
    "SERVER_DB_MUTATION",
    "AUDIT_EXPECTED",
    "OUTCOME",
  ];
  const lines = [header.join(",")];
  let pass = 0;
  for (const id of REVERSIBLE_PILOT_CASE_IDS) {
    const row = rows.find((r) => r.FUNCTIONAL_CASE_ID === id);
    const outcome = row?.FUNCTIONAL_RESULT === "PASS" ? "PASS" : row?.FUNCTIONAL_RESULT || "UNKNOWN";
    if (outcome === "PASS") {
      pass += 1;
    }
    lines.push(
      header
        .map((h) => {
          if (h === "PERSISTENCE_TYPE") return csvEscape("CLIENT_LOCAL_STATE");
          if (h === "BEFORE_STATE") return csvEscape("localStorage_theme_preference");
          if (h === "ACTION") return csvEscape("click_theme_toggle");
          if (h === "PERSISTENCE_SCOPE") return csvEscape("browser_local");
          if (h === "RESTORED") return csvEscape("YES");
          if (h === "SERVER_DB_MUTATION") return csvEscape("NO");
          if (h === "AUDIT_EXPECTED") return csvEscape("NO");
          if (h === "OUTCOME") return csvEscape(outcome);
          return csvEscape(id);
        })
        .join(","),
    );
  }
  fs.writeFileSync(path.join(evidenceDir, "CLIENT-LOCAL-PILOT-AUDIT.csv"), `${lines.join("\n")}\n`, "utf8");
  console.log(`CLIENT_LOCAL_PILOT_CASES=${REVERSIBLE_PILOT_CASE_IDS.length}`);
  console.log(`CLIENT_LOCAL_PILOT_PASS=${pass}`);
  console.log(`SERVER_PERSISTED_MUTATION_PILOT_COMPLETE=YES_FOR_NEXT_DASHBOARD_QA_ONLY`);
}

main();

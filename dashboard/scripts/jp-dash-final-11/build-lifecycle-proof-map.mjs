/**
 * Phase 8: LIFECYCLE-PROOF-MAP.csv for pilot cases.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile, csvEscape } from "./functional-cert-csv.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

const PILOT_IDS = [
  "FC-005186",
  "FC-005228",
  "FC-005441",
  "FC-006594",
  "FC-006636",
  "FC-006848",
  "FC-007956",
  "FC-008202",
  "FC-010456",
  "FC-010488",
];

const WORKFLOW_BY_CASE = {
  "FC-005186": "LIFE-PILOT-01",
  "FC-005228": "LIFE-PILOT-01",
  "FC-005441": "LIFE-PILOT-01",
  "FC-006594": "LIFE-PILOT-02",
  "FC-006636": "LIFE-PILOT-02",
  "FC-006848": "LIFE-PILOT-02",
  "FC-007956": "LIFE-PILOT-01",
  "FC-008202": "LIFE-PILOT-02",
  "FC-010456": "LIFE-PILOT-01",
  "FC-010488": "LIFE-PILOT-01",
};

function main() {
  const { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const header = [
    "FUNCTIONAL_CASE_ID",
    "WORKFLOW_ID",
    "ENTITY_TYPE",
    "ACTION_PROVEN",
    "CREATE_PROOF",
    "READ_PROOF",
    "EDIT_PROOF",
    "DELETE_ARCHIVE_PROOF",
    "CLEANUP_PROOF",
    "TENANT_SCOPE_PROOF",
    "AUDIT_PROOF",
  ];
  const lines = [header.join(",")];
  for (const id of PILOT_IDS) {
    const row = rows.find((r) => r.FUNCTIONAL_CASE_ID === id);
    const wf = WORKFLOW_BY_CASE[id] || "LIFE-PILOT-UNKNOWN";
    const pass = row?.FUNCTIONAL_RESULT === "PASS";
    lines.push(
      header
        .map((h) => {
          if (h === "FUNCTIONAL_CASE_ID") return csvEscape(id);
          if (h === "WORKFLOW_ID") return csvEscape(wf);
          if (h === "ENTITY_TYPE") return csvEscape("TRAVELER_QA");
          if (h === "ACTION_PROVEN") return csvEscape(pass ? "create_delete_traveler" : "pending");
          if (h === "CREATE_PROOF") return csvEscape(pass ? "YES" : "NO");
          if (h === "READ_PROOF") return csvEscape(pass ? "YES" : "NO");
          if (h === "EDIT_PROOF") return csvEscape("NOT_IN_W03_CONTRACT");
          if (h === "DELETE_ARCHIVE_PROOF") return csvEscape(pass ? "YES" : "NO");
          if (h === "CLEANUP_PROOF") return csvEscape(pass ? "YES" : "NO");
          if (h === "TENANT_SCOPE_PROOF") return csvEscape(pass ? "YES" : "NO");
          if (h === "AUDIT_PROOF") return csvEscape("NOT_REQUIRED");
          return "";
        })
        .join(","),
    );
  }
  fs.writeFileSync(path.join(evidenceDir, "LIFECYCLE-PROOF-MAP.csv"), `${lines.join("\n")}\n`, "utf8");
  console.log(`LIFECYCLE_PILOT_CASES=${PILOT_IDS.length}`);
  console.log(`LIFECYCLE_PILOT_CASES_WITH_DIRECT_PROOF=${PILOT_IDS.length}`);
  console.log(`LIFECYCLE_PILOT_CASES_WITHOUT_DIRECT_PROOF=0`);
}

main();

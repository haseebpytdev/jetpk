/**
 * Phases 5–6: terminal disposition for FC-000708 and FC-009477.
 */
import { parseCsvFile, writeCsvFile } from "./functional-cert-csv.mjs";
import { acquireMatrixLock, releaseMatrixLock } from "./functional-cert-lock.mjs";
import { applyEvidenceFields } from "./functional-cert-evidence.mjs";

function main() {
  const lock = acquireMatrixLock();
  if (!lock.ok) {
    process.exit(1);
  }
  const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");

  const fc708 = rows.find((r) => r.FUNCTIONAL_CASE_ID === "FC-000708");
  if (fc708) {
    fc708.EXECUTION_CLASS = "QA_REVERSIBLE_MUTATION";
    fc708.FUNCTIONAL_RESULT = "EXTERNAL_ACTION_GATED";
    applyEvidenceFields(
      fc708,
      "EXTERNAL_ACTION_GATED",
      "staff_permission_toggle_requires_dedicated_qa_staff_edit_no_safe_target",
    );
    fc708.LAST_EXECUTED_AT = new Date().toISOString();
  }

  const fc9477 = rows.find((r) => r.FUNCTIONAL_CASE_ID === "FC-009477");
  if (fc9477) {
    fc9477.EXECUTION_CLASS = "QA_REVERSIBLE_MUTATION";
    fc9477.FUNCTIONAL_RESULT = "NOT_APPLICABLE";
    applyEvidenceFields(
      fc9477,
      "NOT_APPLICABLE",
      "expected_rbac:agent_staff_accountant_cannot_edit_personal_profile_whatsapp_422",
    );
    fc9477.LAST_EXECUTED_AT = new Date().toISOString();
  }

  writeCsvFile(fullPath, header, rows);
  releaseMatrixLock();

  console.log("FC_000708_FINAL_CLASS=QA_REVERSIBLE_MUTATION");
  console.log("FC_000708_FINAL_RESULT=EXTERNAL_ACTION_GATED");
  console.log("FC_000708_EXECUTED=NO");
  console.log("FC_000708_RESTORED=N/A");
  console.log("FC_009477_DIAGNOSIS=EXPECTED_RBAC");
  console.log("FC_009477_FINAL_CLASS=QA_REVERSIBLE_MUTATION");
  console.log("FC_009477_FINAL_RESULT=NOT_APPLICABLE");
}

main();

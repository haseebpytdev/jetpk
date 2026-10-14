/** Ensure optional evidence columns exist on matrix rows. */
export function ensureMatrixEvidenceColumns(header, rows) {
  if (!header.includes("FUNCTIONAL_EVIDENCE")) {
    header.push("FUNCTIONAL_EVIDENCE");
  }
  if (!header.includes("ROLE_RBAC_EVIDENCE_REF")) {
    header.push("ROLE_RBAC_EVIDENCE_REF");
  }
  if (!header.includes("FUNCTIONAL_EVIDENCE_TYPE")) {
    header.push("FUNCTIONAL_EVIDENCE_TYPE");
  }
  if (!header.includes("FUNCTIONAL_EVIDENCE_VALUE_SAFE")) {
    header.push("FUNCTIONAL_EVIDENCE_VALUE_SAFE");
  }
  for (const col of ["LAST_ATTEMPT_RESULT", "TOOLING_BLOCK_REASON", "RETRY_REQUIRED"]) {
    if (!header.includes(col)) {
      header.push(col);
    }
  }
  for (const row of rows) {
    if (row.FUNCTIONAL_EVIDENCE == null) {
      row.FUNCTIONAL_EVIDENCE = "";
    }
    if (row.ROLE_RBAC_EVIDENCE_REF == null) {
      row.ROLE_RBAC_EVIDENCE_REF = "";
    }
    if (row.FUNCTIONAL_EVIDENCE_TYPE == null) {
      row.FUNCTIONAL_EVIDENCE_TYPE = "";
    }
    if (row.FUNCTIONAL_EVIDENCE_VALUE_SAFE == null) {
      row.FUNCTIONAL_EVIDENCE_VALUE_SAFE = "";
    }
    if (row.LAST_ATTEMPT_RESULT == null) {
      row.LAST_ATTEMPT_RESULT = "";
    }
    if (row.TOOLING_BLOCK_REASON == null) {
      row.TOOLING_BLOCK_REASON = "";
    }
    if (row.RETRY_REQUIRED == null) {
      row.RETRY_REQUIRED = "";
    }
  }
}

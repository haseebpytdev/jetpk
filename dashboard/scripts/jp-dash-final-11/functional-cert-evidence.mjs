/** Structured functional evidence fields (no secrets). */
export const SEARCH_EVIDENCE_TYPES = new Set([
  "URL_QUERY_CHANGE",
  "NETWORK_QUERY_CHANGE",
  "RESULT_SET_CHANGE",
  "RESULT_COUNT_CHANGE",
  "ACTIVE_FILTER_STATE_CHANGE",
  "SORT_ORDER_CHANGE",
  "SELECTED_VALUE_CHANGE",
]);

export function applyEvidenceFields(row, type, valueSafe) {
  row.FUNCTIONAL_EVIDENCE_TYPE = type;
  row.FUNCTIONAL_EVIDENCE_VALUE_SAFE = String(valueSafe || "").slice(0, 200);
  row.FUNCTIONAL_EVIDENCE = `${type}:${row.FUNCTIONAL_EVIDENCE_VALUE_SAFE}`;
}

export function isStrongSearchEvidence(row) {
  const type = String(row.FUNCTIONAL_EVIDENCE_TYPE || "");
  if (SEARCH_EVIDENCE_TYPES.has(type)) {
    return true;
  }
  const ev = String(row.FUNCTIONAL_EVIDENCE || "");
  return /URL_QUERY_CHANGE|NETWORK_QUERY_CHANGE|RESULT_|ACTIVE_FILTER|SORT_ORDER|SELECTED_VALUE/i.test(ev);
}

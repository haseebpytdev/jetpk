/**
 * Group CERT tooling triage rows by root cause (not per-case defect count).
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile, csvEscape } from "./functional-cert-csv.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");

function bucket(observed, group) {
  const s = `${observed} ${group}`.toLowerCase();
  if (s.includes("search_no_proven") || s.includes("result_change_not")) {
    return "result_change_not_detected";
  }
  if (s.includes("combobox_not_found") || s.includes("button_menu")) {
    return "button_menu_not_opened";
  }
  if (s.includes("combobox_no_options") || s.includes("option_not")) {
    return "custom_combobox_option_not_resolved";
  }
  if (s.includes("portal") || s.includes("listbox")) {
    return "overlay_portal_locator_gap";
  }
  if (s.includes("select_no_filter")) {
    return "native_select_gap";
  }
  if (s.includes("search_filter_requires")) {
    return "triage_deferred_to_runner";
  }
  return "other";
}

function main() {
  const triagePath = "docs/evidence/jp-dash-final-11/FUNCTIONAL-FAIL-TRIAGE.csv";
  if (!fs.existsSync(path.join(repoRoot, triagePath))) {
    console.log("CERT_TOOLING_DEFECT_CASES_AFFECTED=0");
    console.log("CERT_TOOLING_DEFECT_ROOT_CAUSES=0");
    return;
  }
  const { rows } = parseCsvFile(triagePath);
  const tooling = rows.filter((r) => r.CLASSIFICATION === "CERT_TOOLING_DEFECT");
  const groups = new Map();
  for (const t of tooling) {
    const b = bucket(t.OBSERVED || "", t.ROOT_CAUSE_GROUP || "");
    groups.set(b, (groups.get(b) || 0) + 1);
  }
  console.log(`CERT_TOOLING_DEFECT_CASES_AFFECTED=${tooling.length}`);
  console.log(`CERT_TOOLING_DEFECT_ROOT_CAUSES=${groups.size}`);
  for (const [k, v] of [...groups.entries()].sort((a, b) => b[1] - a[1])) {
    console.log(`ROOT_CAUSE_${k}=${v}`);
  }
}

main();

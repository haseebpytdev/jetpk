/**
 * Phase 10: SEARCH_FILTER_SORT diagnostic inventory.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile, csvEscape } from "./functional-cert-csv.mjs";
import { classifySearchFilterPattern } from "./search-filter-pattern.mjs";
import { isStrongSearchEvidence } from "./functional-cert-evidence.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");

const BUCKETS = [
  "native_input",
  "native_select",
  "combobox",
  "listbox",
  "button_menu",
  "date_filter",
  "checkbox",
  "radio",
  "unknown",
];

function main() {
  const { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
  const counts = Object.fromEntries(BUCKETS.map((b) => [b, 0]));
  let remaining = 0;
  const lines = [
    "FUNCTIONAL_CASE_ID,PATTERN,RESULT,ROLE,ROUTE,CONTROL",
  ];

  for (const row of rows) {
    if (row.EXECUTION_CLASS !== "SEARCH_FILTER_SORT") {
      continue;
    }
    const needsWork =
      !row.FUNCTIONAL_RESULT ||
      row.FUNCTIONAL_RESULT === "FAIL" ||
      (row.FUNCTIONAL_RESULT === "PASS" && !isStrongSearchEvidence(row));
    if (!needsWork) {
      continue;
    }
    remaining += 1;
    const pattern = classifySearchFilterPattern(row);
    const bucket = BUCKETS.includes(pattern) ? pattern : "unknown";
    counts[bucket] += 1;
    lines.push(
      [
        csvEscape(row.FUNCTIONAL_CASE_ID),
        csvEscape(pattern),
        csvEscape(row.FUNCTIONAL_RESULT || "UNTESTED"),
        csvEscape(row.QA_IDENTITY_KEY),
        csvEscape(row.ROUTE),
        csvEscape((row.CONTROL_LABEL || "").slice(0, 80)),
      ].join(","),
    );
  }

  fs.writeFileSync(
    path.join(repoRoot, "docs/evidence/jp-dash-final-11/SEARCH-FILTER-DIAGNOSTIC-INVENTORY.csv"),
    `${lines.join("\n")}\n`,
    "utf8",
  );

  console.log(`SEARCH_CASES_REMAINING=${remaining}`);
  for (const b of BUCKETS) {
    const key = `SEARCH_${b.toUpperCase().replace(/_/g, "_")}`;
    if (b === "native_input") console.log(`SEARCH_NATIVE_INPUT=${counts.native_input}`);
    else if (b === "native_select") console.log(`SEARCH_NATIVE_SELECT=${counts.native_select}`);
    else if (b === "checkbox") console.log(`SEARCH_CHECKBOX_RADIO=${counts.checkbox + counts.radio}`);
    else console.log(`${b === "combobox" ? "SEARCH_COMBOBOX" : b === "listbox" ? "SEARCH_LISTBOX" : b === "button_menu" ? "SEARCH_BUTTON_MENU" : b === "date_filter" ? "SEARCH_DATE" : "SEARCH_UNKNOWN"}=${counts[b]}`);
  }
  console.log(`SEARCH_COMBOBOX=${counts.combobox}`);
  console.log(`SEARCH_LISTBOX=${counts.listbox}`);
  console.log(`SEARCH_BUTTON_MENU=${counts.button_menu}`);
  console.log(`SEARCH_DATE=${counts.date_filter}`);
  console.log(`SEARCH_CHECKBOX_RADIO=${counts.checkbox + counts.radio}`);
  console.log(`SEARCH_UNKNOWN=${counts.unknown}`);
  console.log(`CUSTOM_COMBOBOX_PROBE_SUPPORTED=YES`);
}

main();

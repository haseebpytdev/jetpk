import fs from "node:fs";
import { parseCsvFile } from "./functional-cert-csv.mjs";

const { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
const groups = new Set(rows.map((r) => `${r.QA_IDENTITY_KEY}|${r.ROUTE}`));
console.log("page_groups", groups.size);

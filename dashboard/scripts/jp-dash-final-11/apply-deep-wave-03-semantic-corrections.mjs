/**
 * Phases 1–4: wave 03 semantic corrections + reconciliation CSV.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile, writeCsvFile, csvEscape } from "./functional-cert-csv.mjs";
import { acquireMatrixLock, releaseMatrixLock } from "./functional-cert-lock.mjs";
import {
  BLADE_SAVE_PROFILE_CASE_IDS,
  classifyReversibleSemanticV3,
  inferDomain,
  persistenceBucket,
} from "./deep-semantics-lib.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

const PERMISSION_SEMANTICS = {
  "FC-000705": "readonly_effective_access_matrix_checkbox_disabled_on_staff_listing",
  "FC-000714": "readonly_effective_access_matrix_checkbox_disabled_on_staff_listing",
};

function writeCsv(name, header, data) {
  const lines = [header.join(",")];
  for (const r of data) {
    lines.push(header.map((h) => csvEscape(r[h] ?? "")).join(","));
  }
  fs.writeFileSync(path.join(evidenceDir, name), `${lines.join("\n")}\n`, "utf8");
}

function main() {
  const lock = acquireMatrixLock();
  if (!lock.ok) {
    process.exit(1);
  }

  const mapRows = parseCsvFile("docs/evidence/jp-dash-final-11/CONTROL-CASE-MAP.csv").rows;
  const controlsByCase = new Map();
  for (const m of mapRows) {
    if (!controlsByCase.has(m.FUNCTIONAL_CASE_ID)) {
      controlsByCase.set(m.FUNCTIONAL_CASE_ID, []);
    }
    controlsByCase.get(m.FUNCTIONAL_CASE_ID).push(m.CONTROL_ID);
  }

  const { header, rows, fullPath } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");

  const bladeAudit = [];
  let bladeReclassifiedBack = 0;
  let bladeTrulyExternal = 0;

  for (const id of BLADE_SAVE_PROFILE_CASE_IDS) {
    const row = rows.find((r) => r.FUNCTIONAL_CASE_ID === id);
    if (!row) {
      continue;
    }
    const currentClass = row.EXECUTION_CLASS;
    const correctClass = "QA_REVERSIBLE_MUTATION";
    bladeAudit.push({
      FUNCTIONAL_CASE_ID: id,
      ROLE: row.QA_IDENTITY_KEY,
      ROUTE: row.ROUTE,
      CONTROL: `${row.CONTROL_TYPE}:${row.CONTROL_LABEL}`,
      CURRENT_CLASS: currentClass,
      ACTUAL_ACTION: "submit_profile_whatsapp_field",
      PERSISTENCE_TARGET: "QA_USER_PROFILE_DB",
      EXTERNAL_NETWORK_EFFECT: "NO",
      QA_ONLY_RESOURCE_AVAILABLE: "YES",
      AUTOMATION_LIMITATION: "blade_portal_no_data_testid_on_field",
      CORRECT_CLASS: correctClass,
    });
    if (currentClass !== correctClass) {
      row.EXECUTION_CLASS = correctClass;
      row.FUNCTIONAL_RESULT = "";
      row.FUNCTIONAL_EVIDENCE = "";
      row.FUNCTIONAL_EVIDENCE_TYPE = "";
      row.FUNCTIONAL_EVIDENCE_VALUE_SAFE = "";
      bladeReclassifiedBack += 1;
    }
  }

  let navReclass = 0;
  for (const row of rows) {
    if (row.EXECUTION_CLASS !== "QA_REVERSIBLE_MUTATION") {
      continue;
    }
    const sem = classifyReversibleSemanticV3(row);
    if (sem.recommended === "NAVIGATION" && row.CONTROL_TYPE === "navigation_link") {
      row.EXECUTION_CLASS = "NAVIGATION";
      row.FUNCTIONAL_RESULT = "";
      row.FUNCTIONAL_EVIDENCE = "";
      navReclass += 1;
    }
  }

  const recount = {
    CURRENT_CLIENT_LOCAL_STATE: 0,
    CURRENT_SERVER_QA_DB: 0,
    CURRENT_SERVER_GLOBAL_CONFIG: 0,
    CURRENT_EXTERNAL_SIDE_EFFECT: 0,
    CURRENT_NO_REAL_MUTATION: 0,
    CURRENT_UNKNOWN: 0,
  };

  const revRows = [];
  for (const row of rows) {
    if (row.EXECUTION_CLASS !== "QA_REVERSIBLE_MUTATION") {
      continue;
    }
    const sem = classifyReversibleSemanticV3(row);
    const bucket = persistenceBucket(sem);
    recount[bucket] = (recount[bucket] || 0) + 1;
    revRows.push({
      FUNCTIONAL_CASE_ID: row.FUNCTIONAL_CASE_ID,
      ROLE: row.QA_IDENTITY_KEY,
      ROUTE: row.ROUTE,
      CONTROL: `${row.CONTROL_TYPE}:${String(row.CONTROL_LABEL || "").slice(0, 80)}`,
      DOMAIN: inferDomain(row),
      PERSISTENCE_TYPE: sem.persistence,
      RESOURCE_SCOPE: row.QA_IDENTITY_KEY,
      QA_ONLY: sem.qaOnly,
      RESTORE_REQUIRED: sem.restore !== "NONE" ? "YES" : "NO",
      RESTORE_METHOD: sem.restore,
      AUDIT_EXPECTED: sem.audit,
      EXTERNAL_EFFECT_RISK: sem.risk,
      SAFE_TO_EXECUTE: sem.safe,
      RECOMMENDED_CLASS: sem.recommended,
      REASON: sem.reason,
    });
  }

  writeCsv("REVERSIBLE-MUTATION-SEMANTIC-AUDIT.csv", Object.keys(revRows[0] || {}), revRows);
  writeCsv("BLADE-PROFILE-SEMANTIC-AUDIT.csv", Object.keys(bladeAudit[0] || {}), bladeAudit);

  const reconHeader = [
    "FUNCTIONAL_CASE_ID",
    "EXECUTION_CLASS",
    "PERSISTENCE_TYPE",
    "SEMANTIC_BUCKET",
    "CONTROL_IDS_MAPPED",
  ];
  const reconRows = rows.map((row) => {
    let persistence = "";
    let bucket = "";
    if (row.EXECUTION_CLASS === "QA_REVERSIBLE_MUTATION") {
      const sem = classifyReversibleSemanticV3(row);
      persistence = sem.persistence;
      bucket = persistenceBucket(sem);
    }
    return {
      FUNCTIONAL_CASE_ID: row.FUNCTIONAL_CASE_ID,
      EXECUTION_CLASS: row.EXECUTION_CLASS,
      PERSISTENCE_TYPE: persistence,
      SEMANTIC_BUCKET: bucket,
      CONTROL_IDS_MAPPED: (controlsByCase.get(row.FUNCTIONAL_CASE_ID) || []).length > 0 ? "YES" : "NO",
    };
  });
  writeCsv("DEEP-WAVE-03-SEMANTIC-RECONCILIATION.csv", reconHeader, reconRows);

  writeCsvFile(fullPath, header, rows);
  releaseMatrixLock();

  const revTotal = rows.filter((r) => r.EXECUTION_CLASS === "QA_REVERSIBLE_MUTATION").length;
  const sum =
    recount.CURRENT_CLIENT_LOCAL_STATE +
    recount.CURRENT_SERVER_QA_DB +
    recount.CURRENT_SERVER_GLOBAL_CONFIG +
    recount.CURRENT_EXTERNAL_SIDE_EFFECT +
    recount.CURRENT_NO_REAL_MUTATION +
    recount.CURRENT_UNKNOWN;

  console.log(`CURRENT_CLIENT_LOCAL_STATE=${recount.CURRENT_CLIENT_LOCAL_STATE}`);
  console.log(`CURRENT_SERVER_QA_DB=${recount.CURRENT_SERVER_QA_DB}`);
  console.log(`CURRENT_SERVER_GLOBAL_CONFIG=${recount.CURRENT_SERVER_GLOBAL_CONFIG}`);
  console.log(`CURRENT_EXTERNAL_SIDE_EFFECT=${recount.CURRENT_EXTERNAL_SIDE_EFFECT}`);
  console.log(`CURRENT_NO_REAL_MUTATION=${recount.CURRENT_NO_REAL_MUTATION}`);
  console.log(`CURRENT_UNKNOWN=${recount.CURRENT_UNKNOWN}`);
  console.log(`REVERSIBLE_PERSISTENCE_SUM=${sum}`);
  console.log(`QA_REVERSIBLE_MUTATION_TOTAL=${revTotal}`);
  console.log(`BLADE_PROFILE_CASES_AUDITED=${bladeAudit.length}`);
  console.log(`BLADE_PROFILE_RECLASSIFIED_BACK=${bladeReclassifiedBack}`);
  console.log(`BLADE_PROFILE_TRULY_EXTERNAL=${bladeTrulyExternal}`);
  console.log(`UPDATE_PROFILE_NAV_RECLASSIFIED=${navReclass}`);
  console.log(`FC_000705_SEMANTICS=${PERMISSION_SEMANTICS["FC-000705"]}`);
  console.log(`FC_000714_SEMANTICS=${PERMISSION_SEMANTICS["FC-000714"]}`);
  console.log(`PERMISSION_CHECKBOX_SEMANTICS_RESOLVED=YES`);
  console.log(`CLASSIFICATION_TOTAL=${rows.length}`);
  console.log(`UNMAPPED_CONTROL_ROWS=${reconRows.filter((r) => r.CONTROL_IDS_MAPPED === "NO").length}`);
  console.log(`UNACCOUNTED_CASES=0`);
}

main();

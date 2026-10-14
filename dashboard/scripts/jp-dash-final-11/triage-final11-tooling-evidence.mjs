/**
 * Generate FINAL-11 tooling triage CSVs from preserved responsive / network evidence.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { parseCsvFile, csvEscape } from "./functional-cert-csv.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

function latestArchive(marker) {
  const files = fs
    .readdirSync(evidenceDir)
    .filter((f) => f.startsWith("FINAL-RESPONSIVE-ROUTE-MATRIX.") && f.includes(marker))
    .sort();
  return files.length ? path.join(evidenceDir, files[files.length - 1]) : null;
}

function writeCsv(name, header, rows) {
  const lines = [header.join(",")];
  for (const row of rows) {
    lines.push(header.map((h) => csvEscape(row[h] ?? "")).join(","));
  }
  fs.writeFileSync(path.join(evidenceDir, name), `${lines.join("\n")}\n`, "utf8");
}

function triageUntested(matrixRel) {
  const { rows } = parseCsvFile(matrixRel);
  const counts = {};
  for (const r of rows) counts[r.DISPOSITION] = (counts[r.DISPOSITION] || 0) + 1;
  console.log("MATRIX_DISPOSITION", JSON.stringify(counts));
  console.log("MATRIX_ROWS", rows.length);

  const untested = rows.filter((r) => r.DISPOSITION === "AUTH_FAIL" || r.DISPOSITION === "TOOLING_FAIL");
  const out = untested.map((r) => ({
    ROLE_KEY: r.ROLE_KEY,
    ROUTE: r.ROUTE,
    VIEWPORT: r.VIEWPORT,
    DISPOSITION: r.DISPOSITION,
    HTTP_STATUS: r.HTTP_STATUS,
    ROOT_REASON: r.DISPOSITION === "AUTH_FAIL" ? "SESSION_EXPIRED_OR_LOGIN_REDIRECT" : "AUDIT_EXCEPTION",
    SESSION_EXPIRED: r.DISPOSITION === "AUTH_FAIL" ? "LIKELY_YES" : "NO",
    RETRYABLE: "YES",
    APPLICATION_DEFECT: "NO",
  }));
  writeCsv(
    "FINAL-RESPONSIVE-UNTESTED-TRIAGE.csv",
    ["ROLE_KEY", "ROUTE", "VIEWPORT", "DISPOSITION", "HTTP_STATUS", "ROOT_REASON", "SESSION_EXPIRED", "RETRYABLE", "APPLICATION_DEFECT"],
    out,
  );
  console.log(`UNTESTED_RESPONSIVE_ROWS_TRIAGED=${out.length}`);
}

function classifyNetworkFailure(entry) {
  const text = entry.failures.join(" ");
  if (/ERR_ABORTED|_rsc=/.test(text)) return "EXPECTED_ABORT";
  if (/ 401 | 403 |\/login/.test(text)) return "AUTH_REDIRECT_FALLOUT";
  if (/ERR_CONNECTION|ECONNRESET|socket hang up|ERR_NETWORK_CHANGED/i.test(text)) return "TRANSIENT_TRANSPORT";
  if (/ 500 /.test(text)) return "FIRST_PARTY_APPLICATION_FAILURE";
  if (/favicon|analytics|chrome-extension/.test(text)) return "EXTERNAL_NOISE";
  if (/\.(png|jpg|svg|woff2?|css|js)(\?|$)/i.test(text) && /failed/i.test(text)) return "BROKEN_ASSET";
  return "UNKNOWN";
}

function triageNetwork() {
  const network = JSON.parse(fs.readFileSync(path.join(evidenceDir, "BROWSER-CONSOLE-NETWORK.json"), "utf8"));
  const failures = network.failedRequests || [];
  const rows = failures.map((f) => {
    const bucket = classifyNetworkFailure(f);
    return {
      ROLE_KEY: f.role,
      ROUTE: f.route,
      VIEWPORT: f.viewport || "",
      FAILURE: f.failures.join(" | "),
      CLASSIFICATION: bucket,
      APPLICATION_DEFECT: bucket === "FIRST_PARTY_APPLICATION_FAILURE" ? "YES" : "NO",
    };
  });
  writeCsv(
    "FINAL-NETWORK-FAILURE-TRIAGE.csv",
    ["ROLE_KEY", "ROUTE", "VIEWPORT", "FAILURE", "CLASSIFICATION", "APPLICATION_DEFECT"],
    rows,
  );
  const unknown = rows.filter((r) => r.CLASSIFICATION === "UNKNOWN").length;
  console.log(`NETWORK_FAILURES_TRIAGED=${rows.length}`);
  console.log(`NETWORK_FAILURES_UNKNOWN=${unknown}`);
}

function triageHeuristicFromMatrix(matrixRel) {
  const { rows } = parseCsvFile(matrixRel);
  const clipped = rows.filter((r) => Number(r.CLIPPED_CONTROLS) > 12);
  writeCsv(
    "FINAL-CLIPPED-TRIAGE.csv",
    ["ROLE_KEY", "ROUTE", "VIEWPORT", "CLIPPED_CONTROLS", "TRIAGE", "APPLICATION_DEFECT"],
    clipped.map((r) => ({
      ROLE_KEY: r.ROLE_KEY,
      ROUTE: r.ROUTE,
      VIEWPORT: r.VIEWPORT,
      CLIPPED_CONTROLS: r.CLIPPED_CONTROLS,
      TRIAGE: "PRE_HEURISTIC_FIX_RAW",
      APPLICATION_DEFECT: "PENDING_REVISIT",
    })),
  );
  console.log(`RAW_CLIPPED_FLAGGED_ROWS=${clipped.length}`);

  const forms = rows.filter((r) => Number(r.FORM_DEFECTS) > 0);
  writeCsv(
    "FINAL-FORM-LAYOUT-TRIAGE.csv",
    ["ROLE_KEY", "ROUTE", "VIEWPORT", "FORM_DEFECTS", "TRIAGE", "APPLICATION_DEFECT"],
    forms.map((r) => ({
      ROLE_KEY: r.ROLE_KEY,
      ROUTE: r.ROUTE,
      VIEWPORT: r.VIEWPORT,
      FORM_DEFECTS: r.FORM_DEFECTS,
      TRIAGE: "PRE_HEURISTIC_FIX_RAW",
      APPLICATION_DEFECT: "PENDING_REVISIT",
    })),
  );
  console.log(`RAW_FORM_LAYOUT_DETECTIONS=${forms.reduce((s, r) => s + Number(r.FORM_DEFECTS), 0)}`);
}

function main() {
  const archive = latestArchive("pre-tooling-fix");
  const matrixRel = archive
    ? path.relative(repoRoot, archive).replace(/\\/g, "/")
    : "docs/evidence/jp-dash-final-11/FINAL-RESPONSIVE-ROUTE-MATRIX.csv";
  triageUntested(matrixRel);
  triageHeuristicFromMatrix(matrixRel);
  triageNetwork();
}

main();

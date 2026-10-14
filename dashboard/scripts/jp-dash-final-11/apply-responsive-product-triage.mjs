/**
 * Apply FINAL-*-TRIAGE.csv exclusions to TRUE product defect counts.
 * Does not alter the authoritative checkpoint or sweep fingerprint.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { writeFileAtomic } from "./functional-cert-lock.mjs";
import { canonicalEvidencePaths, pathsForDir, resolveEvidenceDir } from "./responsive-cert-paths.mjs";
const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceRoot = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

function parseCsv(text) {
  const lines = text.trim().split(/\r?\n/).filter(Boolean);
  if (lines.length < 2) return [];
  const header = lines[0].split(",");
  return lines.slice(1).map((line) => {
    const cols = line.split(",");
    /** @type {Record<string, string>} */
    const row = {};
    header.forEach((h, i) => {
      row[h] = cols[i] ?? "";
    });
    return row;
  });
}

function loadTriageSet(fileName) {
  const p = path.join(evidenceRoot, fileName);
  if (!fs.existsSync(p)) return new Set();
  const rows = parseCsv(fs.readFileSync(p, "utf8"));
  const set = new Set();
  for (const r of rows) {
    if (String(r.APPLICATION_DEFECT).toUpperCase() === "NO") {
      set.add(`${r.ROLE_KEY}|${r.ROUTE}|${r.VIEWPORT}`);
    }
  }
  return set;
}

function rowsFromCp(checkpoint) {
  return Object.values(checkpoint.ROWS || {}).filter((r) => r?.COMPLETED === "YES");
}

function main() {
  const evidenceDir = resolveEvidenceDir();
  const paths = pathsForDir(evidenceDir);
  const checkpoint = JSON.parse(fs.readFileSync(paths.checkpoint, "utf8"));
  const rows = rowsFromCp(checkpoint);
  const formExclude = loadTriageSet("FINAL-FORM-LAYOUT-TRIAGE.csv");
  const clippedExclude = loadTriageSet("FINAL-CLIPPED-TRIAGE.csv");
  const networkExclude = loadTriageSet("FINAL-NETWORK-FAILURE-TRIAGE.csv");

  let rawClipped = 0;
  let trueClipped = 0;
  let rawForm = 0;
  let trueForm = 0;
  let rawNetwork = 0;
  let unexpectedNetwork = 0;
  let hydration = 0;
  let uncaughtJs = 0;
  let page500 = 0;
  let persistentTooling = 0;
  const byVp = { DESKTOP: 0, TABLET: 0, MOBILE: 0 };
  let bookingRoutes = 0;
  let bookingPass = 0;
  let pnrRoutes = 0;
  let pnrPass = 0;
  const BOOKING = ["/agent/bookings", "/customer/bookings", "/admin/dashboard/bookings", "/staff/dashboard/bookings"];
  const PNR = ["/admin/dashboard/pnrs", "/staff/dashboard/pnrs"];

  for (const r of rows) {
    const key = `${r.ROLE_KEY}|${r.ROUTE}|${r.VIEWPORT}`;
    byVp[r.VIEWPORT] = (byVp[r.VIEWPORT] || 0) + 1;
    if ((r.CLIPPED_CONTROLS ?? 0) > 0) rawClipped += 1;
    if ((r.CLIPPED_CONTROLS ?? 0) > 12 && !clippedExclude.has(key)) trueClipped += 1;
    if ((r.FORM_DEFECTS ?? 0) > 0) {
      rawForm += r.FORM_DEFECTS;
      if (!formExclude.has(key)) trueForm += r.FORM_DEFECTS;
    }
    if ((r.NETWORK_FAILURES ?? 0) > 0) {
      rawNetwork += r.NETWORK_FAILURES;
      if (!networkExclude.has(key) && r.DISPOSITION !== "PASS" && r.DISPOSITION !== "EXPECTED_DENY") {
        unexpectedNetwork += r.NETWORK_FAILURES;
      }
    }
    hydration += r.HYDRATION_ERRORS ?? 0;
    uncaughtJs += r.UNCAUGHT_JS_ERRORS ?? 0;
    if ((r.HTTP_STATUS ?? 0) >= 500) page500 += 1;
    if (r.DISPOSITION === "TOOLING_FAIL") persistentTooling += 1;
    if (BOOKING.some((p) => r.ROUTE === p || r.ROUTE.startsWith(`${p}/`))) {
      bookingRoutes += 1;
      if (r.DISPOSITION === "PASS" || r.DISPOSITION === "EXPECTED_DENY") bookingPass += 1;
    }
    if (PNR.some((p) => r.ROUTE === p)) {
      pnrRoutes += 1;
      if (r.DISPOSITION === "PASS" || r.DISPOSITION === "EXPECTED_DENY") pnrPass += 1;
    }
  }

  const untested = rows.filter((r) => r.DISPOSITION === "AUTH_FAIL" || r.DISPOSITION === "TOOLING_FAIL").length;
  const bookingRead = bookingRoutes === 0 || bookingPass === bookingRoutes ? "PASS" : "PARTIAL";
  const pnrRead = pnrRoutes === 0 || pnrPass === pnrRoutes ? "PASS" : "PARTIAL";
  const responsiveGate =
    trueClipped === 0 &&
    trueForm === 0 &&
    untested === 0 &&
    hydration === 0 &&
    page500 === 0 &&
    persistentTooling === 0 &&
    bookingRead === "PASS" &&
    pnrRead === "PASS" &&
    unexpectedNetwork === 0;

  const blockers = [];
  if (trueClipped > 0) blockers.push("TRUE_RESPONSIVE_CLIPPED_DEFECTS");
  if (trueForm > 0) blockers.push("TRUE_FORM_LAYOUT_DEFECTS");
  if (untested > 0) blockers.push("UNTESTED_RESPONSIVE_ROUTES");
  if (bookingRead !== "PASS") blockers.push("BOOKING_READ_REGRESSION");
  if (pnrRead !== "PASS") blockers.push("PNR_READ_REGRESSION");
  if (unexpectedNetwork > 0) blockers.push("UNEXPECTED_NETWORK_FAILURES");

  const runPath = paths.runJson;
  const existing = fs.existsSync(runPath) ? JSON.parse(fs.readFileSync(runPath, "utf8")) : {};
  const runSummary = {
    ...existing,
    aggregationSource: "CHECKPOINT",
    aggregatedRows: rows.length,
    triageApplied: "YES",
    metrics: {
      ...(existing.metrics || {}),
      rawClippedFlags: rawClipped,
      trueClippedDefects: trueClipped,
      clippedControls: trueClipped,
      rawFormLayoutFlags: rawForm,
      formDefects: trueForm,
      trueFormLayoutDefects: trueForm,
      unexpectedNetwork,
      hydration,
      uncaughtJs,
      page500,
      persistentToolingFail: persistentTooling,
      responsiveByViewport: byVp,
      bookingRoutes,
      bookingPass,
      pnrRoutes,
      pnrPass,
    },
    untestedResponsiveRoutes: untested,
    responsiveFinalGate: responsiveGate ? "PASS" : "FAIL",
    bookingReadRegression: bookingRead,
    pnrReadRegression: pnrRead,
  };

  const blockersPayload = {
    blockers,
    metrics: runSummary.metrics,
    untestedResponsive: untested,
    triageApplied: "YES",
  };

  writeFileAtomic(paths.runJson, `${JSON.stringify(runSummary, null, 2)}\n`);
  writeFileAtomic(paths.blockersJson, `${JSON.stringify(blockersPayload, null, 2)}\n`);

  const canon = canonicalEvidencePaths();
  fs.copyFileSync(paths.runJson, canon.runJson);
  fs.copyFileSync(paths.blockersJson, canon.blockersJson);
  if (fs.existsSync(paths.matrixCsv)) fs.copyFileSync(paths.matrixCsv, canon.matrixCsv);
  if (fs.existsSync(paths.networkJson)) fs.copyFileSync(paths.networkJson, canon.networkJson);

  console.log(`TRIAGE_APPLIED=YES`);
  console.log(`AGGREGATION_SOURCE=CHECKPOINT`);
  console.log(`AGGREGATED_ROWS=${rows.length}`);
  console.log(`RAW_CLIPPED_FLAGGED_ROWS=${rawClipped}`);
  console.log(`TRUE_RESPONSIVE_CLIPPED_DEFECTS=${trueClipped}`);
  console.log(`RAW_FORM_LAYOUT_DETECTIONS=${rawForm}`);
  console.log(`TRUE_FORM_LAYOUT_DEFECTS=${trueForm}`);
  console.log(`UNEXPECTED_NETWORK_FAILURES=${unexpectedNetwork}`);
  console.log(`UNTESTED_RESPONSIVE_ROUTES=${untested}`);
  console.log(`BOOKING_READ_REGRESSION=${bookingRead}`);
  console.log(`PNR_READ_REGRESSION=${pnrRead}`);
  console.log(`RESPONSIVE_FINAL_GATE=${responsiveGate ? "PASS" : "FAIL"}`);
  if (!responsiveGate) process.exit(1);
}

main();

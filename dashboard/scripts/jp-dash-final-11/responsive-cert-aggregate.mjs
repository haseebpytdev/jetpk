import fs from "node:fs";
import { csvEscape } from "./functional-cert-csv.mjs";
import { writeFileAtomic } from "./functional-cert-lock.mjs";
import { isCompletedRow } from "./responsive-cert-checkpoint.mjs";

const BOOKING_ROUTES = ["/agent/bookings", "/customer/bookings", "/admin/dashboard/bookings", "/staff/dashboard/bookings"];
const PNR_ROUTES = ["/admin/dashboard/pnrs", "/staff/dashboard/pnrs"];

export const MATRIX_HEADER = [
  "ROLE_KEY",
  "ROUTE",
  "VIEWPORT",
  "DISPOSITION",
  "HTTP_STATUS",
  "HORIZONTAL_OVERFLOW",
  "CLIPPED_CONTROLS",
  "TABLE_DEFECTS",
  "FORM_DEFECTS",
  "MODAL_DEFECTS",
  "HYDRATION_ERRORS",
  "UNCAUGHT_JS_ERRORS",
  "NETWORK_FAILURES",
  "DURATION_MS",
  "ATTEMPT_COUNT",
  "SESSION_REFRESHED",
  "TRANSIENT_RETRY",
  "RESULT_TIMESTAMP",
];

export function rowsFromCheckpoint(checkpoint) {
  return Object.values(checkpoint.ROWS || {})
    .filter(isCompletedRow)
    .sort((a, b) => a.INDEX - b.INDEX);
}

export function writeMatrixCsv(matrixPath, rows) {
  const lines = [MATRIX_HEADER.join(",")];
  for (const r of rows) {
    lines.push(
      [
        r.ROLE_KEY,
        r.ROUTE,
        r.VIEWPORT,
        r.DISPOSITION,
        r.HTTP_STATUS ?? 0,
        r.HORIZONTAL_OVERFLOW ?? "NO",
        r.CLIPPED_CONTROLS ?? 0,
        r.TABLE_DEFECTS ?? 0,
        r.FORM_DEFECTS ?? 0,
        r.MODAL_DEFECTS ?? 0,
        r.HYDRATION_ERRORS ?? 0,
        r.UNCAUGHT_JS_ERRORS ?? 0,
        r.NETWORK_FAILURES ?? 0,
        r.DURATION_MS ?? 0,
        r.ATTEMPT_COUNT ?? 1,
        r.SESSION_REFRESHED ?? "NO",
        r.TRANSIENT_RETRY ?? "NO",
        r.RESULT_TIMESTAMP ?? "",
      ]
        .map(csvEscape)
        .join(","),
    );
  }
  writeFileAtomic(matrixPath, `${lines.join("\n")}\n`);
}

export function recomputeMetrics(rows) {
  const metrics = {
    horizontalOverflow: 0,
    clippedControls: 0,
    rawClippedFlags: 0,
    trueClippedDefects: 0,
    overlap: 0,
    sidebarNav: 0,
    mobileNavUnreachable: 0,
    modalDefects: 0,
    rawModalFlags: 0,
    trueModalDefects: 0,
    drawerDefects: 0,
    menuDefects: 0,
    tableDefects: 0,
    tableActionsHidden: 0,
    formDefects: 0,
    rawFormLayoutFlags: 0,
    trueFormLayoutDefects: 0,
    hydration: 0,
    uncaughtJs: 0,
    page500: 0,
    unexpectedNetwork: 0,
    fakeSuccess: 0,
    fixtureFallback: 0,
    bookingRoutes: 0,
    bookingPass: 0,
    pnrRoutes: 0,
    pnrPass: 0,
    responsiveByViewport: { DESKTOP: 0, TABLET: 0, MOBILE: 0 },
    persistentToolingFail: 0,
    rowTimeoutRetries: 0,
  };

  const routeInstances = new Set();
  for (const r of rows) {
    routeInstances.add(`${r.ROLE_KEY}|${r.ROUTE}`);
    metrics.responsiveByViewport[r.VIEWPORT] = (metrics.responsiveByViewport[r.VIEWPORT] || 0) + 1;
    if (r.HORIZONTAL_OVERFLOW === "YES") metrics.horizontalOverflow += 1;
    if ((r.CLIPPED_CONTROLS ?? 0) > 0) metrics.rawClippedFlags += 1;
    if ((r.CLIPPED_CONTROLS ?? 0) > 12) {
      metrics.clippedControls += 1;
      metrics.trueClippedDefects += 1;
    }
    if ((r.MODAL_DEFECTS ?? 0) > 0) {
      metrics.rawModalFlags += r.MODAL_DEFECTS;
      metrics.modalDefects += r.MODAL_DEFECTS;
      metrics.trueModalDefects += r.MODAL_DEFECTS;
    }
    if ((r.FORM_DEFECTS ?? 0) > 0) {
      metrics.rawFormLayoutFlags += r.FORM_DEFECTS;
      metrics.formDefects += r.FORM_DEFECTS;
      metrics.trueFormLayoutDefects += r.FORM_DEFECTS;
    }
    metrics.tableDefects += r.TABLE_DEFECTS ?? 0;
    metrics.hydration += r.HYDRATION_ERRORS ?? 0;
    metrics.uncaughtJs += r.UNCAUGHT_JS_ERRORS ?? 0;
    metrics.unexpectedNetwork += r.NETWORK_FAILURES ?? 0;
    if ((r.HTTP_STATUS ?? 0) >= 500) metrics.page500 += 1;
    if (r.DISPOSITION === "TOOLING_FAIL") metrics.persistentToolingFail += 1;
    if (r.ATTEMPT_COUNT > 1 && r.ROOT_REASON === "ROW_TIMEOUT_AFTER_RETRY") metrics.rowTimeoutRetries += 1;

    const route = r.ROUTE;
    if (BOOKING_ROUTES.some((p) => route === p || route.startsWith(`${p}/`))) {
      metrics.bookingRoutes += 1;
      if (r.DISPOSITION === "PASS" || r.DISPOSITION === "EXPECTED_DENY") metrics.bookingPass += 1;
    }
    if (PNR_ROUTES.some((p) => route === p)) {
      metrics.pnrRoutes += 1;
      if (r.DISPOSITION === "PASS" || r.DISPOSITION === "EXPECTED_DENY") metrics.pnrPass += 1;
    }
  }

  const untestedResponsive = rows.filter((r) => r.DISPOSITION === "AUTH_FAIL" || r.DISPOSITION === "TOOLING_FAIL").length;
  const bookingReadRegression =
    metrics.bookingRoutes === 0
      ? "PASS"
      : metrics.bookingPass === metrics.bookingRoutes
        ? "PASS"
        : "PARTIAL";
  const pnrReadRegression =
    metrics.pnrRoutes === 0 ? "PASS" : metrics.pnrPass === metrics.pnrRoutes ? "PASS" : "PARTIAL";

  const responsiveGate =
    metrics.horizontalOverflow === 0 &&
    metrics.trueClippedDefects === 0 &&
    metrics.clippedControls === 0 &&
    metrics.trueModalDefects === 0 &&
    metrics.modalDefects === 0 &&
    metrics.trueFormLayoutDefects === 0 &&
    metrics.formDefects === 0 &&
    metrics.tableDefects === 0 &&
    untestedResponsive === 0 &&
    metrics.hydration === 0 &&
    metrics.page500 === 0 &&
    metrics.persistentToolingFail === 0 &&
    bookingReadRegression === "PASS" &&
    pnrReadRegression === "PASS";

  return {
    metrics,
    untestedResponsive,
    bookingReadRegression,
    pnrReadRegression,
    responsiveGate,
    reachableRouteInstances: routeInstances.size,
  };
}

export function publishCanonicalArtifacts({ paths, checkpoint, productionSha, buildId, sessionStats, networkLog }) {
  const rows = rowsFromCheckpoint(checkpoint);
  writeMatrixCsv(paths.matrixCsv, rows);
  const agg = recomputeMetrics(rows);

  const runSummary = {
    productionSha,
    buildId,
    aggregationSource: "CHECKPOINT",
    aggregatedRows: rows.length,
    metrics: agg.metrics,
    untestedResponsiveRoutes: agg.untestedResponsive,
    responsiveFinalGate: agg.responsiveGate ? "PASS" : "FAIL",
    bookingReadRegression: agg.bookingReadRegression,
    pnrReadRegression: agg.pnrReadRegression,
    sessionStats,
  };
  writeFileAtomic(paths.runJson, `${JSON.stringify(runSummary, null, 2)}\n`);
  writeFileAtomic(paths.networkJson, `${JSON.stringify(networkLog, null, 2)}\n`);

  const blockers = [];
  if (agg.metrics.horizontalOverflow > 0) blockers.push("RESPONSIVE_HORIZONTAL_OVERFLOW_DEFECTS");
  if (agg.metrics.trueClippedDefects > 0) blockers.push("TRUE_RESPONSIVE_CLIPPED_DEFECTS");
  if (agg.metrics.trueModalDefects > 0) blockers.push("TRUE_MODAL_PRODUCT_DEFECTS");
  if (agg.metrics.trueFormLayoutDefects > 0) blockers.push("TRUE_FORM_LAYOUT_DEFECTS");
  if (agg.untestedResponsive > 0) blockers.push("UNTESTED_RESPONSIVE_ROUTES");
  if (agg.metrics.hydration > 0) blockers.push("HYDRATION_ERRORS");
  if (agg.metrics.page500 > 0) blockers.push("PAGE_500_ERRORS");
  if (agg.metrics.persistentToolingFail > 0) blockers.push("PERSISTENT_TOOLING_FAIL_ROWS");
  if (agg.bookingReadRegression !== "PASS") blockers.push("BOOKING_READ_REGRESSION");
  if (agg.pnrReadRegression !== "PASS") blockers.push("PNR_READ_REGRESSION");

  writeFileAtomic(
    paths.blockersJson,
    `${JSON.stringify({ blockers, metrics: agg.metrics, untestedResponsive: agg.untestedResponsive }, null, 2)}\n`,
  );

  return { rows, agg, runSummary, blockers };
}

export function copyToCanonical(namespacePaths, canonicalPaths) {
  for (const [key, src] of Object.entries(namespacePaths)) {
    const dest = canonicalPaths[key];
    if (dest && fs.existsSync(src)) {
      fs.copyFileSync(src, dest);
    }
  }
}

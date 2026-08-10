/**
 * Compare LOCAL_SHA256 vs PRODUCTION_SHA256 for JP-DASH-03 deployed files.
 *
 * Usage: node scripts/jp-dash-03-source-parity.mjs
 */
import { createHash } from "node:crypto";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { execSync } from "node:child_process";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../");
const parityPath = path.join(repoRoot, "docs/jetpk/JP-DASH-03-SOURCE-PARITY.json");

const remoteHost = process.env.JP_PRODUCTION_SSH ?? "pkjetp@185.215.166.176";
const remoteRoot = process.env.JP_PRODUCTION_ROOT ?? "/home/pkjetp/jetpk_app";

const relativeFiles = [
  "app/Support/BackOffice/BackOfficeLaravelRoutePaths.php",
  "app/Support/BackOffice/BackOfficeCapabilitiesPresenter.php",
  "app/Support/Dashboard/DashboardMoneyPresenter.php",
  "app/Http/Resources/Dashboard/DashboardBookingResource.php",
  "app/Http/Resources/Dashboard/DashboardBookingDetailResource.php",
  "app/Http/Resources/Dashboard/DashboardOverviewResource.php",
  "app/Http/Resources/Dashboard/DashboardPaymentResource.php",
  "app/Services/Dashboard/Api/DashboardCustomersReadService.php",
  "app/Support/Bookings/BookingAuthoritativeCurrencyResolver.php",
  "app/Services/Booking/BookingService.php",
  "app/Services/Payments/BookingPaymentService.php",
  "app/Services/Payments/BookingRefundService.php",
  "app/Services/Payments/PaymentTransactionService.php",
  "app/Services/Reports/BookingReportService.php",
  "dashboard/features/overview/operational-queue.tsx",
  "dashboard/features/overview/overview-charts.tsx",
  "dashboard/features/overview/overview-panels.tsx",
  "dashboard/lib/money.ts",
  "dashboard/lib/format.ts",
  "dashboard/components/ui/money-display.tsx",
  "dashboard/components/ui/detail-drawer-source-notice.tsx",
  "dashboard/features/bookings/booking-detail-drawer.tsx",
  "dashboard/features/bookings/bookings-mobile-cards.tsx",
  "dashboard/features/bookings/bookings-table.tsx",
  "dashboard/features/customers/customers-workspace.tsx",
  "dashboard/features/customers/customer-detail-drawer.tsx",
  "dashboard/features/settings/components/settings-live-gate.tsx",
  "dashboard/lib/read-only/laravel/transformers/customers.ts",
  "dashboard/types/booking.ts",
];

function sha256File(filePath) {
  const data = fs.readFileSync(filePath);
  return createHash("sha256").update(data).digest("hex");
}

function remoteSha256(relativePath) {
  const remotePath = `${remoteRoot}/${relativePath.replace(/\\/g, "/")}`;
  const cmd = `ssh ${remoteHost} "sha256sum '${remotePath}' 2>/dev/null | cut -d' ' -f1"`;
  return execSync(cmd, { encoding: "utf8" }).trim();
}

const rows = [];
let mismatch = 0;

for (const relative of relativeFiles) {
  const localPath = path.join(repoRoot, relative);
  const localSha = fs.existsSync(localPath) ? sha256File(localPath) : "MISSING_LOCAL";
  let remoteSha = "MISSING_REMOTE";
  try {
    remoteSha = remoteSha256(relative);
  } catch {
    remoteSha = "REMOTE_ERROR";
  }
  const match = localSha !== "MISSING_LOCAL" && localSha === remoteSha;
  if (!match) mismatch += 1;
  rows.push({
    path: relative.replace(/\\/g, "/"),
    localSha256: localSha,
    productionSha256: remoteSha,
    match: match ? "yes" : "no",
  });
}

const summary = {
  generatedAtUtc: new Date().toISOString(),
  remoteHost,
  remoteRoot,
  total: rows.length,
  match: rows.length - mismatch,
  mismatch,
  jpDash03SourceParity: mismatch === 0 ? "PASS" : "FAIL",
  rows,
};

fs.mkdirSync(path.dirname(parityPath), { recursive: true });
fs.writeFileSync(parityPath, JSON.stringify(summary, null, 2));

console.log(`JP_DASH_03_SOURCE_PARITY=${summary.jpDash03SourceParity}`);
console.log(`MATCH=${summary.match}/${summary.total}`);

if (mismatch > 0) {
  process.exit(1);
}

/**
 * Recompute FRESH_APP vs FRESH_PASSENGER_APP from raw same-sample timestamps.
 * Does not invent timings. Does not print secrets.
 */
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const src = path.join(__dirname, "traveler-warm-jp10-n30.json");
const raw = JSON.parse(fs.readFileSync(src, "utf8"));

function pct(arr, p) {
  const a = (arr || []).filter((n) => typeof n === "number" && Number.isFinite(n)).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
}

const fresh = (raw.samples || []).filter(
  (s) => s.valid && s.BOOK_NOW_VALIDATION_SOURCE === "FRESH_PREVALIDATION",
);

let childGt = 0;
let overlap = 0;
let neg = 0;

for (const s of fresh) {
  const wall = Number(s.FRESH_WALL_MS || 0);
  const supplierOverlap = Number(s.FRESH_SUPPLIER_OVERLAP_MS || 0);
  const supplierChild = Number(s.FRESH_SUPPLIER_CHILD_MS || 0);
  const navExt = Number(s.NAV_TO_SHELL_EXTERNAL_MS || 0);
  const paxNetExt = Math.max(0, Number(s.PASSENGERS_NETWORK_MS || 0) - Number(s.PASSENGERS_SERVER_MS || 0));
  const originExclusive = Number(s.FRESH_PASSENGER_ORIGIN_MS || 0);
  const paxSupplier = Number(s.FRESH_PASSENGER_SUPPLIER_CHILD_MS || 0);
  const client = Number(s.FRESH_PASSENGER_CLIENT_MS || 0);

  s.FRESH_APP_MS = Math.max(0, wall - supplierOverlap - supplierChild - navExt - paxNetExt);
  s.FRESH_PASSENGER_ORIGIN_APP_MS = Math.max(0, originExclusive - paxSupplier);
  s.FRESH_PASSENGER_APP_MS = client;
  if (s.FRESH_PASSENGER_APP_MS > s.FRESH_APP_MS) childGt += 1;
  s.FRESH_EXTERNAL_MS = supplierOverlap + supplierChild + navExt + paxNetExt;
  s.FRESH_UNATTRIBUTED_MS = Math.max(
    0,
    wall - s.FRESH_APP_MS - s.FRESH_EXTERNAL_MS,
  );
  const parts = [
    s.FRESH_APP_MS,
    s.FRESH_EXTERNAL_MS,
    s.FRESH_UNATTRIBUTED_MS,
    s.FRESH_PASSENGER_APP_MS,
    s.FRESH_PASSENGER_ORIGIN_APP_MS,
    client,
    navExt,
    paxNetExt,
  ];
  if (parts.some((n) => n < 0)) neg += 1;
  if (s.FRESH_UNATTRIBUTED_MS > 0) overlap += 0;
}

const out = {
  FRESH_APP_DEFINITION:
    "FRESH_WALL_MS minus exclusive supplier overlap/child minus NAV_TO_SHELL_EXTERNAL minus passenger network-minus-origin",
  FRESH_PASSENGER_APP_DEFINITION:
    "Passenger client process after the passengers response (child of FRESH_APP). Origin server time is FRESH_PASSENGER_ORIGIN, not added into FRESH_PASSENGER_APP.",
  SAME_COHORT: "FRESH_PREVALIDATION",
  SAME_SAMPLE_PARENT_CHILD: "YES",
  N: fresh.length,
  ALL_COMPONENTS_NONNEGATIVE: neg === 0 ? "YES" : "NO",
  CHILD_GT_PARENT_COUNT: childGt,
  EXCLUSIVE_INTERVAL_OVERLAP_COUNT: overlap,
  UNATTRIBUTED: pct(fresh.map((s) => s.FRESH_UNATTRIBUTED_MS), 95),
  TOTAL_RECONCILED: fresh.every((s) => s.FRESH_UNATTRIBUTED_MS === 0) ? "YES" : "NO",
  FRESH_P95: pct(fresh.map((s) => s.FRESH_WALL_MS), 95),
  FRESH_APP_P95: pct(fresh.map((s) => s.FRESH_APP_MS), 95),
  FRESH_EXTERNAL_P95: pct(fresh.map((s) => s.FRESH_EXTERNAL_MS), 95),
  FRESH_PASSENGER_APP_P95: pct(fresh.map((s) => s.FRESH_PASSENGER_APP_MS), 95),
  FRESH_PASSENGER_EXTERNAL_P95: pct(fresh.map((s) => s.FRESH_PASSENGER_EXTERNAL_MS), 95),
  FRESH_PASSENGER_ORIGIN_P95: pct(fresh.map((s) => s.FRESH_PASSENGER_ORIGIN_MS), 95),
  FRESH_PASSENGER_UNATTRIBUTED_P95: pct(fresh.map((s) => s.FRESH_PASSENGER_UNATTRIBUTED_MS), 95),
  runtime_sha: raw.runtime_sha,
  public_build_id: raw.public_build_id,
};

fs.writeFileSync(path.join(__dirname, "traveler-jp10c-fresh-recompute.json"), JSON.stringify(out, null, 2));
console.log(JSON.stringify(out, null, 2));
if (out.CHILD_GT_PARENT_COUNT > 0 && out.FRESH_APP_P95 < out.FRESH_PASSENGER_APP_P95) {
  process.exit(1);
}

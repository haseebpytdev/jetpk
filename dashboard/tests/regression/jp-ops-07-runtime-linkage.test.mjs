/**
 * JP-OPS-07 regression — operational-api review and core bindings.
 */

import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const root = join(dirname(fileURLToPath(import.meta.url)), "..", "..");
const operationalApi = readFileSync(join(root, "services", "operational-api.ts"), "utf8");
const portalPaths = readFileSync(join(root, "lib", "api", "portal-paths.ts"), "utf8");

let failed = 0;

function assert(condition, message) {
  if (!condition) {
    console.error(`FAIL: ${message}`);
    failed += 1;
  }
}

const reviewFns = [
  "approveCancellationReview",
  "rejectCancellationReview",
  "approveRefundReview",
  "rejectRefundReview",
];
const reviewPaths = [
  "cancellationApprovePath",
  "cancellationRejectPath",
  "refundApprovePath",
  "refundRejectPath",
];

reviewFns.forEach((fn) => assert(operationalApi.includes(fn), `${fn} exists`));
reviewPaths.forEach((fn) => assert(portalPaths.includes(fn), `${fn} exists`));

const coreFns = [
  "storeBookingNote",
  "assignBookingStaff",
  "activateUser",
  "suspendUser",
  "replySupportTicket",
  "updateSupportTicketStatus",
];
coreFns.forEach((fn) => assert(operationalApi.includes(fn), `${fn} exists`));

assert(operationalApi.includes("retryCsrfOnce: false"), "mutations disable CSRF replay");
assert(
  readFileSync(join(root, "features", "review", "operational-review-workspace.tsx"), "utf8").includes(
    "operational-review-workspace",
  ),
  "review workspace exists",
);

console.log(`jp-ops-07-runtime-linkage: ${failed === 0 ? "PASS" : `${failed} FAIL`}`);
process.exit(failed > 0 ? 1 : 0);

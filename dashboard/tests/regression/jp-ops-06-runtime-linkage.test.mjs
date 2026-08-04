/**
 * JP-OPS-06 regression — operational-api execution bindings.
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

assert(operationalApi.includes("processCancellationExecution"), "cancellation process client exists");
assert(operationalApi.includes("markRefundPaidExecution"), "refund mark-paid client exists");
assert(operationalApi.includes("issueTicketExecution"), "issue ticket client exists");
assert(operationalApi.includes("retryCsrfOnce: false"), "execution mutations disable CSRF replay");
assert(portalPaths.includes("cancellationProcessPath"), "cancellation process path builder exists");
assert(portalPaths.includes("refundMarkPaidPath"), "refund mark-paid path builder exists");
assert(portalPaths.includes("issueTicketPath"), "issue ticket path builder exists");

console.log(`jp-ops-06-runtime-linkage: ${failed === 0 ? "PASS" : `${failed} FAIL`}`);
process.exit(failed > 0 ? 1 : 0);

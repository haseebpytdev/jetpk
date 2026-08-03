/**
 * JP-OPS-05 dashboard API error normalization regression tests.
 */

import { normalizeNonJsonPayload } from "../../lib/api/response-payload-policy.ts";
import { defaultErrorMessage, mapStatusToErrorCode } from "../../lib/api/errors.ts";

let failed = 0;

function assert(condition, message) {
  if (!condition) {
    console.error(`FAIL: ${message}`);
    failed += 1;
  }
}

const statusCases = [
  [401, "unauthorized"],
  [403, "forbidden"],
  [404, "not_found"],
  [409, "conflict"],
  [419, "csrf_expired"],
  [422, "validation"],
  [429, "rate_limit"],
  [500, "server"],
  [503, "server"],
];

for (const [status, expected] of statusCases) {
  assert(mapStatusToErrorCode(status) === expected, `status ${status} should map to ${expected}`);
}

const htmlPayload = normalizeNonJsonPayload(
  "text/html",
  "<html><body>Error</body></html>",
  defaultErrorMessage,
  500,
);
assert(htmlPayload?.html_response === true, "HTML body is flagged as non-JSON");

const malformedJson = normalizeNonJsonPayload("application/json", "{not-json", defaultErrorMessage, 200);
assert(malformedJson?.malformed_json === true, "malformed JSON is flagged");

assert(defaultErrorMessage(419).includes("session"), "419 message mentions session");

console.log(`jp-ops-05-api-errors: ${failed === 0 ? "PASS" : `${failed} FAIL`}`);
process.exit(failed > 0 ? 1 : 0);

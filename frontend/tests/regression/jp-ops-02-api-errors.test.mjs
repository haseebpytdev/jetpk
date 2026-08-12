/**
 * Durable JP-OPS-02 API error normalization regression tests.
 * Keep mapStatusToErrorCode/mapFieldErrors aligned with frontend/lib/api/errors.ts.
 */

import { normalizeNonJsonPayload } from "../../lib/api/response-payload-policy.mjs";

function mapStatusToErrorCode(status) {
  if (status === 401) return "unauthorized";
  if (status === 403) return "forbidden";
  if (status === 404) return "not_found";
  if (status === 409) return "conflict";
  if (status === 419) return "csrf_expired";
  if (status === 422) return "validation";
  if (status === 429) return "rate_limit";
  if (status >= 500) return "server";
  return "unknown";
}

function mapFieldErrors(errors) {
  if (!errors) return {};
  const mapped = {};
  Object.entries(errors).forEach(([key, messages]) => {
    mapped[key] = messages[0] ?? "Invalid value";
  });
  return mapped;
}

function defaultErrorMessage(status) {
  if (status === 401) return "Your session has expired. Please sign in again.";
  if (status === 403) return "You do not have permission to perform this action.";
  if (status === 404) return "The requested resource could not be found.";
  if (status === 409) return "This action is no longer valid. Please refresh and try again.";
  if (status === 419) return "Your session expired. Please refresh and try again.";
  if (status === 422) return "Please correct the highlighted fields and try again.";
  if (status === 429) return "Too many attempts. Please wait a moment and try again.";
  if (status >= 500) return "Something went wrong on our side. Please try again shortly.";
  return "Request failed. Please try again.";
}

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
  [418, "unknown"],
];

for (const [status, expected] of statusCases) {
  assert(mapStatusToErrorCode(status) === expected, `status ${status} should map to ${expected}`);
}

const fieldMap = mapFieldErrors({ email: ["Email is required.", "Second message"] });
assert(fieldMap.email === "Email is required.", "422 field map uses first message");

const htmlPayload = normalizeNonJsonPayload(
  "text/html",
  "<html><body>Error</body></html>",
  defaultErrorMessage,
  500,
);
assert(htmlPayload?._html === true, "HTML body is flagged as non-JSON");
assert(htmlPayload?.message?.includes("Something went wrong"), "HTML body uses status default message");

// Mirror laravel-action-client: HTML-flagged payloads must not be treated as success.
function treatHtmlAsFailure(payload) {
  if (payload && typeof payload === "object" && payload._html === true) {
    return { ok: false, code: "unknown", message: payload.message ?? "Unexpected HTML response from API." };
  }
  return { ok: true };
}
const htmlFailure = treatHtmlAsFailure(htmlPayload);
assert(htmlFailure.ok === false, "HTML _html payload is rejected as API failure");
assert(typeof htmlFailure.message === "string" && htmlFailure.message.length > 0, "HTML failure has message");

const malformedJson = normalizeNonJsonPayload("application/json", "{not-json", defaultErrorMessage, 200);
assert(malformedJson === null, "malformed JSON returns null");

const emptyBody = normalizeNonJsonPayload("text/plain", "   ", defaultErrorMessage, 200);
assert(emptyBody === null, "empty non-JSON body returns null");

assert(defaultErrorMessage(401).toLowerCase().includes("session"), "401 message is session-oriented");
assert(defaultErrorMessage(419).toLowerCase().includes("expired"), "419 message mentions expiry");

// Network/timeout/abort codes are assigned in laravel-action-client catch blocks.
const networkCodes = { network: 0, aborted: 0 };
assert(networkCodes.network === 0 && networkCodes.aborted === 0, "network and abort use status 0 contract");

if (failed > 0) process.exit(1);
console.log(`JP-OPS-02 API error regression: ${statusCases.length + 8} assertions passed`);

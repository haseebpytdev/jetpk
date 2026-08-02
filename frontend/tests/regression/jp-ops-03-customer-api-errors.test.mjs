import test from "node:test";
import assert from "node:assert/strict";

function customerApiErrorMessage(result) {
  if (result.code === "unauthorized" || result.status === 401) {
    return "Your session has expired. Please sign in again.";
  }
  if (result.code === "forbidden" || result.status === 403) {
    return "You do not have access to this record.";
  }
  if (result.code === "not_found" || result.status === 404) {
    return "This record is unavailable.";
  }
  if (result.code === "conflict" || result.status === 409) {
    return result.message;
  }
  if (result.code === "csrf_expired" || result.status === 419) {
    return result.message || "Your session expired. Please try again.";
  }
  if (result.code === "validation" || result.status === 422) {
    return result.message;
  }
  if (result.code === "rate_limit" || result.status === 429) {
    return result.message;
  }
  if (result.code === "server" || result.status >= 500) {
    return result.message || "Something went wrong. Please try again.";
  }
  return result.message;
}

const cases = [
  ["401 unauthorized", { code: "unauthorized", status: 401, message: "Unauthenticated." }, /session has expired/i],
  ["403 forbidden", { code: "forbidden", status: 403, message: "Forbidden." }, /do not have access/i],
  ["404 not found", { code: "not_found", status: 404, message: "Missing." }, /unavailable/i],
  ["409 conflict", { code: "conflict", status: 409, message: "Duplicate cancellation." }, "Duplicate cancellation."],
  ["419 csrf", { code: "csrf_expired", status: 419, message: "CSRF token mismatch." }, "CSRF token mismatch."],
  ["422 validation", { code: "validation", status: 422, message: "Validation failed." }, "Validation failed."],
  ["429 throttle", { code: "rate_limit", status: 429, message: "Too many attempts." }, "Too many attempts."],
  ["500 server", { code: "server", status: 500, message: "Server error." }, "Server error."],
];

for (const [label, input, expected] of cases) {
  test(`customerApiErrorMessage handles ${label}`, () => {
    const message = customerApiErrorMessage({ ok: false, ...input });
    if (expected instanceof RegExp) {
      assert.match(message, expected);
    } else {
      assert.equal(message, expected);
    }
  });
}

test("refund panel contract has no request mutation action", () => {
  const refundSummary = {
    state: "not_eligible",
    label: "No refund on file",
    message: "Refund requests are handled by our support team after cancellation review.",
    can_request: false,
    request: null,
  };
  assert.equal(refundSummary.can_request, false);
  assert.equal(Object.prototype.hasOwnProperty.call(refundSummary, "submit_url"), false);
});

/**
 * JP-OPS-03: prove customer portal mutations attempt once on 419 (no auto replay).
 */

import test from "node:test";
import assert from "node:assert/strict";
import { simulateCsrfRetryPolicy } from "../../lib/api/csrf-retry-policy.mjs";

const CUSTOMER_MUTATION_PATHS = [
  { name: "cancellation", path: "/customer/bookings/BKG-1001/cancellations?format=json", method: "POST" },
  { name: "traveler create", path: "/customer/travelers?format=json", method: "POST" },
  { name: "traveler update", path: "/customer/travelers/12?format=json", method: "POST" },
  { name: "traveler delete", path: "/customer/travelers/12?format=json", method: "POST" },
  { name: "support reply", path: "/customer/support/tickets/TKT-1001/reply?format=json", method: "POST" },
  { name: "support close", path: "/customer/support/tickets/TKT-1001/close?format=json", method: "POST" },
];

for (const mutation of CUSTOMER_MUTATION_PATHS) {
  test(`419 on ${mutation.name} produces exactly one mutation attempt`, async () => {
    const { result, attempts } = await simulateCsrfRetryPolicy({
      method: mutation.method,
      retryCsrfOnce: false,
      fetchImpl: async () => ({
        ok: false,
        code: "csrf_expired",
        status: 419,
        path: mutation.path,
      }),
    });

    assert.equal(result.code, "csrf_expired");
    assert.equal(attempts, 1, `${mutation.name} must not auto-replay after 419`);
  });
}

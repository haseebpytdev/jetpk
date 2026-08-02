/**
 * JP-OPS-03 durable mutation wiring regression — reads production customer-dashboard-api.ts.
 */

import { readFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import test from "node:test";
import assert from "node:assert/strict";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const apiPath = path.join(__dirname, "../../features/customer-dashboard/services/customer-dashboard-api.ts");
const apiSource = readFileSync(apiPath, "utf8");
const refundPanelPath = path.join(__dirname, "../../features/customer-dashboard/bookings/BookingDocumentsPanel.tsx");
const refundPanelSource = readFileSync(refundPanelPath, "utf8");
const cancelPanelPath = path.join(__dirname, "../../features/customer-dashboard/bookings/BookingCancellationPanel.tsx");
const cancelPanelSource = readFileSync(cancelPanelPath, "utf8");

test("requestBookingCancellation posts to booking-reference endpoint", () => {
  assert.match(apiSource, /requestBookingCancellation\(\s*bookingReference: string/);
  assert.match(apiSource, /\/customer\/bookings\/\$\{encodeURIComponent\(bookingReference\)\}\/cancellations\?format=json/);
  assert.doesNotMatch(apiSource, /\/customer\/bookings\/\$\{bookingId\}/);
});

test("closeSupportTicket uses POST with _method PATCH and ticket reference", () => {
  assert.match(apiSource, /closeSupportTicket\(reference: string\)/);
  assert.match(apiSource, /formData\.set\("_method", "PATCH"\)/);
  assert.match(apiSource, /\/customer\/support\/tickets\/\$\{encodeURIComponent\(reference\)\}\/close\?format=json/);
});

test("traveler mutations use CSRF-protected customerMutation without auto replay", () => {
  assert.match(apiSource, /retryCsrfOnce: false/);
  assert.match(apiSource, /formData\.set\("_method", "PATCH"\)/);
  assert.match(apiSource, /formData\.set\("_method", "DELETE"\)/);
  assert.match(apiSource, /\/customer\/travelers\/\$\{options\.travelerId\}\?format=json/);
});

test("refund panel has no request mutation action", () => {
  assert.doesNotMatch(refundPanelSource, /requestRefund|Request refund|can_request_refund/i);
  assert.match(refundPanelSource, /BookingRefundPanel/);
  assert.match(refundPanelSource, /refund\.message/);
});

test("cancellation panel submits via requestBookingCancellation with bookingReference", () => {
  assert.match(cancelPanelSource, /requestBookingCancellation\(bookingReference/);
  assert.match(cancelPanelSource, /submitting/);
  assert.doesNotMatch(cancelPanelSource, /bookingId/);
});

test("document download URLs remain first-party laravel customer paths in presenter contract", () => {
  const presenterPath = path.join(
    __dirname,
    "../../../app/Support/CustomerPortal/CustomerPortalBookingDetailPresenter.php",
  );
  const presenterSource = readFileSync(presenterPath, "utf8");
  assert.match(presenterSource, /\/laravel\/customer\/documents\/'/);
  assert.match(presenterSource, /download_urls/);
  assert.doesNotMatch(presenterSource, /\$payload\['file_path'\]/);
});

import test from "node:test";
import assert from "node:assert/strict";

// Mirror allowlist.ts without importing TS in node:test runner.
const NEXT_BOOKING_PREFIXES = [
  "/booking/review",
  "/customer/bookings",
  "/customer/travelers",
  "/customer/invoices",
  "/customer/support",
];

function isAllowedInternalHandoffUrl(url) {
  if (!url) return false;
  const trimmed = url.trim();
  if (trimmed === "") return false;
  if (/^\s*(javascript|data|vbscript):/i.test(trimmed)) return false;
  if (trimmed.startsWith("//")) return false;
  if (trimmed.startsWith("/")) return !trimmed.includes("..");
  return false;
}

function pathMatchesAllowed(path) {
  return NEXT_BOOKING_PREFIXES.some(
    (prefix) => path === prefix || path.startsWith(`${prefix}/`) || path.startsWith(`${prefix}?`),
  );
}

function isAllowedBookingNextUrl(url) {
  if (!url) return false;
  const trimmed = url.trim();
  if (!isAllowedInternalHandoffUrl(trimmed)) return false;
  if (trimmed.startsWith("/")) return pathMatchesAllowed(trimmed);
  try {
    return pathMatchesAllowed(new URL(trimmed).pathname);
  } catch {
    return false;
  }
}

test("allowlist accepts intended customer next paths", () => {
  assert.equal(isAllowedBookingNextUrl("/customer/bookings/BKG-1001"), true);
  assert.equal(isAllowedBookingNextUrl("/customer/travelers"), true);
  assert.equal(isAllowedBookingNextUrl("/customer/invoices/BKG-1001"), true);
  assert.equal(isAllowedBookingNextUrl("/customer/support/TKT-1"), true);
});

test("allowlist rejects laravel mutation and external URLs", () => {
  assert.equal(isAllowedBookingNextUrl("/laravel/customer/bookings/1/cancellations"), false);
  assert.equal(isAllowedBookingNextUrl("https://evil.example/booking/review"), false);
  assert.equal(isAllowedBookingNextUrl("//evil.example/customer/bookings"), false);
  assert.equal(isAllowedBookingNextUrl("javascript:alert(1)"), false);
  assert.equal(isAllowedBookingNextUrl("/customer/bookings/../admin"), false);
  assert.equal(isAllowedBookingNextUrl("/laravel/customer/documents/9/download"), false);
});

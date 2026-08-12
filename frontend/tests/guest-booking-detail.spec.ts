import { expect, test } from "@playwright/test";

const guestDetailFixture = {
  ok: true,
  source: "guest_lookup",
  booking_reference: "GUEST01E01",
  presentation: { heading: "Booking pending payment", subtitle: "Awaiting payment.", tone: "warning", show_celebration: false },
  payment_status: { code: "unpaid", label: "Unpaid", terminal: false },
  booking_status: { code: "pending", label: "Pending", terminal: false },
  ticketing_status: { code: "not_started", label: "Not started", terminal: false },
  pricing: { currency: "PKR", base_fare: 40000, taxes: 5000, service_charges: 0, total: 45000, formatted_total: "Rs. 45,000" },
  itinerary: {
    trip_type: "one_way",
    origin: "LHE",
    destination: "KHI",
    depart_date: "2026-09-01",
    airline_name: "Test Air",
    cabin: "economy",
    total_formatted: "45,000",
    currency: "PKR",
    segments: [{ origin: "LHE", destination: "KHI", flight_number: "PK301" }],
    return_segments: [],
  },
  passengers: [{ passenger_type: "adult", display_name: "A*** K***", is_lead_passenger: true }],
  contact: { email_masked: "a***@example.test", phone_masked: "03***4567" },
  pnr_details: { booking_reference: null, airline_locator: null, available: false },
  tickets: [],
  capabilities: {
    can_request_cancellation: true,
    can_upload_payment_proof: true,
    mutation_urls: {
      request_cancellation: "/laravel/guest/bookings/1/access/test-token/cancellations?format=json",
      payment_proof: "/laravel/guest/bookings/1/access/test-token/payment-proof?format=json",
    },
    blade_fallback_urls: {
      guest_detail: "/laravel/guest/bookings/1/access/test-token",
      abhipay_start: "/laravel/guest/bookings/1/access/test-token/abhipay/start",
    },
  },
  cancellation: { state: "available", label: "Cancellation available", message: "You can submit a cancellation request for review.", request: null },
  blade_fallback_url: "/laravel/guest/bookings/1/access/test-token",
};

test.describe("JP-FULLSTACK-01E guest booking detail", () => {
  test("loads authoritative JSON on canonical route", async ({ page }) => {
    await page.route("**/laravel/guest/bookings/1/access/test-token?format=json", async (route) => {
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(guestDetailFixture) });
    });
    await page.goto("/guest/bookings/1/access/test-token");
    await expect(page.getByTestId("guest-booking-detail-page")).toBeVisible();
    await expect(page.getByTestId("payment-status-card")).toContainText("Unpaid");
    await expect(page.getByTestId("guest-blade-fallback-link")).toHaveCount(0);
    await expect(page.getByTestId("guest-card-payment-handoff")).toBeVisible();
    await expect(page.getByText(/Blade/i)).toHaveCount(0);
  });

  test("invalid access shows error state", async ({ page }) => {
    await page.route("**/laravel/guest/bookings/1/access/bad-token?format=json", async (route) => {
      await route.fulfill({
        status: 403,
        contentType: "application/json",
        body: JSON.stringify({ ok: false, code: "access_denied", message: "Access denied." }),
      });
    });
    await page.goto("/guest/bookings/1/access/bad-token");
    await expect(page.getByTestId("guest-booking-access-error")).toBeVisible();
  });

  test("does not persist token in localStorage", async ({ page }) => {
    await page.route("**/laravel/guest/bookings/1/access/test-token?format=json", async (route) => {
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(guestDetailFixture) });
    });
    await page.goto("/guest/bookings/1/access/test-token");
    const storage = await page.evaluate(() => ({
      local: localStorage.getItem("guest-token"),
      session: sessionStorage.getItem("guest-token"),
    }));
    expect(storage.local).toBeNull();
    expect(storage.session).toBeNull();
  });
});

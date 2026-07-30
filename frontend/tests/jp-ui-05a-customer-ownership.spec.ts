import { expect, test } from "@playwright/test";
import { sessionFixtureCookieName } from "../features/auth/server/session-fixture";

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? "http://127.0.0.1:3002";

async function setSessionFixture(page: import("@playwright/test").Page, fixture: string) {
  await page.context().addCookies([
    { name: sessionFixtureCookieName, value: fixture, url: baseURL },
  ]);
}

const ownedBookingDetail = {
  ok: true,
  booking_reference: "BKG-1001",
  booking_status: { code: "confirmed", label: "Confirmed", terminal: false },
  payment_status: { code: "paid", label: "Paid", terminal: true },
  ticketing_status: { code: "ticketed", label: "Ticketed", terminal: true },
  pricing: { currency: "PKR", total: 45000, formatted_total: "Rs. 45,000", base_fare: 40000, taxes: 5000, service_charges: 0 },
  itinerary: {
    trip_type: "one_way",
    origin: "LHE",
    destination: "KHI",
    depart_date: "2026-08-01",
    route_label: "LHE → KHI",
    segments: [{ origin: "LHE", destination: "KHI", flight_number: "PK301" }],
    return_segments: [],
    airline_name: "PIA",
    cabin: "economy",
    total_formatted: "45,000",
    currency: "PKR",
  },
  passengers: [{ title: "Ms", first_name: "Ayesha", last_name: "Khan", passenger_type: "adult" }],
  presentation: { heading: "Booking confirmed", subtitle: "Confirmed", tone: "success", show_celebration: false },
  pnr_details: { booking_reference: "ABC123", airline_locator: null, available: true },
  tickets: [{ passenger_name: "Ayesha Khan", ticket_number: "1234567890" }],
  actions: [{ code: "view_invoice", label: "View invoice", available: true, url: "/customer/invoices" }],
  contact: { name: "Ayesha Khan", email: "ayesha.khan@example.com", phone: "+923001234567", country: "PK" },
  documents_portal: [],
  support: { support_url: "/support", lookup_url: "/lookup-booking" },
  poll: { should_poll: false, interval_ms: 4000, max_attempts: 45 },
  cancellation: { eligible: false, request_pending: false, already_cancelled: false, message: "" },
  refund: { available: false, status: null, label: null },
};

test.describe("JP-UI-05A customer ownership", () => {
  test.beforeEach(async ({ page }) => {
    await setSessionFixture(page, "customer");
  });

  test("customer can access owned booking detail", async ({ page }) => {
    await page.route("**/laravel/customer/bookings/BKG-1001?format=json", async (route) => {
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(ownedBookingDetail) });
    });
    await page.goto("/customer/bookings/BKG-1001");
    await expect(page.getByTestId("customer-dashboard-shell")).toBeVisible({ timeout: 30_000 });
    await expect(page.getByTestId("customer-booking-detail")).toBeVisible({ timeout: 30_000 });
  });

  test("customer forbidden booking shows safe denial without sensitive flash", async ({ page }) => {
    await page.route("**/laravel/customer/bookings/BKG-FORBIDDEN?format=json", async (route) => {
      await route.fulfill({
        status: 403,
        contentType: "application/json",
        body: JSON.stringify({ ok: false, message: "You do not have access to this booking." }),
      });
    });
    await page.goto("/customer/bookings/BKG-FORBIDDEN");
    await expect(page.getByTestId("customer-permission-denied").or(page.getByText(/do not have access/i))).toBeVisible();
    await expect(page.getByText(/PNR/i)).toHaveCount(0);
  });

  test("customer navigation excludes agent and admin routes", async ({ page }) => {
    await page.route("**/laravel/customer?format=json", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ ok: true, metrics: {}, recent_bookings: [], quick_actions: [] }),
      });
    });
    await page.goto("/customer/dashboard");
    await expect(page.getByTestId("customer-dashboard-shell")).toBeVisible();
    await expect(page.getByRole("link", { name: /agent/i })).toHaveCount(0);
    await expect(page.getByRole("link", { name: /admin/i })).toHaveCount(0);
  });

  test("customer private route is noindex", async ({ page }) => {
    await page.route("**/laravel/customer?format=json", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ ok: true, metrics: {}, recent_bookings: [], quick_actions: [] }),
      });
    });
    await page.goto("/customer/dashboard");
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute("content", /noindex/i);
  });
});

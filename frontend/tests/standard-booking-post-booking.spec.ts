import { test, expect } from "@playwright/test";

const confirmationFixture = {
  ok: true,
  booking_session: {
    id: "abc",
    status: "confirmation",
    server_time: "2026-07-29T12:00:00Z",
    progress: [
      { key: "confirmation", label: "Confirmation", state: "current", href: null },
    ],
  },
  booking_reference: "JPTEST01",
  booking_method: "pay_later",
  payment_method_code: "manual",
  booking_status: { code: "pending", label: "Pending", terminal: false },
  payment_status: { code: "not_started", label: "Unpaid", terminal: false },
  ticketing_status: { code: "not_started", label: "Not started", terminal: false },
  pricing: {
    currency: "PKR",
    base_fare: 10000,
    taxes: 2000,
    service_charges: 500,
    total: 12500,
    formatted_total: "Rs. 12,500",
  },
  itinerary: {
    trip_type: "one_way",
    origin: "LHE",
    destination: "DXB",
    route_label: "LHE → DXB",
    segments: [{ origin: "LHE", destination: "DXB", flight_number: "PK233" }],
    return_segments: [],
    currency: "PKR",
  },
  passengers: [
    {
      title: "Mr",
      first_name: "Test",
      last_name: "Passenger",
      passenger_type: "adult",
      passport_number_masked: "••••1234",
    },
  ],
  contact: { name: "Test Passenger", email: "test@example.com", phone: "+923001234567", country: "PK" },
  documents_portal: [],
  support: { support_url: "/support", lookup_url: "/lookup-booking" },
  presentation: {
    heading: "Booking request received",
    subtitle: "Complete manual payment using the instructions provided.",
    tone: "pending",
    show_celebration: false,
  },
  pnr_details: { booking_reference: null, airline_locator: null, available: false },
  tickets: [],
  actions: [
    { code: "view_invoice", label: "View invoice", available: true, url: "/booking/invoice" },
    { code: "lookup_booking", label: "Lookup booking later", available: true, url: "/lookup-booking" },
  ],
  poll: { should_poll: false, interval_ms: 4000, max_attempts: 45 },
  cancellation: { eligible: false, request_pending: false, already_cancelled: false, message: "Use your customer account or booking lookup to request cancellation." },
  refund: { available: false, status: null, label: null },
};

test.describe("standard booking post-booking", () => {
  test("confirmation page shows missing session without checkout cookie", async ({ page }) => {
    await page.goto("/booking/confirmation");
    await expect(page.getByTestId("missing-booking-session")).toBeVisible();
  });

  test("lookup page renders form", async ({ page }) => {
    await page.goto("/lookup-booking");
    await expect(page.getByTestId("booking-lookup-page")).toBeVisible();
    await expect(page.getByTestId("lookup-submit")).toBeVisible();
  });

  test("confirmation page renders manual pending state from Laravel", async ({ page }) => {
    await page.route("**/booking/confirmation?format=json", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(confirmationFixture),
      });
    });

    await page.goto("/booking/confirmation");
    await expect(page.getByTestId("booking-confirmation-page")).toBeVisible();
    await expect(page.getByTestId("booking-reference")).toHaveText("JPTEST01");
    await expect(page.getByText("Booking request received")).toBeVisible();
    await expect(page.getByTestId("payment-status-card")).toContainText("Unpaid");
    await expect(page.getByTestId("booking-status-card")).toContainText("Pending");
    await expect(page.getByTestId("itinerary-timeline")).toBeVisible();
    await expect(page.getByTestId("passenger-summary")).toBeVisible();
    await expect(page.getByTestId("masked-document")).toContainText("••••1234");
    await expect(page.getByTestId("post-booking-actions")).toBeVisible();
    await expect(page.getByTestId("action-view_invoice")).toBeVisible();
    await expect(page.getByTestId("pnr-value")).toHaveCount(0);
    await expect(page.getByTestId("ticket-number-row")).toHaveCount(0);
  });

  test("confirmation page shows ticketed state only when authoritative", async ({ page }) => {
    await page.route("**/booking/confirmation?format=json", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ...confirmationFixture,
          booking_status: { code: "ticketed", label: "Confirmed", terminal: true },
          payment_status: { code: "succeeded", label: "Paid", terminal: true },
          ticketing_status: { code: "ticketed", label: "Ticketed", terminal: true },
          presentation: {
            heading: "Booking complete",
            subtitle: "Your tickets have been issued.",
            tone: "success",
            show_celebration: true,
          },
          pnr_details: { booking_reference: "ABC123", airline_locator: null, available: true },
          tickets: [{ ticket_number: "176-1234567890", passenger_name: "Test Passenger", status: "issued" }],
        }),
      });
    });

    await page.goto("/booking/confirmation");
    await expect(page.getByText("Booking complete")).toBeVisible();
    await expect(page.getByTestId("pnr-value")).toContainText("ABC123");
    await expect(page.getByTestId("ticket-number-row")).toContainText("176-1234567890");
  });

  test("payment status page shows separate booking and payment states", async ({ page }) => {
    await page.route("**/booking/payment/status?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          booking_reference: "JPTEST01",
          payment_status: { code: "succeeded", label: "Paid", terminal: true },
          booking_status: { code: "pending", label: "Pending", terminal: false },
          ticketing_status: { code: "pending", label: "Pending", terminal: false },
          confirmation_url: "/booking/confirmation",
          invoice_url: "/booking/invoice",
          poll: { should_poll: false, interval_ms: 3000, max_attempts: 40 },
        }),
      });
    });

    await page.goto("/booking/payment/status");
    await expect(page.getByTestId("payment-status-page")).toBeVisible();
    await expect(page.getByTestId("payment-status-label")).toContainText("Paid");
    await expect(page.getByRole("article").filter({ hasText: "Booking" })).toContainText("Pending");
    await expect(page.getByRole("article").filter({ hasText: "Ticketing" })).toContainText("Pending");
  });

  test("invoice page shows unavailable pdf honestly", async ({ page }) => {
    await page.route("**/booking/invoice?format=json", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          invoice_number: null,
          booking_reference: "JPTEST01",
          issue_date: "2026-07-29",
          customer: { email: "test@example.com", name: "Test Passenger" },
          itinerary_summary: { route: "LHE → DXB", depart_date: "2026-08-15", return_date: "" },
          passenger_count: 1,
          line_items: [{ label: "Base fare", formatted: "Rs. 10,000" }],
          pricing: confirmationFixture.pricing,
          payment_method: "pay_later",
          payment_status: { code: "not_started", label: "Unpaid" },
          booking_status: { code: "pending", label: "Pending" },
          company: { name: "JetPakistan", email: "support@jetpakistan.com", phone: "+92 300 0000000", address: null },
          pdf_available: false,
          pdf_download_path: null,
        }),
      });
    });

    await page.goto("/booking/invoice");
    await expect(page.getByTestId("invoice-page")).toBeVisible();
    await expect(page.getByTestId("invoice-pdf-unavailable")).toBeVisible();
  });
});

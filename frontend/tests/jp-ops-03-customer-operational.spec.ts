import { expect, test } from "@playwright/test";
import { sessionFixtureCookieName } from "../features/auth/server/session-fixture";

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? "http://127.0.0.1:3002";

async function setSessionFixture(
  page: import("@playwright/test").Page,
  fixture: "customer" | "anonymous" | "expired",
) {
  await page.context().addCookies([
    { name: sessionFixtureCookieName, value: fixture, url: baseURL },
    { name: "XSRF-TOKEN", value: "test-csrf-token", url: baseURL },
  ]);
}

const bookingDetailBase = {
  ok: true,
  booking_reference: "BKG-OPS03",
  booking_method: "pay_later",
  payment_method_code: "manual",
  booking_status: { code: "confirmed", label: "Confirmed", terminal: false },
  payment_status: { code: "paid", label: "Paid", terminal: true },
  ticketing_status: { code: "ticketed", label: "Ticketed", terminal: true },
  pricing: {
    currency: "PKR",
    base_fare: 40000,
    taxes: 5000,
    service_charges: 0,
    total: 45000,
    formatted_total: "Rs. 45,000",
  },
  itinerary: {
    trip_type: "one_way",
    origin: "LHE",
    destination: "KHI",
    depart_date: "2026-08-01",
    airline_name: "Pakistan International Airlines",
    cabin: "economy",
    total_formatted: "45,000",
    currency: "PKR",
    route_label: "LHE → KHI",
    segments: [{ origin: "LHE", destination: "KHI", flight_number: "PK301" }],
    return_segments: [],
  },
  passengers: [{ title: "Mr", first_name: "Audit", last_name: "Traveler", passenger_type: "adult" }],
  contact: { name: "Audit Traveler", email: "audit@example.com", phone: "+923001234567", country: "PK" },
  documents_portal: [],
  support: { support_url: "/support", lookup_url: "/lookup-booking" },
  presentation: { heading: "Booking confirmed", subtitle: "Your booking is confirmed.", tone: "success", show_celebration: false },
  pnr_details: { booking_reference: "ABC123", airline_locator: null, available: true },
  tickets: [{ passenger_name: "Audit Traveler", ticket_number: "1234567890" }],
  actions: [],
  poll: { should_poll: false, interval_ms: 4000, max_attempts: 45 },
  cancellation: { eligible: true, request_pending: false, already_cancelled: false, message: "" },
  refund: { available: false, status: null, label: null },
};

function bookingDetailWithOps03(overrides: Record<string, unknown> = {}) {
  return {
    ...bookingDetailBase,
    capabilities: {
      can_request_cancellation: true,
      can_download_invoice: false,
      can_download_ticket: false,
      can_request_refund: false,
      mutation_urls: { request_cancellation: "/laravel/customer/bookings/BKG-OPS03/cancellations" },
      download_urls: { invoice: null, ticket: null },
      reason_codes: { can_download_invoice: "document_not_ready", can_request_refund: "customer_refund_request_unavailable" },
    },
    cancellation: {
      state: "available",
      label: "Cancellation available",
      message: "You can submit a cancellation request for review.",
      request: null,
    },
    refund: {
      state: "not_eligible",
      label: "No refund on file",
      message: "Refund requests are handled by our support team after cancellation review.",
      can_request: false,
      request: null,
    },
    ...overrides,
  };
}

test.describe("JP-OPS-03 customer operational closure", () => {
  test.beforeEach(async ({ page }) => {
    await setSessionFixture(page, "customer");
    await page.route("**/laravel/api/public/content/csrf-token", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ csrf_token: "test-csrf-token" }),
      });
    });
    await page.route("**/laravel/customer?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ ok: true, metrics: { total_bookings: 1 }, notifications_available: false }),
      });
    });
  });

  test("booking detail loads and shows refund unavailable without request action", async ({ page }) => {
    await page.route("**/laravel/customer/bookings/BKG-OPS03?format=json*", async (route) => {
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(bookingDetailWithOps03()) });
    });
    await page.goto("/customer/bookings/BKG-OPS03");
    await expect(page.getByTestId("customer-booking-detail")).toBeVisible();
    await expect(page.getByTestId("booking-refund-panel")).toBeVisible();
    await expect(page.getByRole("button", { name: /request refund/i })).toHaveCount(0);
    await expect(page.getByTestId("booking-documents-panel")).toContainText(/not available yet/i);
  });

  test("cancellation submit posts to booking-reference endpoint once", async ({ page }) => {
    const posts: string[] = [];
    await page.route("**/laravel/customer/bookings/BKG-OPS03?format=json*", async (route) => {
      if (route.request().method() === "GET") {
        await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(bookingDetailWithOps03()) });
        return;
      }
      await route.continue();
    });
    await page.route("**/laravel/customer/bookings/BKG-OPS03/cancellations?format=json", async (route) => {
      posts.push(route.request().method());
      await route.fulfill({
        status: 201,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          message: "Your cancellation request has been submitted.",
          cancellation_request: { status: "requested", status_label: "Requested", message: "Under review." },
        }),
      });
    });
    await page.goto("/customer/bookings/BKG-OPS03");
    await expect(page.getByTestId("cancellation-request-panel")).toBeVisible();
    await page.getByLabel(/understand that cancellation/i).check();
    await page.getByTestId("submit-cancellation-request").click();
    await expect(page.getByText(/submitted/i)).toBeVisible();
    expect(posts).toEqual(["POST"]);
  });

  test("duplicate cancellation shows authoritative conflict state", async ({ page }) => {
    await page.route("**/laravel/customer/bookings/BKG-OPS03?format=json*", async (route) => {
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(bookingDetailWithOps03()) });
    });
    await page.route("**/laravel/customer/bookings/BKG-OPS03/cancellations?format=json", async (route) => {
      await route.fulfill({
        status: 409,
        contentType: "application/json",
        body: JSON.stringify({
          ok: false,
          code: "cancellation_already_requested",
          message: "A cancellation request is already in progress.",
        }),
      });
    });
    await page.goto("/customer/bookings/BKG-OPS03");
    await page.getByLabel(/understand that cancellation/i).check();
    await page.getByTestId("submit-cancellation-request").click();
    await expect(page.getByText(/already in progress/i)).toBeVisible();
  });

  test("invoice detail shows honest unavailable state", async ({ page }) => {
    await page.route("**/laravel/customer/invoices/BKG-NOINV?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          booking_reference: "BKG-NOINV",
          pdf_available: false,
          download_url: null,
        }),
      });
    });
    await page.goto("/customer/invoices/BKG-NOINV");
    await expect(page.getByText(/PDF not available yet/i)).toBeVisible();
    await expect(page.getByRole("link", { name: /download/i })).toHaveCount(0);
  });

  test("saved traveler validation surfaces 422 message", async ({ page }) => {
    await page.route("**/laravel/customer/travelers?format=json*", async (route) => {
      if (route.request().method() === "GET") {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            ok: true,
            travelers: [],
            default_traveler: null,
            pagination: { current_page: 1, last_page: 1, per_page: 20, total: 0, from: null, to: null },
            countries: [{ code: "PK", name: "Pakistan" }],
            create_url: "/laravel/customer/travelers",
          }),
        });
        return;
      }
      await route.fulfill({
        status: 422,
        contentType: "application/json",
        body: JSON.stringify({ ok: false, message: "Validation failed.", errors: { first_name: ["Required."] } }),
      });
    });
    await page.route("**/laravel/customer/travelers/create?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          traveler: {
            id: null,
            title: "Mr",
            first_name: "",
            last_name: "",
            gender: "male",
            nationality: "PK",
            document_type: "passport",
            document_number: null,
            is_default: false,
          },
          countries: [{ code: "PK", name: "Pakistan" }],
          submit_url: "/laravel/customer/travelers",
          method: "POST",
        }),
      });
    });
    await page.goto("/customer/travelers");
    await page.getByRole("button", { name: /add traveler/i }).click();
    await page.locator('[name="first_name"]').fill("Ali");
    await page.locator('[name="last_name"]').fill("Khan");
    await page.locator('[name="date_of_birth"]').fill("1990-01-01");
    await page.getByRole("button", { name: /save traveler/i }).click();
    await expect(page.getByText(/validation failed/i)).toBeVisible();
  });

  test("support close submits PATCH contract once", async ({ page }) => {
    let closeBody = "";
    await page.route("**/laravel/customer/support/tickets/TKT-OPS03?format=json*", async (route) => {
      if (route.request().method() === "GET") {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            ok: true,
            ticket: {
              reference: "TKT-OPS03",
              subject: "Help",
              category: "payment",
              category_label: "Payment",
              status: { code: "open", label: "Open" },
              detail_url: "/customer/support/TKT-OPS03",
              can_close: true,
              can_reply: true,
            },
            conversation: [],
            reply_url: "/laravel/customer/support/tickets/TKT-OPS03/reply",
            close_url: "/laravel/customer/support/tickets/TKT-OPS03/close",
          }),
        });
        return;
      }
      await route.continue();
    });
    await page.route("**/laravel/customer/support/tickets/TKT-OPS03/close**", async (route) => {
      closeBody = route.request().postData() ?? "";
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ ok: true, ticket: { status: { code: "closed", label: "Closed" } } }),
      });
    });
    await page.goto("/customer/support/TKT-OPS03");
    await expect(page.getByTestId("support-case-detail")).toBeVisible();
    const closeResponse = page.waitForResponse(
      (response) => response.url().includes("/close") && response.request().method() === "POST",
    );
    await page.getByTestId("close-support-ticket").click();
    await closeResponse;
    expect(closeBody).toContain('name="_method"');
    expect(closeBody).toContain("PATCH");
  });

  test("expired session recovery redirects to login", async ({ page }) => {
    await setSessionFixture(page, "expired");
    await page.goto("/customer/bookings");
    await expect(page).toHaveURL(/\/login\?reason=session-expired/);
  });

  test("anonymous user does not call private customer API before layout redirect", async ({ page }) => {
    const privateRequests: string[] = [];
    page.on("request", (request) => {
      if (/\/laravel\/customer\?format=json/.test(request.url())) {
        privateRequests.push(request.url());
      }
    });
    await setSessionFixture(page, "anonymous");
    await page.goto("/customer/dashboard");
    await expect(page).toHaveURL(/\/login/);
    expect(privateRequests.length).toBe(0);
  });

  test("customer payments list loads from Laravel JSON", async ({ page }) => {
    await page.route("**/laravel/customer/payments?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          filter: "all",
          payments: [
            {
              reference: "PAY-1001",
              booking_reference: "BKG-PAY1",
              amount: 45000,
              currency: "PKR",
              payment_method_label: "Bank transfer",
              payment_status: { code: "paid", label: "Paid" },
            },
          ],
          pagination: { current_page: 1, last_page: 1, per_page: 15, total: 1, from: 1, to: 1 },
        }),
      });
    });
    await page.goto("/customer/payments");
    await expect(page.getByTestId("customer-payments-list")).toBeVisible();
    await expect(page.getByText("PAY-1001")).toBeVisible();
    await expect(page.getByText("BKG-PAY1")).toBeVisible();
  });

  test("customer payments empty state", async ({ page }) => {
    await page.route("**/laravel/customer/payments?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          filter: "all",
          payments: [],
          pagination: { current_page: 1, last_page: 1, per_page: 15, total: 0, from: null, to: null },
        }),
      });
    });
    await page.goto("/customer/payments");
    await expect(page.getByText(/no payments yet/i)).toBeVisible();
  });

  test("customer payments server error state", async ({ page }) => {
    await page.route("**/laravel/customer/payments?format=json*", async (route) => {
      await route.fulfill({ status: 500, contentType: "application/json", body: JSON.stringify({ ok: false, message: "Server error." }) });
    });
    await page.goto("/customer/payments");
    await expect(page.getByText(/server error/i)).toBeVisible();
  });
});

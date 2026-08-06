import { expect, test, type Page } from "@playwright/test";
import { sessionFixtureCookieName } from "../features/auth/server/session-fixture";

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? "http://127.0.0.1:3002";

async function setSessionFixture(page: Page, fixture: "agent" | "agent_staff" | "expired") {
  await page.context().addCookies([
    { name: sessionFixtureCookieName, value: fixture, url: baseURL },
    { name: "XSRF-TOKEN", value: "test-csrf-token", url: baseURL },
  ]);
}

const capabilitiesWithTravelers = {
  ok: true,
  session_usable: true,
  identity: {
    display_name: "Agency Owner",
    email: "agent@example.com",
    role: "agent" as const,
    role_label: "Agency owner",
    is_owner: true,
  },
  agency: { name: "Alpha Travel", status: "active" },
  permissions: {
    bookings_view: true,
    travelers_manage: true,
    ledger_view: true,
    reports_view: true,
    wallet_view: true,
  },
  modules: {
    saved_travelers: true,
    agent_ledger: true,
    agent_reports: true,
    agent_wallet: true,
  },
  capabilities: {},
  navigation: [
    { code: "travelers", label: "Travelers", href: "/agent/travelers", available: true },
    { code: "finance_statement", label: "Statement", href: "/agent/finance/statement", available: true },
    { code: "accounting_ledger", label: "Accounting ledger", href: "/agent/accounting/ledger", available: true },
  ],
};

const capabilitiesStaffWithLedger = {
  ...capabilitiesWithTravelers,
  identity: {
    display_name: "Ledger Staff",
    email: "ledger-staff@example.com",
    role: "agent_staff" as const,
    role_label: "Agent staff",
    is_owner: false,
  },
  permissions: {
    bookings_view: true,
    travelers_manage: false,
    ledger_view: true,
    reports_view: false,
    wallet_view: false,
  },
  navigation: [
    { code: "bookings", label: "Bookings", href: "/agent/bookings", available: true },
    { code: "accounting_ledger", label: "Accounting ledger", href: "/agent/accounting/ledger", available: true },
  ],
};

const editTravelerFixture = {
  id: 42,
  title: "Mr",
  first_name: "Ali",
  last_name: "Khan",
  gender: "male",
  nationality: "PK",
  date_of_birth: "1990-01-15",
  document_type: "passport",
  document_number: "AB1234567",
  document_expiry: "2030-01-01",
  issuing_country: "PK",
  is_default: false,
};

const financeStatementPayload = {
  ok: true,
  agency: { name: "Alpha Travel" },
  period: { from: "2026-08-01", to: "2026-08-31" },
  currency: "PKR",
  opening_balance: 10000,
  closing_balance: 12000,
  total_debits: 2000,
  total_credits: 4000,
  movements: [
    {
      date: "2026-08-02",
      type: "deposit",
      description: "Deposit approved",
      reference: "DEP-1",
      debit: 0,
      credit: 4000,
      running_balance: 14000,
      status: "posted",
    },
  ],
  reconciliation: { wallet_balance: 12000, ledger_liability: 12000, difference: 0, status: "Matched", matches: true },
  export_url: "/laravel/agent/finance/statement/export",
  blade_fallback_url: "/laravel/agent/finance/statement",
};

const accountingLedgerPayload = {
  ok: true,
  summary: { wallet_balance: 12000, ledger_liability: 12000, difference: 0, reconciliation_status: "Matched", currency: "PKR" },
  filters: {},
  transactions: [
    {
      id: 1,
      transaction_ref: "LT-001",
      transaction_type: "wallet_deposit",
      status: "posted",
      currency: "PKR",
      amount_total: 4000,
      debit_total: 4000,
      credit_total: 4000,
      description: "Deposit",
      detail_url: "/laravel/agent/accounting/ledger/1",
    },
  ],
  pagination: { current_page: 1, last_page: 1, per_page: 25, total: 1, from: 1, to: 1 },
  blade_fallback_url: "/laravel/agent/accounting/ledger",
};

const capabilitiesStaffNoTravelers = {
  ...capabilitiesWithTravelers,
  identity: {
    display_name: "Staff User",
    email: "staff@example.com",
    role: "agent_staff" as const,
    role_label: "Agent staff",
    is_owner: false,
  },
  permissions: {
    bookings_view: true,
    travelers_manage: false,
    ledger_view: false,
    reports_view: false,
    wallet_view: false,
  },
  navigation: [{ code: "bookings", label: "Bookings", href: "/agent/bookings", available: true }],
};

async function mockAgentDashboard(page: Page, caps: typeof capabilitiesWithTravelers | typeof capabilitiesStaffNoTravelers = capabilitiesWithTravelers) {
  await page.route("**/laravel/agent?format=json", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        ok: true,
        capabilities: caps,
        metrics: { total_bookings: 0, pending_payment: 0, ticketing_pending: 0, confirmed_bookings: 0, upcoming_trips: 0, open_support_cases: 0, unread_notifications: 0 },
        notifications_available: false,
        recent_bookings: [],
        upcoming_booking: null,
        first_pending_payment_booking: null,
        quick_actions: [],
      }),
    });
  });
}

test.describe("JP-FULLSTACK-01F agent travelers and finance", () => {
  test.beforeEach(async ({ page }) => {
    await page.route("**/laravel/api/public/content/csrf-token", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ csrf_token: "test-csrf-token" }),
      });
    });
    await mockAgentDashboard(page);
  });

  test("travelers list populated state", async ({ page }) => {
    await page.route("**/laravel/agent/travelers?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          travelers: [
            {
              id: 1,
              title: "Mr",
              first_name: "Ali",
              last_name: "Khan",
              gender: "male",
              nationality: "PK",
              document_type: "passport",
              document_number_masked: "PK****7890",
              is_default: true,
            },
          ],
          pagination: { current_page: 1, last_page: 1, per_page: 20, total: 1, from: 1, to: 1 },
          countries: [{ code: "PK", name: "Pakistan" }],
          create_url: "/laravel/agent/travelers",
        }),
      });
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/travelers");
    await expect(page.getByTestId("agent-travelers-list")).toBeVisible();
    await expect(page.getByText("Ali Khan")).toBeVisible();
  });

  test("travelers empty state", async ({ page }) => {
    await page.route("**/laravel/agent/travelers?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          travelers: [],
          pagination: { current_page: 1, last_page: 1, per_page: 20, total: 0, from: null, to: null },
          countries: [],
          create_url: "/laravel/agent/travelers",
        }),
      });
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/travelers");
    await expect(page.getByTestId("agent-dashboard-empty")).toBeVisible();
  });

  test("traveler create form loads", async ({ page }) => {
    await page.route("**/laravel/agent/travelers/create?format=json*", async (route) => {
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
            is_default: false,
          },
          countries: [{ code: "PK", name: "Pakistan" }],
          submit_url: "/laravel/agent/travelers",
          method: "POST",
        }),
      });
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/travelers/new");
    await expect(page.getByTestId("agent-traveler-form")).toBeVisible();
  });

  test("traveler permission denied", async ({ page }) => {
    await mockAgentDashboard(page, capabilitiesStaffNoTravelers);
    await page.route("**/laravel/agent/travelers?format=json*", async (route) => {
      await route.fulfill({ status: 403, contentType: "application/json", body: JSON.stringify({ ok: false, message: "Forbidden" }) });
    });
    await setSessionFixture(page, "agent_staff");
    await page.goto("/agent/travelers");
    await expect(page.getByTestId("agent-permission-denied")).toBeVisible();
  });

  test("finance statement populated state", async ({ page }) => {
    await page.route("**/laravel/agent/finance/statement?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          agency: { name: "Alpha Travel" },
          period: { from: "2026-08-01", to: "2026-08-31" },
          currency: "PKR",
          opening_balance: 10000,
          closing_balance: 12000,
          total_debits: 2000,
          total_credits: 4000,
          movements: [
            {
              date: "2026-08-02",
              type: "deposit",
              description: "Deposit approved",
              reference: "DEP-1",
              debit: 0,
              credit: 4000,
              running_balance: 14000,
              status: "posted",
            },
          ],
          reconciliation: { wallet_balance: 12000, ledger_liability: 12000, difference: 0, status: "Matched", matches: true },
          export_url: "/laravel/agent/finance/statement/export",
          blade_fallback_url: "/laravel/agent/finance/statement",
        }),
      });
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/finance/statement");
    await expect(page.getByTestId("agent-finance-statement-summary")).toBeVisible();
    await expect(page.getByTestId("agent-finance-export")).toBeVisible();
  });

  test("finance statement empty movements", async ({ page }) => {
    await page.route("**/laravel/agent/finance/statement?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          agency: { name: "Alpha Travel" },
          period: { from: "2026-08-01", to: "2026-08-31" },
          currency: "PKR",
          opening_balance: 0,
          closing_balance: 0,
          total_debits: 0,
          total_credits: 0,
          movements: [],
          reconciliation: { wallet_balance: 0, ledger_liability: 0, difference: 0, status: "Matched", matches: true },
          export_url: null,
          blade_fallback_url: "/laravel/agent/finance/statement",
        }),
      });
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/finance/statement");
    await expect(page.getByTestId("agent-dashboard-empty")).toBeVisible();
  });

  test("accounting ledger populated state", async ({ page }) => {
    await page.route("**/laravel/agent/accounting/ledger?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          summary: { wallet_balance: 12000, ledger_liability: 12000, difference: 0, reconciliation_status: "Matched", currency: "PKR" },
          filters: {},
          transactions: [
            {
              id: 1,
              transaction_ref: "LT-001",
              transaction_type: "wallet_deposit",
              status: "posted",
              currency: "PKR",
              amount_total: 4000,
              debit_total: 4000,
              credit_total: 4000,
              description: "Deposit",
              detail_url: "/laravel/agent/accounting/ledger/1",
            },
          ],
          pagination: { current_page: 1, last_page: 1, per_page: 25, total: 1, from: 1, to: 1 },
          blade_fallback_url: "/laravel/agent/accounting/ledger",
        }),
      });
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/accounting/ledger");
    await expect(page.getByTestId("agent-accounting-ledger-list")).toBeVisible();
    await expect(page.getByText("LT-001")).toBeVisible();
  });

  test("accounting ledger staff denied", async ({ page }) => {
    await mockAgentDashboard(page, capabilitiesStaffNoTravelers);
    await page.route("**/laravel/agent/accounting/ledger?format=json*", async (route) => {
      await route.fulfill({ status: 403, contentType: "application/json", body: JSON.stringify({ ok: false, message: "Forbidden" }) });
    });
    await setSessionFixture(page, "agent_staff");
    await page.goto("/agent/accounting/ledger");
    await expect(page.getByTestId("agent-permission-denied")).toBeVisible();
  });

  test("traveler edit mutation sends PATCH without ownership fields", async ({ page }) => {
    await page.route("**/laravel/agent/travelers/42/edit?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          traveler: editTravelerFixture,
          countries: [{ code: "PK", name: "Pakistan" }],
          submit_url: "/laravel/agent/travelers/42",
          method: "PATCH",
        }),
      });
    });

    let mutationCount = 0;
    let capturedBody = "";
    await page.route("**/laravel/agent/travelers/42?format=json", async (route) => {
      if (route.request().method() !== "POST") {
        await route.continue();
        return;
      }
      mutationCount += 1;
      capturedBody = route.request().postData() ?? "";
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          redirect_url: "/agent/travelers",
          traveler: { ...editTravelerFixture, first_name: "Updated" },
        }),
      });
    });
    await page.route("**/laravel/agent/travelers?format=json*", async (route) => {
      if (route.request().method() !== "GET") {
        await route.continue();
        return;
      }
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          travelers: [{ ...editTravelerFixture, first_name: "Updated" }],
          pagination: { current_page: 1, last_page: 1, per_page: 20, total: 1, from: 1, to: 1 },
          countries: [{ code: "PK", name: "Pakistan" }],
          create_url: "/laravel/agent/travelers",
        }),
      });
    });

    await setSessionFixture(page, "agent");
    await page.goto("/agent/travelers/42/edit");
    await expect(page.getByTestId("agent-traveler-form")).toBeVisible();
    await page.locator('input[name="first_name"]').fill("Updated");
    await Promise.all([
      page.waitForURL(/\/agent\/travelers$/),
      page.getByRole("button", { name: /update traveler/i }).click(),
    ]);
    expect(mutationCount).toBe(1);
    expect(capturedBody).toMatch(/name="_method"/);
    expect(capturedBody).toContain("PATCH");
    expect(capturedBody).not.toContain("agency_id");
    expect(capturedBody).not.toContain("user_id");
    expect(capturedBody).toContain('name="first_name"');
    expect(capturedBody).toContain("Updated");
    await expect(page.getByTestId("agent-travelers-list")).toBeVisible();
    await expect(page.getByText("Updated Khan")).toBeVisible();
  });

  test("traveler create validation 422 keeps form usable", async ({ page }) => {
    await page.route("**/laravel/agent/travelers/create?format=json*", async (route) => {
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
            is_default: false,
          },
          countries: [{ code: "PK", name: "Pakistan" }],
          submit_url: "/laravel/agent/travelers",
          method: "POST",
        }),
      });
    });
    await page.route("**/laravel/agent/travelers?format=json", async (route) => {
      if (route.request().method() !== "POST") {
        await route.continue();
        return;
      }
      await route.fulfill({
        status: 422,
        contentType: "application/json",
        body: JSON.stringify({
          ok: false,
          message: "The document number format is invalid.",
          errors: { document_number: ["The document number format is invalid."] },
        }),
      });
    });

    await setSessionFixture(page, "agent");
    await page.goto("/agent/travelers/new");
    await page.locator('input[name="first_name"]').fill("Sara");
    await page.locator('input[name="last_name"]').fill("Ahmed");
    await page.locator('input[name="date_of_birth"]').fill("1992-05-10");
    await page.locator('input[name="document_number"]').fill("INVALID");
    await page.getByRole("button", { name: /save traveler/i }).click();
    await expect(page).toHaveURL(/\/agent\/travelers\/new$/);
    await expect(page.getByTestId("agent-traveler-form")).toBeVisible();
    await expect(page.getByTestId("agent-traveler-form").locator('[role="alert"]')).toBeVisible();
    await expect(page.getByText(/document number format is invalid/i)).toBeVisible();
  });

  test("traveler mutation 419 retries exactly once then succeeds", async ({ page }) => {
    await page.route("**/laravel/agent/travelers/create?format=json*", async (route) => {
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
            date_of_birth: "",
            document_type: "passport",
            is_default: false,
          },
          countries: [{ code: "PK", name: "Pakistan" }],
          submit_url: "/laravel/agent/travelers",
          method: "POST",
        }),
      });
    });

    let mutationAttempts = 0;
    await page.route("**/laravel/agent/travelers?format=json", async (route) => {
      if (route.request().method() !== "POST") {
        await route.continue();
        return;
      }
      mutationAttempts += 1;
      if (mutationAttempts === 1) {
        await route.fulfill({
          status: 419,
          contentType: "application/json",
          body: JSON.stringify({ message: "CSRF token mismatch." }),
        });
        return;
      }
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          redirect_url: "/agent/travelers",
          traveler: { ...editTravelerFixture, id: 99, first_name: "Sara", last_name: "Ahmed" },
        }),
      });
    });

    await setSessionFixture(page, "agent");
    await page.goto("/agent/travelers/new");
    await page.locator('input[name="first_name"]').fill("Sara");
    await page.locator('input[name="last_name"]').fill("Ahmed");
    await page.locator('input[name="date_of_birth"]').fill("1992-05-10");
    await page.getByRole("button", { name: /save traveler/i }).click();
    await expect(page).toHaveURL(/\/agent\/travelers$/);
    expect(mutationAttempts).toBe(2);
  });

  test("traveler mutation second 419 terminates without infinite retry", async ({ page }) => {
    await page.route("**/laravel/agent/travelers/create?format=json*", async (route) => {
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
            date_of_birth: "",
            document_type: "passport",
            is_default: false,
          },
          countries: [{ code: "PK", name: "Pakistan" }],
          submit_url: "/laravel/agent/travelers",
          method: "POST",
        }),
      });
    });

    let mutationAttempts = 0;
    await page.route("**/laravel/agent/travelers?format=json", async (route) => {
      if (route.request().method() !== "POST") {
        await route.continue();
        return;
      }
      mutationAttempts += 1;
      await route.fulfill({
        status: 419,
        contentType: "application/json",
        body: JSON.stringify({ message: "CSRF token mismatch." }),
      });
    });

    await setSessionFixture(page, "agent");
    await page.goto("/agent/travelers/new");
    await page.locator('input[name="first_name"]').fill("Sara");
    await page.locator('input[name="last_name"]').fill("Ahmed");
    await page.locator('input[name="date_of_birth"]').fill("1992-05-10");
    await page.getByRole("button", { name: /save traveler/i }).click();
    await expect(page).toHaveURL(/\/agent\/travelers\/new$/);
    await expect(page.getByTestId("agent-traveler-form").locator('[role="alert"]')).toBeVisible();
    await expect.poll(() => mutationAttempts).toBe(2);
  });

  test("unauthorized staff cannot mutate travelers on edit route", async ({ page }) => {
    await mockAgentDashboard(page, capabilitiesStaffNoTravelers);
    await page.route("**/laravel/agent/travelers/42/edit?format=json*", async (route) => {
      await route.fulfill({ status: 403, contentType: "application/json", body: JSON.stringify({ ok: false, message: "Forbidden" }) });
    });
    await setSessionFixture(page, "agent_staff");
    await page.goto("/agent/travelers/42/edit");
    await expect(page.getByTestId("agent-permission-denied")).toBeVisible();
  });

  test("forged travelers navigation cannot bypass Laravel denial", async ({ page }) => {
    const forgedCaps = {
      ...capabilitiesStaffNoTravelers,
      navigation: [
        { code: "bookings", label: "Bookings", href: "/agent/bookings", available: true },
        { code: "travelers", label: "Travelers", href: "/agent/travelers", available: true },
      ],
    };
    await mockAgentDashboard(page, forgedCaps);
    await page.route("**/laravel/agent/travelers?format=json*", async (route) => {
      await route.fulfill({ status: 403, contentType: "application/json", body: JSON.stringify({ ok: false, message: "Forbidden" }) });
    });
    await setSessionFixture(page, "agent_staff");
    await page.goto("/agent/travelers");
    await expect(page.getByTestId("agent-permission-denied")).toBeVisible();
    await expect(page.getByTestId("agent-travelers-list")).toHaveCount(0);
  });

  test("forged finance statement navigation cannot bypass Laravel denial", async ({ page }) => {
    const forgedCaps = {
      ...capabilitiesStaffNoTravelers,
      navigation: [
        { code: "bookings", label: "Bookings", href: "/agent/bookings", available: true },
        { code: "finance_statement", label: "Statement", href: "/agent/finance/statement", available: true },
      ],
    };
    await mockAgentDashboard(page, forgedCaps);
    await page.route("**/laravel/agent/finance/statement?format=json*", async (route) => {
      await route.fulfill({ status: 403, contentType: "application/json", body: JSON.stringify({ ok: false, message: "Forbidden" }) });
    });
    await setSessionFixture(page, "agent_staff");
    await page.goto("/agent/finance/statement");
    await expect(page.getByTestId("agent-permission-denied")).toBeVisible();
    await expect(page.getByTestId("agent-finance-statement-summary")).toHaveCount(0);
  });

  test("finance statement server error surfaces retry state", async ({ page }) => {
    await page.route("**/laravel/agent/finance/statement?format=json*", async (route) => {
      await route.fulfill({
        status: 500,
        contentType: "application/json",
        body: JSON.stringify({ ok: false, message: "Statement unavailable." }),
      });
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/finance/statement");
    await expect(page.getByTestId("agent-dashboard-error")).toBeVisible();
    await expect(page.getByText(/statement unavailable/i)).toBeVisible();
  });

  test("finance statement policy denied for restricted staff", async ({ page }) => {
    await mockAgentDashboard(page, capabilitiesStaffNoTravelers);
    await page.route("**/laravel/agent/finance/statement?format=json*", async (route) => {
      await route.fulfill({ status: 403, contentType: "application/json", body: JSON.stringify({ ok: false, message: "Forbidden" }) });
    });
    await setSessionFixture(page, "agent_staff");
    await page.goto("/agent/finance/statement");
    await expect(page.getByTestId("agent-permission-denied")).toBeVisible();
  });

  test("finance statement expired session redirects to login", async ({ page }) => {
    await setSessionFixture(page, "expired");
    await page.goto("/agent/finance/statement");
    await expect(page).toHaveURL(/\/login/);
  });

  test("finance statement allowed export handoff uses internal Laravel URL", async ({ page }) => {
    await page.route("**/laravel/agent/finance/statement?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(financeStatementPayload),
      });
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/finance/statement");
    const exportLink = page.getByTestId("agent-finance-export");
    await expect(exportLink).toBeVisible();
    await expect(exportLink).toHaveAttribute("href", "/laravel/agent/finance/statement/export");
  });

  test("finance statement rejects external export URL", async ({ page }) => {
    await page.route("**/laravel/agent/finance/statement?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ...financeStatementPayload,
          export_url: "https://example.invalid/export.csv",
        }),
      });
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/finance/statement");
    await expect(page.getByTestId("agent-finance-export")).toHaveCount(0);
    await expect(page.getByRole("link", { name: /export csv/i })).toHaveCount(0);
  });

  test("accounting ledger empty state", async ({ page }) => {
    await page.route("**/laravel/agent/accounting/ledger?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ...accountingLedgerPayload,
          transactions: [],
          pagination: { current_page: 1, last_page: 1, per_page: 25, total: 0, from: null, to: null },
        }),
      });
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/accounting/ledger");
    await expect(page.getByTestId("agent-dashboard-empty")).toBeVisible();
  });

  test("accounting ledger server error surfaces retry state", async ({ page }) => {
    await page.route("**/laravel/agent/accounting/ledger?format=json*", async (route) => {
      await route.fulfill({
        status: 500,
        contentType: "application/json",
        body: JSON.stringify({ ok: false, message: "Ledger unavailable." }),
      });
    });
    await setSessionFixture(page, "agent");
    await page.goto("/agent/accounting/ledger");
    await expect(page.getByTestId("agent-dashboard-error")).toBeVisible();
  });

  test("accounting ledger staff allowed when Laravel permits", async ({ page }) => {
    await mockAgentDashboard(page, capabilitiesStaffWithLedger);
    await page.route("**/laravel/agent/accounting/ledger?format=json*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(accountingLedgerPayload),
      });
    });
    await setSessionFixture(page, "agent_staff");
    await page.goto("/agent/accounting/ledger");
    await expect(page.getByTestId("agent-accounting-ledger-list")).toBeVisible();
    await expect(page.getByText("LT-001")).toBeVisible();
  });

  test("accounting ledger expired session redirects to login", async ({ page }) => {
    await setSessionFixture(page, "expired");
    await page.goto("/agent/accounting/ledger");
    await expect(page).toHaveURL(/\/login/);
  });

  test("expired session redirects to login from travelers", async ({ page }) => {
    await setSessionFixture(page, "expired");
    await page.goto("/agent/travelers");
    await expect(page).toHaveURL(/\/login/);
  });
});

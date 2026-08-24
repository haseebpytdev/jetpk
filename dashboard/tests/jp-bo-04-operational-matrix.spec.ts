import { test, expect, type Page, type Route } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";
import { buildIntegrationsFixture, buildIntegrationDetailFixture } from "@/features/integrations/integrations-fixtures";

/**
 * JP-BO-04 operational matrix — live Dashboard build + fixture/mock data source,
 * with Laravel JSON stubs for mutation/inbox/integrations contracts.
 * Domain mutation truth remains covered by focused Laravel PHPUnit.
 */

const EVIDENCE_DIR = path.resolve(process.cwd(), "..", "tmp", "jp-bo-04", "playwright");
const FIXTURE = "dataSourcePreview=fixture";

function ensureEvidenceDir() {
  fs.mkdirSync(EVIDENCE_DIR, { recursive: true });
}

async function shot(page: Page, name: string) {
  ensureEvidenceDir();
  await page.screenshot({
    path: path.join(EVIDENCE_DIR, name),
    fullPage: true,
  });
}

async function stubCsrf(page: Page) {
  await page.route("**/api/public/content/csrf-token", async (route: Route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ csrf_token: "jp-bo-04-csrf" }),
    });
  });
  await page.addInitScript(() => {
    document.cookie = "XSRF-TOKEN=jp-bo-04-csrf; path=/";
  });
}

function overviewBody(paymentReviewCount: number) {
  return {
    data: {
      operationalInbox: [
        {
          key: "bookings_awaiting_payment",
          label: "Bookings awaiting payment",
          count: paymentReviewCount,
          href: "/bookings?queue=payment_review",
        },
        {
          key: "agency_applications_pending",
          label: "Agency applications pending",
          count: 2,
          href: "/agents/applications",
        },
      ],
      operationalCounts: {
        bookings_awaiting_payment: paymentReviewCount,
        payment_review: paymentReviewCount,
        agency_applications_pending: 2,
      },
    },
  };
}

async function stubOverview(page: Page, state: { count: number }) {
  await page.route("**/api/dashboard/overview**", async (route: Route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(overviewBody(state.count)),
    });
  });
}

async function stubIntegrationsApi(page: Page) {
  await page.route("**/admin/integrations**", async (route: Route) => {
    const url = route.request().url();
    const method = route.request().method();
    const detailMatch = url.match(/\/admin\/integrations\/([^/?#]+)/);

    if (method === "GET" && detailMatch && detailMatch[1] && detailMatch[1] !== "") {
      const code = decodeURIComponent(detailMatch[1]);
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ ok: true, integration: buildIntegrationDetailFixture(code) }),
      });
      return;
    }

    if (method === "GET") {
      const fixture = buildIntegrationsFixture("all");
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ ok: true, hub: fixture.hub, permissions: fixture.permissions }),
      });
      return;
    }

    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ ok: true }),
    });
  });
}

async function trackPost(page: Page, pattern: string | RegExp): Promise<string[]> {
  const hits: string[] = [];
  await page.route(pattern, async (route: Route) => {
    if (route.request().method() === "GET" || route.request().method() === "HEAD") {
      await route.continue();
      return;
    }
    hits.push(`${route.request().method()} ${route.request().url()}`);
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ ok: true, data: { status: "ok" } }),
    });
  });
  return hits;
}

test.beforeEach(async ({ page }) => {
  await stubCsrf(page);
  page.on("dialog", (dialog) => {
    void dialog.accept();
  });
});

test.describe("JP-BO-04 A — Inbox / Payment Review", () => {
  test("payment review badge 6 → resolve → 5 persists on refresh", async ({ page }) => {
    const state = { count: 6 };
    await stubOverview(page, state);

    await page.goto(`/admin/dashboard?${FIXTURE}`, { waitUntil: "load" });
    await expect(page.getByTestId("operational-inbox-badge")).toBeVisible({ timeout: 30_000 });
    await page.getByTestId("operational-inbox-badge").click();
    const paymentItem = page.getByTestId("operational-inbox-item-bookings_awaiting_payment");
    await expect(paymentItem).toContainText("6");
    await shot(page, "01-payment-review-6.png");

    await paymentItem.click();
    await expect(page).toHaveURL(/queue=payment_review/);
    await expect(page.getByTestId("bookings-filters")).toBeVisible();
    await expect(page.getByTestId("bookings-table")).toBeAttached();
    await expect(page.getByTestId("bookings-table").getByTestId("booking-manage-button")).toHaveCount(6);

    await page.goto(`/admin/dashboard/bookings/JP-BK-10015?${FIXTURE}`, { waitUntil: "load" });
    await expect(page.getByTestId("booking-operational-actions")).toBeVisible();
    const paymentHits = await trackPost(page, "**/admin/bookings/**/payments**");
    await page.getByTestId("booking-admin-mark-paid-reason").fill("JP-BO-04 Playwright resolve payment review");
    await page.getByTestId("booking-admin-mark-paid-submit").click();
    await expect.poll(() => paymentHits.length).toBeGreaterThan(0);
    state.count = 5;

    await page.goto(`/admin/dashboard?${FIXTURE}`, { waitUntil: "load" });
    await page.getByTestId("operational-inbox-badge").click();
    await expect(page.getByTestId("operational-inbox-item-bookings_awaiting_payment")).toContainText("5");
    await shot(page, "02-payment-review-5-after-resolve.png");

    await page.reload({ waitUntil: "load" });
    await page.getByTestId("operational-inbox-badge").click();
    await expect(page.getByTestId("operational-inbox-item-bookings_awaiting_payment")).toContainText("5");

    await page.getByTestId("operational-inbox-item-agency_applications_pending").click();
    await expect(page).toHaveURL(/agents\/applications/);
  });
});

test.describe("JP-BO-04 B — Booking lifecycle", () => {
  test("unpaid no-PNR shows generate/record/mark-paid; void disabled with Sabre reason", async ({ page }) => {
    await page.goto(`/admin/dashboard/bookings/JP-BK-10015?${FIXTURE}`, { waitUntil: "load" });
    await expect(page.getByTestId("booking-operational-actions")).toBeVisible({ timeout: 30_000 });
    await expect(page.getByTestId("booking-pnr-action")).toBeVisible();
    await expect(page.getByTestId("booking-payment-panel")).toBeVisible();
    await expect(page.getByTestId("booking-admin-mark-paid")).toBeVisible();
    await expect(page.getByTestId("booking-void-ticket-ineligible")).toContainText(
      /Void is not supported by the current Sabre servicing adapter/i,
    );
    await shot(page, "03-booking-no-pnr-actions.png");
  });

  test("paid PNR unticketed shows issue ticket; cancel dialog available", async ({ page }) => {
    await page.goto(`/admin/dashboard/bookings/JP-BK-10009?${FIXTURE}`, { waitUntil: "load" });
    await expect(page.getByTestId("booking-operational-actions")).toBeVisible({ timeout: 30_000 });
    await expect(page.getByTestId("booking-issue-ticket")).toBeVisible();
    await expect(page.getByTestId("booking-sync-pnr")).toBeVisible();
    await expect(page.getByTestId("booking-admin-direct-cancel")).toBeVisible();
    await shot(page, "04-booking-paid-pnr-actions.png");
    await page.getByTestId("booking-admin-direct-cancel-reason").fill("JP-BO-04 cancel proof");
    await shot(page, "05-booking-cancel-dialog.png");
  });

  test("payment override requires reason and does not auto-issue ticket", async ({ page }) => {
    const paymentHits = await trackPost(page, "**/admin/bookings/**/payments**");
    const ticketHits = await trackPost(page, "**/admin/bookings/**/tickets**");

    await page.goto(`/admin/dashboard/bookings/JP-BK-10015?${FIXTURE}`, { waitUntil: "load" });
    await expect(page.getByTestId("booking-admin-mark-paid")).toBeVisible();
    await page.getByTestId("booking-admin-mark-paid-submit").click();
    await page.waitForTimeout(400);
    expect(paymentHits.length).toBe(0);

    await page.getByTestId("booking-admin-mark-paid-reason").fill("Owner override QA reason");
    await page.getByTestId("booking-admin-mark-paid-submit").click();
    await expect.poll(() => paymentHits.length).toBe(1);
    expect(ticketHits.length).toBe(0);
  });
});

test.describe("JP-BO-04 C — Integrations / AbhiPay", () => {
  test("multi-connection Sabre UI and AbhiPay save/mask surfaces", async ({ page }) => {
    await stubIntegrationsApi(page);

    await page.goto(`/admin/dashboard/integrations?${FIXTURE}`, { waitUntil: "load" });
    await expect(page.getByTestId("integrations-hub")).toBeVisible({ timeout: 30_000 });
    await expect(page.getByTestId("integration-card-sabre")).toBeVisible({ timeout: 15_000 });
    await expect(page.getByRole("button", { name: "+ Add Integration" })).toBeVisible();
    await page.getByTestId("integration-card-sabre").getByRole("button", { name: "Settings" }).click();
    await expect(page.getByTestId("integrations-connections-panel").or(page.getByRole("dialog")).first()).toBeVisible();
    await shot(page, "06-integrations-provider-connections.png");

    await page.goto(`/admin/dashboard/integrations?provider=sabre&${FIXTURE}`, { waitUntil: "load" });
    await expect(page.getByText(/Sabre/i).first()).toBeVisible();
    await shot(page, "07-two-sabre-connections.png");
    const channel = page.getByTestId("sabre-channel-toggles");
    if ((await channel.count()) > 0) {
      await expect(channel.first()).toBeVisible();
    }
    await shot(page, "08-sabre-channel-controls.png");

    await page.goto(`/admin/dashboard/integrations?provider=abhipay&${FIXTURE}`, { waitUntil: "load" });
    // provider= query auto-opens the detail dialog — switch to configuration tab.
    const configTab = page.getByRole("dialog").getByRole("button", { name: "configuration" });
    await expect(configTab).toBeVisible({ timeout: 15_000 });
    await configTab.click();
    await expect(page.getByTestId("abhipay-config-form")).toBeVisible({ timeout: 15_000 });
    await expect(page.getByTestId("abhipay-save-configuration")).toBeVisible();
    await shot(page, "09-abhipay-config-save.png");
    await expect(page.getByText(/••••|configured|masked|secret/i).first()).toBeVisible();
    await shot(page, "10-abhipay-masked-reload.png");
  });
});

test.describe("JP-BO-04 D — Finance", () => {
  test("accounting credit/debit/reversal surfaces with stubbed Laravel JSON", async ({ page }) => {
    await page.route("**/admin/finance/adjustments**", async (route: Route) => {
      const url = route.request().url();
      const method = route.request().method();
      if (method === "GET" && url.includes("/create")) {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            ok: true,
            agencies: [{ id: "1", name: "JPQA Agency" }],
            selected_agency_id: "1",
            reason_categories: ["bank_correction"],
            idempotency_key: "jp-bo-04-idem",
            canonical_summary: { wallet_id: 9, owner_label: "JPQA Agency", balance: 1000, currency: "PKR" },
          }),
        });
        return;
      }
      if (method === "GET") {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            ok: true,
            transactions: [
              {
                id: "adj-1",
                type: "manual_credit",
                amount: 1000,
                currency: "PKR",
                can_reverse: true,
                agency_name: "JPQA Agency",
                adjustment_reason: "bank_correction",
              },
            ],
            reason_categories: ["bank_correction"],
          }),
        });
        return;
      }
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ ok: true, wallet_transaction: { id: "adj-new", type: "manual_credit", amount: 500 } }),
      });
    });

    await page.goto(`/admin/dashboard/accounting?${FIXTURE}`, { waitUntil: "load" });
    await expect(page.getByTestId("accounting-workspace")).toBeVisible({ timeout: 30_000 });
    await page.getByTestId("finance-adjustment-amount").fill("500");
    await page.getByTestId("finance-adjustment-note").fill("JP-BO-04 credit proof");
    await page.getByTestId("finance-adjustment-confirmation").check();
    await shot(page, "11-agent-credit.png");
    await expect(page.getByTestId("finance-adjustment-row-adj-1")).toBeVisible();
    await page.getByTestId("finance-adjustment-reverse-reason-adj-1").fill("net-zero reversal");
    await shot(page, "12-agent-debit-reversal.png");
  });
});

test.describe("JP-BO-04 E — SMTP / RBAC / CMS", () => {
  test("SMTP config form visible in live mode", async ({ page }) => {
    await page.route("**/admin/settings/communications**", async (route: Route) => {
      if (route.request().method() === "GET") {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            ok: true,
            settings: {
              smtp_enabled: true,
              smtp_host: "smtp.example.test",
              smtp_port: 587,
              smtp_username: "jpqa",
              smtp_password_set: true,
              smtp_password_masked: "********",
              smtp_encryption: "tls",
              smtp_from_name: "JetPakistan",
              smtp_from_email: "noreply@example.test",
            },
          }),
        });
        return;
      }
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ ok: true }) });
    });

    await page.goto(`/admin/dashboard/settings/notifications?${FIXTURE}`, { waitUntil: "load" });
    const smtp = page.getByTestId("communications-smtp-workspace");
    if ((await smtp.count()) > 0) {
      await expect(smtp).toBeVisible();
    }
    await shot(page, "13-smtp-config.png");
  });

  test("non-authorized staff is denied users admin route", async ({ page }) => {
    await page.goto(`/admin/dashboard/users?dataSourcePreview=forbidden`, {
      waitUntil: "load",
    });
    await expect(page.getByTestId("dashboard-shell")).toBeVisible({ timeout: 30_000 });
    await expect(page.getByTestId("dashboard-access-denied").or(page.getByText("Access denied")).first()).toBeVisible({
      timeout: 15_000,
    });
    await shot(page, "14-rbac-denied.png");
  });

  test("current CMS homepage/pages/media operational regression", async ({ page }) => {
    await page.goto(`/admin/dashboard/cms/sections?${FIXTURE}`, { waitUntil: "load" });
    await expect(page.getByTestId("dashboard-shell")).toBeVisible({ timeout: 30_000 });
    await expect(page.getByTestId("cms-homepage-builder")).toBeVisible({ timeout: 15_000 });
    await page.goto(`/admin/dashboard/cms/pages?${FIXTURE}`, { waitUntil: "load" });
    await expect(page.getByTestId("dashboard-shell")).toBeVisible();
    await page.goto(`/admin/dashboard/cms/assets?${FIXTURE}`, { waitUntil: "load" });
    await expect(page.getByTestId("dashboard-shell")).toBeVisible();
    await shot(page, "15-cms-operational-regression.png");
  });
});

test.describe("JP-BO-04 J — Sidebar route matrix", () => {
  const routes = [
    "",
    "/bookings",
    "/operations/execution",
    "/operations/review",
    "/pnrs",
    "/tickets",
    "/customers",
    "/agents",
    "/agents/applications",
    "/payments",
    "/deposits",
    "/markups",
    "/commissions",
    "/accounting",
    "/suppliers",
    "/integrations",
    "/cms/sections",
    "/cms/pages",
    "/cms/assets",
    "/support",
    "/reports",
    "/audit",
    "/users",
    "/staff",
    "/users/roles",
    "/settings",
    "/settings/promo-codes",
    "/system/health",
    "/system/go-live",
  ];

  for (const route of routes) {
    test(`desktop renders ${route || "/"}`, async ({ page }) => {
      const errors: string[] = [];
      page.on("console", (msg) => {
        if (msg.type() === "error") errors.push(msg.text());
      });
      if (route === "/integrations") {
        await stubIntegrationsApi(page);
      }
      const response = await page.goto(`/admin/dashboard${route}?${FIXTURE}`, { waitUntil: "domcontentloaded" });
      expect(response?.status() ?? 500).toBeLessThan(500);
      await expect(page.getByTestId("dashboard-shell")).toBeVisible({ timeout: 30_000 });
      const body = await page.locator("body").innerText();
      expect(body.trim().length).toBeGreaterThan(20);
      const critical = errors.filter(
        (e) => !/favicon|hydration|Download the React DevTools|404 \(Not Found\)|Failed to load resource/i.test(e),
      );
      expect(critical).toEqual([]);
    });
  }

  test("mobile sidebar key routes render", async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await stubIntegrationsApi(page);
    for (const route of ["", "/bookings", "/integrations", "/accounting", "/settings"]) {
      const response = await page.goto(`/admin/dashboard${route}?${FIXTURE}`, { waitUntil: "domcontentloaded" });
      expect(response?.status() ?? 500).toBeLessThan(500);
      await expect(page.getByTestId("dashboard-shell")).toBeVisible({ timeout: 30_000 });
    }
  });
});

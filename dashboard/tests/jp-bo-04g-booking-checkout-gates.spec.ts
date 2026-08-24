import { test, expect, type Page, type Route } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";

/**
 * JP-BO-04G Dashboard Booking & Checkout gate UI proof.
 * Live Dashboard build + stateful Laravel-contract stub for UI save/reload.
 * Authoritative persistence/audit/RBAC remain in Laravel PHPUnit.
 */

const EVIDENCE_DIR = path.resolve(process.cwd(), "..", "tmp", "jp-bo-04g", "dashboard-gate-ui");

type GateState = {
  guest_booking_enabled: boolean;
  card_payment_enabled: boolean;
};

function ensureEvidenceDir() {
  fs.mkdirSync(EVIDENCE_DIR, { recursive: true });
}

async function shot(page: Page, name: string) {
  ensureEvidenceDir();
  await page.screenshot({ path: path.join(EVIDENCE_DIR, name), fullPage: true });
}

async function stubCsrf(page: Page) {
  await page.route("**/api/public/content/csrf-token", async (route: Route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ csrf_token: "jp-bo-04g-csrf" }),
    });
  });
  await page.addInitScript(() => {
    document.cookie = "XSRF-TOKEN=jp-bo-04g-csrf; path=/";
  });
}

async function installStatefulCheckoutApi(page: Page, state: GateState) {
  const mutations: Array<{ method: string; body: GateState }> = [];

  await page.route("**/admin/settings/booking-checkout**", async (route: Route) => {
    const method = route.request().method().toUpperCase();
    if (method === "GET" || method === "HEAD") {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          settings: {
            guest_booking_enabled: state.guest_booking_enabled,
            card_payment_enabled: state.card_payment_enabled,
            updated_at: new Date().toISOString(),
          },
        }),
      });
      return;
    }

    if (method === "PATCH" || method === "POST" || method === "PUT") {
      let body: Record<string, unknown> = {};
      const raw = route.request().postData();
      if (raw) {
        try {
          body = JSON.parse(raw) as Record<string, unknown>;
        } catch {
          body = {};
        }
      }
      if (Object.prototype.hasOwnProperty.call(body, "guest_booking_enabled")) {
        state.guest_booking_enabled = body.guest_booking_enabled === true || body.guest_booking_enabled === 1 || body.guest_booking_enabled === "1";
      }
      if (Object.prototype.hasOwnProperty.call(body, "card_payment_enabled")) {
        state.card_payment_enabled = body.card_payment_enabled === true || body.card_payment_enabled === 1 || body.card_payment_enabled === "1";
      }
      mutations.push({
        method,
        body: {
          guest_booking_enabled: state.guest_booking_enabled,
          card_payment_enabled: state.card_payment_enabled,
        },
      });
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          message: "Booking & checkout settings updated.",
          settings: {
            guest_booking_enabled: state.guest_booking_enabled,
            card_payment_enabled: state.card_payment_enabled,
            updated_at: new Date().toISOString(),
          },
        }),
      });
      return;
    }

    await route.continue();
  });

  return mutations;
}

async function openCheckoutSettings(page: Page) {
  await page.goto("/admin/dashboard/settings/booking-checkout", { waitUntil: "domcontentloaded" });
  await expect(page.getByTestId("booking-checkout-workspace")).toBeVisible({ timeout: 30_000 });
  await expect(page.getByTestId("guest-booking-enabled-toggle")).toBeVisible();
  await expect(page.getByTestId("card-payment-enabled-toggle")).toBeVisible();
  await expect(page.getByTestId("booking-checkout-save")).toBeEnabled();
}

async function saveAndAssert(
  page: Page,
  expectGuest: boolean,
  expectCard: boolean,
) {
  const save = page.getByTestId("booking-checkout-save");
  await expect(save).toBeEnabled();
  const responsePromise = page.waitForResponse(
    (response) =>
      response.url().includes("/admin/settings/booking-checkout") &&
      ["PATCH", "POST", "PUT"].includes(response.request().method().toUpperCase()),
    { timeout: 15_000 },
  );
  await save.click();
  const response = await responsePromise;
  expect(response.ok()).toBeTruthy();
  const payload = (await response.json()) as {
    settings?: { guest_booking_enabled?: boolean; card_payment_enabled?: boolean };
  };
  expect(payload.settings?.guest_booking_enabled).toBe(expectGuest);
  expect(payload.settings?.card_payment_enabled).toBe(expectCard);
  await expect(page.getByText(/Booking & checkout settings saved|updated/i)).toBeVisible({
    timeout: 10_000,
  });
  if (expectGuest) {
    await expect(page.getByTestId("guest-booking-enabled-toggle")).toBeChecked();
  } else {
    await expect(page.getByTestId("guest-booking-enabled-toggle")).not.toBeChecked();
  }
  if (expectCard) {
    await expect(page.getByTestId("card-payment-enabled-toggle")).toBeChecked();
  } else {
    await expect(page.getByTestId("card-payment-enabled-toggle")).not.toBeChecked();
  }
}

async function reloadAndAssert(page: Page, expectGuest: boolean, expectCard: boolean) {
  await page.reload({ waitUntil: "domcontentloaded" });
  await expect(page.getByTestId("booking-checkout-workspace")).toBeVisible({ timeout: 30_000 });
  await expect(page.getByTestId("booking-checkout-save")).toBeEnabled({ timeout: 15_000 });
  if (expectGuest) {
    await expect(page.getByTestId("guest-booking-enabled-toggle")).toBeChecked();
  } else {
    await expect(page.getByTestId("guest-booking-enabled-toggle")).not.toBeChecked();
  }
  if (expectCard) {
    await expect(page.getByTestId("card-payment-enabled-toggle")).toBeChecked();
  } else {
    await expect(page.getByTestId("card-payment-enabled-toggle")).not.toBeChecked();
  }
}

test.describe("JP-BO-04G booking checkout gate UI", () => {
  test("guest and card toggles save, reload, and restore", async ({ page }) => {
    const state: GateState = {
      guest_booking_enabled: true,
      card_payment_enabled: true,
    };
    await stubCsrf(page);
    const mutations = await installStatefulCheckoutApi(page, state);

    await openCheckoutSettings(page);
    await expect(page.getByTestId("guest-booking-enabled-toggle")).toBeChecked();
    await expect(page.getByTestId("card-payment-enabled-toggle")).toBeChecked();
    await shot(page, "01-booking-checkout-baseline.png");

    await page.getByTestId("guest-booking-enabled-toggle").uncheck();
    await expect(page.getByTestId("guest-booking-enabled-toggle")).not.toBeChecked();
    await saveAndAssert(page, false, true);
    expect(state.guest_booking_enabled).toBe(false);
    expect(state.card_payment_enabled).toBe(true);
    await reloadAndAssert(page, false, true);
    await shot(page, "02-guest-gate-off-after-reload.png");

    await page.getByTestId("guest-booking-enabled-toggle").check();
    await expect(page.getByTestId("guest-booking-enabled-toggle")).toBeChecked();
    await saveAndAssert(page, true, true);
    await reloadAndAssert(page, true, true);

    await page.getByTestId("card-payment-enabled-toggle").uncheck();
    await expect(page.getByTestId("card-payment-enabled-toggle")).not.toBeChecked();
    await saveAndAssert(page, true, false);
    expect(state.card_payment_enabled).toBe(false);
    expect(state.guest_booking_enabled).toBe(true);
    await reloadAndAssert(page, true, false);
    await shot(page, "03-card-gate-off-after-reload.png");

    await page.getByTestId("card-payment-enabled-toggle").check();
    await expect(page.getByTestId("card-payment-enabled-toggle")).toBeChecked();
    await saveAndAssert(page, true, true);
    await reloadAndAssert(page, true, true);
    await shot(page, "04-gates-restored-baseline.png");

    expect(state.guest_booking_enabled).toBe(true);
    expect(state.card_payment_enabled).toBe(true);
    expect(mutations.length).toBeGreaterThanOrEqual(4);
  });
});

import { test, expect } from "@playwright/test";

const playwrightBaseUrl = process.env.PLAYWRIGHT_BASE_URL ?? "http://127.0.0.1:3002";

const turnstileEnabledConfig = {
  enabled: true,
  site_key: "test-site-key",
  response_field: "cf-turnstile-response",
};

const turnstileDisabledConfig = {
  enabled: false,
  site_key: null,
  response_field: "cf-turnstile-response",
};

async function mockTurnstileConfig(
  page: import("@playwright/test").Page,
  config: { enabled: boolean; site_key: string | null; response_field: string },
) {
  await page.route("**/laravel/api/public/content/turnstile-config", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(config),
    });
  });
}

async function mockTurnstileScript(page: import("@playwright/test").Page) {
  await page.route("**/challenges.cloudflare.com/turnstile/v0/api.js**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/javascript",
      body: `
        window.turnstile = {
          render: function(container, options) {
            window.__turnstileOptions = options;
            return 'widget-1';
          },
          reset: function() {},
          remove: function() {},
        };
      `,
    });
  });
}

function lookupForm(page: import("@playwright/test").Page) {
  return page.getByTestId("booking-lookup-page");
}

test.describe("booking lookup turnstile", () => {
  test("turnstile-enabled lookup renders widget and disables submit before token", async ({ page }) => {
    await mockTurnstileConfig(page, turnstileEnabledConfig);
    await mockTurnstileScript(page);

    await page.goto("/lookup-booking");
    await expect(lookupForm(page)).toBeVisible();
    await expect(page.getByTestId("turnstile-widget")).toBeVisible();
    await expect(page.getByTestId("lookup-submit")).toBeDisabled();
  });

  test("successful token enables submission and submits exact field", async ({ page }) => {
    await mockTurnstileConfig(page, turnstileEnabledConfig);
    await mockTurnstileScript(page);

    let submittedToken: string | null = null;
    await page.route("**/laravel/lookup-booking", async (route) => {
      const body = route.request().postData() ?? "";
      const match = body.match(/name="cf-turnstile-response"\r?\n\r?\n([^\r\n]+)/);
      submittedToken = match?.[1] ?? null;
      await route.fulfill({
        status: 302,
        headers: { Location: "/guest/bookings/1/access/test-token-123" },
      });
    });

    await page.goto("/lookup-booking");
    await lookupForm(page).getByRole("textbox", { name: /Booking reference/ }).fill("JPTEST01");
    await lookupForm(page).getByRole("textbox", { name: /Email address/ }).fill("test@example.com");

    await page.waitForFunction(() => Boolean((window as Window & { __turnstileOptions?: { callback?: (t: string) => void } }).__turnstileOptions));
    await page.evaluate(() => {
      const options = (window as Window & { __turnstileOptions?: { callback?: (t: string) => void } }).__turnstileOptions;
      options?.callback?.("mock-turnstile-token");
    });

    await expect(page.getByTestId("lookup-submit")).toBeEnabled();
    await page.getByTestId("lookup-submit").click();
    await page.waitForTimeout(300);
    expect(submittedToken).toBe("mock-turnstile-token");
  });

  test("generic lookup failure resets token requirement", async ({ page }) => {
    await mockTurnstileConfig(page, turnstileEnabledConfig);
    await mockTurnstileScript(page);

    await page.route("**/laravel/lookup-booking", async (route) => {
      await route.fulfill({
        status: 422,
        contentType: "application/json",
        body: JSON.stringify({
          message: "The given data was invalid.",
          errors: { lookup: ["Booking not found for the provided reference and email."] },
        }),
      });
    });

    await page.goto("/lookup-booking");
    await lookupForm(page).getByRole("textbox", { name: /Booking reference/ }).fill("JPTEST01");
    await lookupForm(page).getByRole("textbox", { name: /Email address/ }).fill("wrong@example.com");
    await page.waitForFunction(() =>
      Boolean((window as Window & { __turnstileOptions?: { callback?: (t: string) => void } }).__turnstileOptions),
    );
    await page.evaluate(() => {
      const options = (window as Window & { __turnstileOptions?: { callback?: (t: string) => void } }).__turnstileOptions;
      options?.callback?.("mock-turnstile-token");
    });
    await expect(page.getByTestId("lookup-submit")).toBeEnabled();
    await page.getByTestId("lookup-submit").click();
    await expect(page.getByTestId("lookup-error")).toContainText("Booking not found");
  });

  test("turnstile rejection shows safe message", async ({ page }) => {
    await mockTurnstileConfig(page, turnstileEnabledConfig);
    await mockTurnstileScript(page);

    await page.route("**/laravel/lookup-booking", async (route) => {
      await route.fulfill({
        status: 422,
        contentType: "application/json",
        body: JSON.stringify({
          message: "The given data was invalid.",
          errors: { "cf-turnstile-response": ["Security check failed. Please refresh and try again."] },
        }),
      });
    });

    await page.goto("/lookup-booking");
    await lookupForm(page).getByRole("textbox", { name: /Booking reference/ }).fill("JPTEST01");
    await lookupForm(page).getByRole("textbox", { name: /Email address/ }).fill("test@example.com");
    await page.waitForFunction(() =>
      Boolean((window as Window & { __turnstileOptions?: { callback?: (t: string) => void } }).__turnstileOptions),
    );
    await page.evaluate(() => {
      const options = (window as Window & { __turnstileOptions?: { callback?: (t: string) => void } }).__turnstileOptions;
      options?.callback?.("mock-turnstile-token");
    });
    await expect(page.getByTestId("lookup-submit")).toBeEnabled();
    await page.getByTestId("lookup-submit").click();
    await expect(page.getByTestId("lookup-error")).toContainText("Security check failed");
  });

  test("turnstile-disabled configuration omits widget safely", async ({ page }) => {
    await mockTurnstileConfig(page, turnstileDisabledConfig);
    await page.goto("/lookup-booking");
    await expect(page.getByTestId("turnstile-widget")).toHaveCount(0);
    await expect(page.getByTestId("lookup-submit")).toBeEnabled();
  });

  test("script-load failure offers Blade fallback", async ({ page }) => {
    await mockTurnstileConfig(page, turnstileEnabledConfig);
    await page.route("**/challenges.cloudflare.com/turnstile/v0/api.js**", async (route) => {
      await route.abort("failed");
    });

    await page.goto("/lookup-booking");
    await expect(page.getByTestId("turnstile-unavailable")).toBeVisible();
    await expect(page.getByTestId("blade-lookup-fallback")).toBeVisible();
    await expect(page.getByTestId("lookup-submit")).toBeDisabled();
  });

  test("successful lookup redirects to canonical internal guest route", async ({ page }) => {
    await mockTurnstileConfig(page, turnstileDisabledConfig);
    await page.route("**/laravel/lookup-booking", async (route) => {
      if (route.request().method() !== "POST") {
        await route.continue();
        return;
      }
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          redirect_url: `${playwrightBaseUrl}/guest/bookings/1/access/redirect-token`,
        }),
      });
    });
    await page.route("**/laravel/guest/bookings/1/access/redirect-token?format=json", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ ok: true, booking_reference: "GUEST01E01" }),
      });
    });

    await page.goto("/lookup-booking");
    await lookupForm(page).getByRole("textbox", { name: /Booking reference/ }).fill("GUEST01E01");
    await lookupForm(page).getByRole("textbox", { name: /Email address/ }).fill("guest@example.test");
    await page.getByTestId("lookup-submit").click();
    await expect(page).toHaveURL(/\/guest\/bookings\/1\/access\/redirect-token/, { timeout: 15000 });
  });

  test("arbitrary success URL is rejected", async ({ page }) => {
    await mockTurnstileConfig(page, turnstileDisabledConfig);
    await page.route("**/laravel/lookup-booking", async (route) => {
      if (route.request().method() !== "POST") {
        await route.continue();
        return;
      }
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ ok: true, redirect_url: "https://evil.example/phish" }),
      });
    });

    await page.goto("/lookup-booking");

    await page.goto("/lookup-booking");
    await lookupForm(page).getByRole("textbox", { name: /Booking reference/ }).fill("JPTEST01");
    await lookupForm(page).getByRole("textbox", { name: /Email address/ }).fill("test@example.com");
    await page.getByTestId("lookup-submit").click();
    await expect(page.getByTestId("lookup-error")).toBeVisible();
  });
});

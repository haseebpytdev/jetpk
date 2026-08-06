import { expect, test } from "@playwright/test";
import { sessionFixtureCookieName } from "../features/auth/server/session-fixture";

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? "http://127.0.0.1:3002";

async function mockCsrf(page: import("@playwright/test").Page) {
  await page.route("**/laravel/api/public/content/csrf-token", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ csrf_token: "test-csrf-token" }),
      headers: { "set-cookie": "XSRF-TOKEN=test-csrf-token; Path=/" },
    });
  });
}

async function setFixture(page: import("@playwright/test").Page, fixture: string) {
  await page.context().addCookies([{ name: sessionFixtureCookieName, value: fixture, url: baseURL }]);
}

test.describe("JP-FULLSTACK-01A force-password", () => {
  test("unauthenticated user redirects to login", async ({ page }) => {
    await setFixture(page, "anonymous");
    await page.goto("/password/force-change");
    await expect(page).toHaveURL(/\/login$/);
  });

  test("customer with force-password requirement renders form", async ({ page }) => {
    await setFixture(page, "customer_force_password");
    await page.goto("/password/force-change");
    await expect(page).toHaveURL(/\/password\/force-change$/);
    await expect(page.getByRole("heading", { name: /change your password/i })).toBeVisible();
    await expect(page.locator("#main-content").getByLabel(/^new password/i)).toBeVisible();
    await expect(page.locator("#main-content").getByLabel(/confirm new password/i)).toBeVisible();
  });

  test("customer without requirement redirects to dashboard", async ({ page }) => {
    await setFixture(page, "customer");
    await page.goto("/password/force-change");
    await expect(page).toHaveURL(/\/customer\/dashboard$/);
  });

  test("customer portal redirects to force-password when required", async ({ page }) => {
    await setFixture(page, "customer_force_password");
    await page.goto("/customer/dashboard");
    await expect(page).toHaveURL(/\/password\/force-change$/);
  });

  test("agent portal redirects to force-password when required", async ({ page }) => {
    await setFixture(page, "agent_force_password");
    await page.goto("/agent/dashboard");
    await expect(page).toHaveURL(/\/password\/force-change$/);
  });

  test("successful submission redirects to customer dashboard", async ({ page }) => {
    await mockCsrf(page);
    await setFixture(page, "customer_force_password");

    await page.route("**/laravel/password/force-change?format=json", async (route) => {
      if (route.request().method() === "POST") {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
            body: JSON.stringify({
            ok: true,
            message: "Password updated. You can now access your account.",
            redirect: "/customer/bookings",
          }),
        });
        return;
      }
      await route.continue();
    });

    await page.goto("/password/force-change");
    await page.locator("#main-content").getByLabel(/^new password/i).fill("New-Secure-Pass-1!");
    await page.locator("#main-content").getByLabel(/confirm new password/i).fill("New-Secure-Pass-1!");
    await page.getByRole("button", { name: /save password/i }).click();
    await expect(page).toHaveURL(/\/customer\/bookings$/);
  });

  test("validation errors render without duplicate submission", async ({ page }) => {
    await mockCsrf(page);
    await setFixture(page, "customer_force_password");

    await page.route("**/laravel/password/force-change?format=json", async (route) => {
      if (route.request().method() === "POST") {
        await route.fulfill({
          status: 422,
          contentType: "application/json",
          body: JSON.stringify({
            message: "The password field confirmation does not match.",
            errors: { password: ["The password field confirmation does not match."] },
          }),
        });
        return;
      }
      await route.continue();
    });

    await page.goto("/password/force-change");
    await page.locator("#main-content").getByLabel(/^new password/i).fill("short");
    await page.locator("#main-content").getByLabel(/confirm new password/i).fill("mismatch");
    await page.getByRole("button", { name: /save password/i }).click();
    await expect(page.locator("#main-content").getByRole("alert").first()).toBeVisible();
    await expect(page).toHaveURL(/\/password\/force-change$/);
    await page.getByRole("button", { name: /save password/i }).click();
    await expect(page.getByRole("button", { name: /save password/i })).toBeEnabled();
  });

  test("419 csrf refresh retries once", async ({ page }) => {
    await mockCsrf(page);
    await setFixture(page, "customer_force_password");

    let postAttempts = 0;
    await page.route("**/laravel/password/force-change?format=json", async (route) => {
      if (route.request().method() === "POST") {
        postAttempts += 1;
        if (postAttempts === 1) {
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
          body: JSON.stringify({ ok: true, redirect: "/customer/bookings" }),
        });
        return;
      }
      await route.continue();
    });

    await page.goto("/password/force-change");
    await page.locator("#main-content").getByLabel(/^new password/i).fill("New-Secure-Pass-1!");
    await page.locator("#main-content").getByLabel(/confirm new password/i).fill("New-Secure-Pass-1!");
    await page.getByRole("button", { name: /save password/i }).click();
    await expect(page).toHaveURL(/\/customer\/bookings$/);
    expect(postAttempts).toBe(2);
  });

  test("agent success redirects to agent dashboard", async ({ page }) => {
    await mockCsrf(page);
    await setFixture(page, "agent_force_password");

    await page.route("**/laravel/password/force-change?format=json", async (route) => {
      if (route.request().method() === "POST") {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({ ok: true, redirect: "/agent" }),
        });
        return;
      }
      await route.continue();
    });

    await page.goto("/password/force-change");
    await page.locator("#main-content").getByLabel(/^new password/i).fill("New-Secure-Pass-1!");
    await page.locator("#main-content").getByLabel(/confirm new password/i).fill("New-Secure-Pass-1!");
    await page.getByRole("button", { name: /save password/i }).click();
    await expect(page).toHaveURL(/\/agent$/);
  });
});

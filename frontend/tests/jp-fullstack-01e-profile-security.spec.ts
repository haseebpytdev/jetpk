import { expect, test } from "@playwright/test";
import { sessionFixtureCookieName } from "../features/auth/server/session-fixture";

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? "http://127.0.0.1:3002";

async function setSessionFixture(page: import("@playwright/test").Page, fixture: string) {
  await page.context().addCookies([
    { name: sessionFixtureCookieName, value: fixture, url: baseURL },
    { name: "XSRF-TOKEN", value: "test-csrf-token", url: baseURL },
  ]);
}

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

const profileFixture = {
  ok: true,
  user: { name: "Ayesha Khan", email: "ayesha.khan@example.com", username: "ayesha", email_verified: true },
  profile: { phone: "+923001234567", city: "Lahore" },
};

test.describe("JP-FULLSTACK-01E profile and security verification", () => {
  test.beforeEach(async ({ page }) => {
    await setSessionFixture(page, "customer");
    await mockCsrf(page);
  });

  test("profile load", async ({ page }) => {
    await page.route("**/laravel/customer/profile?format=json", async (route) => {
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(profileFixture) });
    });
    await page.goto("/customer/profile");
    await expect(page.getByTestId("customer-profile-form")).toBeVisible();
    await expect(page.locator('[name="name"]')).toHaveValue("Ayesha Khan");
  });

  test("profile PATCH success", async ({ page }) => {
    await page.route("**/laravel/customer/profile?format=json", async (route) => {
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(profileFixture) });
    });
    await page.route("**/laravel/profile", async (route) => {
      if (route.request().method() === "POST") {
        await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ ok: true, message: "Updated." }) });
        return;
      }
      await route.continue();
    });
    await page.goto("/customer/profile");
    await page.locator('[name="name"]').fill("Ayesha K.");
    await page.getByRole("button", { name: /save profile/i }).click();
    await expect(page.getByText(/updated successfully/i)).toBeVisible();
  });

  test("profile PATCH 422", async ({ page }) => {
    await page.route("**/laravel/customer/profile?format=json", async (route) => {
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify(profileFixture) });
    });
    await page.route("**/laravel/profile", async (route) => {
      await route.fulfill({
        status: 422,
        contentType: "application/json",
        body: JSON.stringify({ ok: false, message: "Validation failed.", errors: { email: ["Invalid."] } }),
      });
    });
    await page.goto("/customer/profile");
    await page.getByRole("button", { name: /save profile/i }).click();
    await expect(page.getByText(/validation failed/i)).toBeVisible();
  });

  test("password PUT success", async ({ page }) => {
    await page.route("**/laravel/password", async (route) => {
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ ok: true, message: "Password updated." }) });
    });
    await page.goto("/customer/security");
    await page.locator('[name="current_password"]').fill("old-password");
    await page.locator('[name="password"]').fill("new-password-12");
    await page.locator('[name="password_confirmation"]').fill("new-password-12");
    await page.getByRole("button", { name: /change password/i }).click();
    await expect(page.getByText(/updated successfully/i)).toBeVisible();
  });

  test("password PUT 422", async ({ page }) => {
    await page.route("**/laravel/password", async (route) => {
      await route.fulfill({
        status: 422,
        contentType: "application/json",
        body: JSON.stringify({ ok: false, message: "Validation failed.", errors: { password: ["Too weak."] } }),
      });
    });
    await page.goto("/customer/security");
    await page.locator('[name="current_password"]').fill("old-password");
    await page.locator('[name="password"]').fill("short");
    await page.locator('[name="password_confirmation"]').fill("short");
    await page.getByRole("button", { name: /change password/i }).click();
    await expect(page.getByText(/validation failed/i)).toBeVisible();
  });

  test("expired session redirects to login", async ({ page }) => {
    await setSessionFixture(page, "expired");
    await page.goto("/customer/profile");
    await expect(page).toHaveURL(/\/login\?reason=session-expired/);
  });
});

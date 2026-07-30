import { expect, test } from "@playwright/test";

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

test.describe("JP-FE-04 authentication shell", () => {
  test("login page renders accessible form", async ({ page }) => {
    await page.goto("/login");
    await expect(page.getByRole("heading", { name: /log in to your account/i })).toBeVisible();
    await expect(page.locator("#main-content").getByLabel(/email or username/i)).toBeVisible();
    await expect(page.locator("#main-content").getByLabel(/^password/i)).toBeVisible();
    await expect(page.getByRole("link", { name: /forgot password/i })).toBeVisible();
    await expect(page.getByTestId("auth-form-card").getByRole("link", { name: /^sign up$/i })).toHaveAttribute("href", "/register");
  });

  test("register page renders customer fields", async ({ page }) => {
    await page.route("**/laravel/api/public/auth/registration-security-challenge", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ security_question: "What is 2 + 3?" }),
      });
    });

    await page.goto("/register");
    await expect(page.getByRole("heading", { name: /create your account/i })).toBeVisible();
    await expect(page.locator("#main-content").getByLabel(/^first name/i)).toBeVisible();
    await expect(page.locator("#main-content").getByLabel(/^email/i)).toBeVisible();
  });

  test("agent register page renders application form", async ({ page }) => {
    await page.goto("/agent/register");
    await expect(page.getByRole("heading", { name: /apply as a jetpakistan agent/i })).toBeVisible();
    await expect(page.locator("#main-content").getByLabel(/agency name/i)).toBeVisible();
  });

  test("forgot password page renders generic form", async ({ page }) => {
    await page.goto("/forgot-password");
    await expect(page.getByRole("heading", { name: /forgot password/i })).toBeVisible();
    await expect(page.locator("#main-content").getByLabel(/^email/i)).toBeVisible();
  });

  test("mobile login form remains usable", async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto("/login");
    await expect(page.getByRole("button", { name: /sign in/i })).toBeVisible();
    const box = await page.getByRole("button", { name: /sign in/i }).boundingBox();
    expect(box?.width ?? 0).toBeGreaterThan(200);
  });

  test("public shell shows login link when logged out", async ({ page }) => {
    await page.goto("/");
    await expect(page.getByRole("link", { name: /log in \/ sign up/i }).first()).toBeVisible();
  });
});

test.describe("JP-FE-04 Laravel auth API mocks", () => {
  test("session bootstrap logged-out mock", async ({ page }) => {
    await page.route("**/laravel/api/public/auth/session", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ authenticated: false }),
      });
    });

    await page.goto("/login");
    await expect(page.getByRole("heading", { name: /log in to your account/i })).toBeVisible();
  });

  test("login invalid credentials show generic error", async ({ page }) => {
    await mockCsrf(page);
    await page.route("**/laravel/login", async (route) => {
      await route.fulfill({
        status: 422,
        contentType: "application/json",
        body: JSON.stringify({
          message: "These credentials do not match our records.",
          errors: { login: ["These credentials do not match our records."] },
        }),
      });
    });

    await page.goto("/login");
    await page.locator("#main-content").getByLabel(/email or username/i).fill("unknown@example.test");
    await page.locator("#main-content").getByLabel(/^password/i).fill("WrongPassword1");
    await page.getByRole("button", { name: /sign in/i }).click();
    await expect(page.locator("#main-content").getByRole("alert").first()).toContainText(
      /these credentials do not match our records/i,
    );
  });

  test("login OTP challenge transition mock", async ({ page }) => {
    await mockCsrf(page);
    await page.route("**/laravel/login", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ ok: true, requires_otp: true, redirect: "/login/otp" }),
      });
    });
    await page.route("**/laravel/api/public/auth/otp-challenge", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ has_challenge: true, masked_email: "u***@example.test", resend_available_in: 0 }),
      });
    });

    await page.goto("/login");
    await page.locator("#main-content").getByLabel(/email or username/i).fill("user@example.test");
    await page.locator("#main-content").getByLabel(/^password/i).fill("SecretPass1");
    await page.getByRole("button", { name: /sign in/i }).click();
    await expect(page).toHaveURL(/\/login\/otp$/);
  });

  test("forgot password generic success state", async ({ page }) => {
    await mockCsrf(page);
    await page.route("**/laravel/forgot-password", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          message: "If an account exists for that email address, we have emailed password reset instructions.",
        }),
      });
    });

    await page.goto("/forgot-password");
    await page.locator("#main-content").getByLabel(/^email/i).fill("user@example.test");
    await page.getByRole("button", { name: /send reset link/i }).click();
    await expect(page.locator("#main-content").getByText(/emailed password reset instructions/i)).toBeVisible();
  });
});

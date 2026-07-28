import { test, expect } from "@playwright/test";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("about page renders hero, sections, and reduced-motion fallback", async ({ page }) => {
  await page.goto("/about-us", { waitUntil: "load" });
  await expect(page.getByRole("heading", { level: 1, name: /Cheap flights and secure/i })).toBeVisible();
  await expect(page.getByRole("heading", { name: "Our story" })).toBeVisible();
  await expect(page.getByRole("link", { name: "Search flights" })).toBeVisible();

  await page.emulateMedia({ reducedMotion: "reduce" });
  await page.reload({ waitUntil: "load" });
  await expect(page.getByRole("img", { name: "Decorative flight path" })).toBeVisible();
});

test("support page renders categories and verified contact channels", async ({ page }) => {
  await page.goto("/support", { waitUntil: "load" });
  await expect(page.getByRole("heading", { level: 1, name: /Flight booking help/i })).toBeVisible();
  await expect(page.getByText("Flight search and booking")).toBeVisible();
  await expect(page.getByRole("link", { name: /0311 1222427/i })).toBeVisible();
  await expect(page.getByRole("link", { name: /ota@jetpakistan\.pk/i })).toBeVisible();
});

test("faq search and category filtering", async ({ page }) => {
  await page.goto("/faq", { waitUntil: "load" });
  await page.getByLabel("Search FAQs").fill("payment");
  await expect(page.getByRole("button", { name: /What payment methods/i })).toBeVisible();
  await page.getByLabel("Search FAQs").fill("");
  await page.getByRole("tab", { name: "Booking", exact: true }).click();
  await expect(page.getByRole("button", { name: /How do I book a flight/i })).toBeVisible();
});

test("faq keyboard accordion behavior", async ({ page }) => {
  await page.goto("/faq", { waitUntil: "load" });
  const trigger = page.getByRole("button", { name: /How do I book a flight/i });
  await trigger.focus();
  await page.keyboard.press("Enter");
  await expect(trigger).toHaveAttribute("aria-expanded", "true");
  await page.keyboard.press(" ");
  await expect(trigger).toHaveAttribute("aria-expanded", "false");
});

test("contact form validation", async ({ page }) => {
  await page.route("**/laravel/support", async (route) => {
    await route.fulfill({
      status: 422,
      contentType: "application/json",
      body: JSON.stringify({
        message: "The given data was invalid.",
        errors: { email: ["The email field must be a valid email address."] },
      }),
    });
  });

  await page.goto("/contact", { waitUntil: "load" });
  const form = page.getByTestId("contact-form");
  await form.getByLabel("Your name").fill("Test User");
  await form.getByLabel("Email").fill("not-an-email");
  await form.getByLabel("Message").fill("Validation test");
  await form.getByRole("button", { name: "Send message" }).click();
  await expect(page.getByText(/valid email/i)).toBeVisible();
});

test("contact form successful Laravel handoff when endpoint exists", async ({ page }) => {
  await page.route("**/laravel/api/public/content/csrf-token", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ csrf_token: "test-token" }),
    });
  });
  await page.route("**/laravel/support", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ ok: true, ticket_reference: "SABC1234" }),
    });
  });

  await page.goto("/contact", { waitUntil: "load" });
  const form = page.getByTestId("contact-form");
  await form.getByLabel("Your name").fill("Test User");
  await form.getByLabel("Email").fill("test@example.com");
  await form.getByLabel("Message").fill("Hello from Playwright.");
  await form.getByRole("button", { name: "Send message" }).click();
  await expect(page.getByText("SABC1234")).toBeVisible();
});

test("contact form duplicate-submit prevention", async ({ page }) => {
  let requestCount = 0;
  await page.route("**/laravel/api/public/content/csrf-token", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ csrf_token: "test-token" }),
    });
  });
  await page.route("**/laravel/support", async (route) => {
    requestCount += 1;
    await new Promise((resolve) => setTimeout(resolve, 500));
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ ok: true, ticket_reference: "SXYZ9876" }),
    });
  });

  await page.goto("/contact", { waitUntil: "load" });
  const form = page.getByTestId("contact-form");
  await form.getByLabel("Your name").fill("Test User");
  await form.getByLabel("Email").fill("test@example.com");
  await form.getByLabel("Message").fill("Duplicate prevention test.");
  const button = form.locator('button[type="submit"]');
  await button.click();
  await expect(button).toBeDisabled();
  await expect(page.getByText("SXYZ9876")).toBeVisible();
  expect(requestCount).toBe(1);
});

test("terms page table-of-contents anchors", async ({ page }) => {
  await page.goto("/terms", { waitUntil: "load" });
  await expect(page.getByRole("navigation", { name: "Table of contents" })).toBeVisible();
  await page.getByRole("link", { name: "Bookings and payments" }).click();
  await expect(page.locator("#terms-2")).toBeInViewport();
});

test("privacy page rendering", async ({ page }) => {
  await page.goto("/privacy", { waitUntil: "load" });
  await expect(page.getByRole("heading", { level: 1, name: "Privacy policy" })).toBeVisible();
  await expect(page.getByRole("heading", { name: "Information we collect" })).toBeVisible();
});

test("cms page unknown slug returns branded 404", async ({ page }) => {
  await page.route("**/laravel/api/public/content/cms/**", async (route) => {
    await route.fulfill({ status: 404, contentType: "application/json", body: "{}" });
  });
  const response = await page.goto("/pages/does-not-exist", { waitUntil: "load" });
  expect(response?.status()).toBe(404);
  await expect(page.getByRole("heading", { name: "Page not found" })).toBeVisible();
});

test("branded 404 route", async ({ page }) => {
  const response = await page.goto("/this-route-does-not-exist", { waitUntil: "load" });
  expect(response?.status()).toBe(404);
  await expect(page.getByRole("link", { name: "Search flights" })).toBeVisible();
});

test("mobile public navigation includes support links", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/", { waitUntil: "load" });
  await page.getByRole("button", { name: "Open navigation menu" }).click();
  const mobileNav = page.getByRole("navigation", { name: "Mobile primary" });
  await expect(mobileNav.getByRole("link", { name: "Contact Us" })).toHaveAttribute("href", "/contact");
  await expect(mobileNav.getByRole("link", { name: "FAQs" })).toHaveAttribute("href", "/faq");
});

test("homepage search regression", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });
  await expect(page.getByTestId("search-module")).toBeVisible();
  await expect(page.getByRole("button", { name: "Search Flights" })).toBeVisible();
});

test("mobile viewport public page checks", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/contact", { waitUntil: "load" });
  const form = page.getByTestId("contact-form");
  await expect(form.getByLabel("Email")).toBeVisible();
  await expect(page.getByRole("link", { name: /0311 1222427/i })).toBeVisible();
});

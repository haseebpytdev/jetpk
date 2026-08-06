import { expect, test, type Page } from "@playwright/test";
import { attachRuntimeGuards } from "./helpers";

const FIXTURE_ABOUT_HEADING = "Our story";

async function mockJsonRoute(page: Page, urlPart: string, status: number, body?: unknown) {
  await page.route(`**/laravel${urlPart}**`, async (route) => {
    await route.fulfill({
      status,
      contentType: "application/json",
      body: body === undefined ? "" : JSON.stringify(body),
    });
  });
}

const POPULATED_ABOUT_PAYLOAD = {
  source: "cms",
  content: {
    hero: {
      kicker: "About JetPakistan",
      title: "Cheap flights and secure online booking for Pakistan",
      description: "JetPakistan helps travellers discover low fares.",
    },
    content_grid: {
      items: [
        {
          id: "story",
          title: "Our story",
          body: "JetPakistan brings airline options together in one place.",
          enabled: "1",
        },
      ],
    },
  },
  seo: { title: "About JetPakistan" },
};

test.describe("JP-FULL-NEXT-FRONTEND-01B CMS bridge", () => {
  test("about page renders CMS managed content from Laravel", async ({ page }) => {
    const guards = await attachRuntimeGuards(page);
    await page.goto("/about-us", { waitUntil: "load" });
    await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
    await expect(page.locator("#main-content [onclick], #main-content [onerror]")).toHaveCount(0);
    await guards.assertClean();
  });

  test("faq page renders sanitized CMS content", async ({ page }) => {
    await page.goto("/faq", { waitUntil: "load" });
    await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
    await expect(page.locator("#main-content [onclick], #main-content [onerror]")).toHaveCount(0);
  });

  test("contact remains canonical dedicated page", async ({ page }) => {
    const response = await page.goto("/contact");
    expect(response?.status()).toBeLessThan(500);
    await expect(page).toHaveURL(/\/contact$/);
    await expect(page.getByTestId("contact-form")).toBeVisible();
  });

  test("verify-email reserved from CMS catch-all", async ({ page }) => {
    const response = await page.goto("/verify-email");
    expect(response?.status()).toBe(200);
    await expect(page.getByTestId("auth-form-card").getByRole("heading", { level: 1 })).toBeVisible();
  });

  test("CMS dark theme renders readable content", async ({ page }) => {
    await mockJsonRoute(page, "/api/public/content/pages/about", 200, POPULATED_ABOUT_PAYLOAD);
    await page.addInitScript(() => localStorage.setItem("jp-theme-preference", "dark"));
    await page.emulateMedia({ colorScheme: "dark" });
    await page.goto("/about-us", { waitUntil: "load" });
    await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
    await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
  });
});

test.describe("JP-FULLSTACK-01G CMS static routes", () => {
  test("homepage renders without server error", async ({ page }) => {
    const guards = await attachRuntimeGuards(page);
    const response = await page.goto("/", { waitUntil: "domcontentloaded" });
    expect(response?.status()).toBeLessThan(500);
    await expect(page.locator("body")).not.toContainText(/stack trace|ENOENT|Exception/i);
    await guards.assertClean();
  });

  test("support page renders without unsafe handlers", async ({ page }) => {
    await page.goto("/support", { waitUntil: "load" });
    await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
    await expect(page.locator("#main-content [onclick], #main-content [onerror]")).toHaveCount(0);
  });

  test("terms and privacy render legal shells", async ({ page }) => {
    for (const path of ["/terms", "/privacy"]) {
      const response = await page.goto(path, { waitUntil: "load" });
      expect(response?.status()).toBeLessThan(500);
      await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
    }
  });

  test("sitemap renders link list shell", async ({ page }) => {
    await page.goto("/sitemap", { waitUntil: "load" });
    await expect(page.getByRole("heading", { name: "Sitemap" })).toBeVisible();
  });

  test("about empty CMS response stays usable without fixture story section", async ({ page }) => {
    await mockJsonRoute(page, "/api/public/content/pages/about", 200, {
      source: "empty",
      content: {},
      seo: { title: "About JetPakistan" },
    });
    await page.goto("/about-us", { waitUntil: "load" });
    await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
    await expect(page.getByRole("heading", { name: FIXTURE_ABOUT_HEADING })).toHaveCount(0);
  });

  test("contact empty site-contact does not expose fixture office line", async ({ page }) => {
    await mockJsonRoute(page, "/api/public/content/site-contact", 200, { contact: null });
    await page.goto("/contact", { waitUntil: "load" });
    await expect(page.getByTestId("contact-form")).toBeVisible();
    await expect(page.locator("body")).not.toContainText("Century Tower, Kalma Chowk");
  });
});

test.describe("JP-FULLSTACK-01G CMS dynamic routes", () => {
  test("valid custom slug renders CMS page when Laravel publishes content", async ({ page }) => {
    const response = await page.goto("/about-us", { waitUntil: "load" });
    expect(response?.status()).toBe(200);
    await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
  });

  test("valid legal slug route resolves or returns not found without unsafe output", async ({ page }) => {
    const response = await page.goto("/legal/terms", { waitUntil: "domcontentloaded" });
    expect([200, 404]).toContain(response?.status() ?? 0);
    await expect(page.locator("body")).not.toContainText(/stack trace|Exception in/i);
  });

  test("valid pages slug route resolves or returns not found without unsafe output", async ({ page }) => {
    const response = await page.goto("/pages/about", { waitUntil: "domcontentloaded" });
    expect([200, 404]).toContain(response?.status() ?? 0);
    await expect(page.locator("body")).not.toContainText(/stack trace|Exception in/i);
  });

  test("unknown custom slug returns not found", async ({ page }) => {
    await mockJsonRoute(page, "/api/public/content/custom/unknown-custom-slug-01g", 404);
    const response = await page.goto("/unknown-custom-slug-01g", { waitUntil: "domcontentloaded" });
    expect(response?.status()).toBe(404);
  });

  test("valid legal slug renders CMS page when Laravel publishes content", async ({ page }) => {
    const response = await page.goto("/terms", { waitUntil: "load" });
    expect(response?.status()).toBe(200);
    await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
  });

  test("unknown legal slug returns not found", async ({ page }) => {
    await mockJsonRoute(page, "/api/public/content/custom/unknown-legal-01g", 404);
    const response = await page.goto("/legal/unknown-legal-01g", { waitUntil: "domcontentloaded" });
    expect(response?.status()).toBe(404);
  });

  test("unknown pages slug returns not found", async ({ page }) => {
    await mockJsonRoute(page, "/api/public/content/cms/unknown-pages-01g", 404);
    const response = await page.goto("/pages/unknown-pages-01g", { waitUntil: "domcontentloaded" });
    expect(response?.status()).toBe(404);
  });

  test("reserved login slug is not captured by CMS catch-all", async ({ page }) => {
    const response = await page.goto("/login", { waitUntil: "domcontentloaded" });
    expect(response?.status()).toBe(200);
    await expect(page.getByTestId("auth-form-card")).toBeVisible();
  });
});

test.describe("JP-FULLSTACK-01G CMS failure states", () => {
  test("about handles Laravel 500 without stack trace", async ({ page }) => {
    await mockJsonRoute(page, "/api/public/content/pages/about", 500, { message: "server error" });
    const response = await page.goto("/about-us", { waitUntil: "load" });
    expect(response?.status()).toBeLessThan(500);
    await expect(page.locator("body")).not.toContainText(/stack trace|Exception in/i);
  });

  test("sitemap handles empty route list", async ({ page }) => {
    await mockJsonRoute(page, "/api/public/content/sitemap-routes", 200, { routes: [] });
    await page.goto("/sitemap", { waitUntil: "load" });
    await expect(page.getByRole("heading", { name: "Sitemap" })).toBeVisible();
  });

  test("malformed CMS HTML is rejected safely", async ({ page }) => {
    await mockJsonRoute(page, "/api/public/content/cms/unsafe", 200, {
      slug: "unsafe",
      title: "Unsafe",
      body_html: '<img src=x onerror="alert(1)">',
      seo: { title: "Unsafe" },
    });
    const response = await page.goto("/pages/unsafe", { waitUntil: "domcontentloaded" });
    expect([404, 200]).toContain(response?.status() ?? 0);
    await expect(page.locator("#main-content img[onerror]")).toHaveCount(0);
  });
});

import { expect, test, type Page } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";

const evidenceDir = path.resolve(__dirname, "../../docs/evidence/jp-ask-ui-01");
const viewports = [
  { name: "desktop-1280", width: 1280, height: 800 },
  { name: "mobile-430", width: 430, height: 932 },
  { name: "mobile-390", width: 390, height: 844 },
  { name: "mobile-360", width: 360, height: 800 },
  { name: "mobile-320", width: 320, height: 568 },
] as const;

async function enableAssistant(page: Page) {
  const configBody = {
    brand_name: "JetPakistan",
    domain: "jetpakistan.pk",
    app_url: "http://127.0.0.1:3002",
    ai_assistant_enabled: true,
    contact: {
      phone: "+92 311 1222427",
      email: "ota@jetpakistan.com",
      whatsapp: "+92 311 1222427",
    },
    legal_paths: { terms: "/terms", privacy: "/privacy" },
    support_path: "/support",
    contact_path: "/contact",
    booking_lookup_path: "/lookup-booking",
    groups_path: "/groups",
    social_links: [],
    default_seo: { title: "JetPakistan", description: "JetPakistan", robots: "index,follow" },
    source: "laravel",
  };

  await page.route("**/laravel/api/public/content/config", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(configBody),
    });
  });

  await page.route("**/api/public/content/config", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(configBody),
    });
  });
}

async function assertNoHorizontalOverflow(page: Page) {
  const overflow = await page.evaluate(() => {
    const doc = document.documentElement;
    return doc.scrollWidth - doc.clientWidth;
  });
  expect(overflow).toBeLessThanOrEqual(1);
}

test.beforeAll(() => {
  fs.mkdirSync(evidenceDir, { recursive: true });
});

test.beforeEach(async ({ page }) => {
  await enableAssistant(page);
});

for (const viewport of viewports) {
  test(`ask assistant layout ${viewport.name}`, async ({ page }) => {
    await page.setViewportSize({ width: viewport.width, height: viewport.height });
    await page.goto("/", { waitUntil: "load" });

    const askFab = page.getByTestId("ask-jetpakistan-fab");
    await expect(askFab).toBeVisible({ timeout: 30_000 });
    await expect(page.getByTestId("human-support-fab")).toHaveCount(0);

    await assertNoHorizontalOverflow(page);
    await page.screenshot({
      path: path.join(evidenceDir, `home-${viewport.name}.png`),
      fullPage: false,
    });

    await askFab.click();
    await expect(page.getByTestId("ask-jetpakistan-panel")).toBeVisible();
    await expect(page.getByRole("heading", { name: "Ask JetPakistan" })).toBeVisible();
    await expect(page.getByText("Online")).toBeVisible();

    await page.screenshot({
      path: path.join(evidenceDir, `assistant-open-${viewport.name}.png`),
      fullPage: false,
    });

    await page.keyboard.press("Escape");
    await expect(page.getByTestId("ask-jetpakistan-panel")).toHaveCount(0);
    await expect(askFab).toBeVisible();
  });
}

test("support links and quick actions inside assistant", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 800 });
  await page.goto("/", { waitUntil: "load" });

  await page.getByTestId("ask-jetpakistan-fab").click();
  await expect(page.getByRole("button", { name: "Talk to Support" })).toBeVisible();

  await page.getByRole("button", { name: "Chat options" }).click();
  await expect(page.getByRole("menuitem", { name: "Open support centre" })).toHaveAttribute(
    "href",
    "/support",
  );
});

test("chat send uses existing AI API contract", async ({ page }) => {
  let chatHit = false;

  await page.context().clearCookies();
  await page.route("**/laravel/api/public/content/csrf-token", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ csrf_token: "test-csrf-token" }),
      headers: { "set-cookie": "XSRF-TOKEN=test-csrf-token; Path=/" },
    });
  });

  await page.route("**/laravel/api/public/ai/chat", async (route) => {
    chatHit = true;
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        conversation_id: "conv-test-01",
        message: "Fixture assistant reply for JP-ASK-UI-01.",
        recommendations: [],
        actions: [],
      }),
    });
  });

  await page.route("**/api/public/ai/chat", async (route) => {
    chatHit = true;
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        conversation_id: "conv-test-01",
        message: "Fixture assistant reply for JP-ASK-UI-01.",
        recommendations: [],
        actions: [],
      }),
    });
  });

  await page.setViewportSize({ width: 1280, height: 800 });
  await page.goto("/", { waitUntil: "load" });
  await page.getByTestId("ask-jetpakistan-fab").click();
  await page.getByRole("textbox", { name: "Message Ask JetPakistan" }).fill("Hello JetPakistan");
  await page.getByRole("button", { name: "Send message" }).click();

  await expect(page.getByText("Fixture assistant reply for JP-ASK-UI-01.")).toBeVisible();
  expect(chatHit).toBe(true);
});

test("support page FAQ accordions and form regression", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 800 });
  await page.goto("/support", { waitUntil: "load" });

  await expect(page.getByTestId("support-faq-preview")).toBeVisible();
  const faqTrigger = page.getByTestId("support-faq-preview").getByRole("button").first();
  await faqTrigger.click();
  await expect(faqTrigger).toHaveAttribute("aria-expanded", "true");

  await expect(page.getByRole("heading", { name: "Contact Us" })).toBeVisible();
  await expect(page.getByRole("heading", { name: "Submit a support request" })).toBeVisible();
  await expect(page.getByTestId("support-form")).toBeVisible({ timeout: 15_000 });

  await page.screenshot({
    path: path.join(evidenceDir, "support-desktop-1280.png"),
    fullPage: true,
  });
});

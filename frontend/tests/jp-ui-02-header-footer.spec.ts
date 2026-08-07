import { test, expect } from "@playwright/test";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("header exposes theme switch and authoritative navigation", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/", { waitUntil: "load" });

  await expect(page.getByRole("navigation", { name: "Primary" })).toBeVisible();
  await expect(page.getByTestId("theme-switch")).toBeVisible();
  await expect(page.getByRole("link", { name: "Book Now" })).toHaveAttribute("href", "/#flight-search");
  await expect(page.locator('a[href="#"]')).toHaveCount(0);
  await expect(page.getByRole("link", { name: /^Hotels$/i })).toHaveCount(0);
  await expect(page.getByRole("link", { name: /^Offers$/i })).toHaveCount(0);
  await expect(page.getByRole("link", { name: /^Travel Services$/i })).toHaveCount(0);
});

test("footer has authoritative columns without newsletter stub", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });
  await expect(page.getByRole("contentinfo")).toBeVisible();
  await expect(page.getByRole("contentinfo").getByRole("heading", { name: "Explore", exact: true })).toBeVisible();
  await expect(page.getByRole("contentinfo").getByRole("heading", { name: "Legal", exact: true })).toBeVisible();
  await expect(page.getByText("Stay Updated")).toHaveCount(0);
  await expect(page.getByRole("button", { name: "Subscribe" })).toHaveCount(0);
});

test("footer social links use safe rel attributes", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });
  const social = page.getByRole("contentinfo").getByRole("link", { name: "Facebook" });
  await expect(social).toHaveAttribute("rel", /noopener/);
  await expect(social).toHaveAttribute("target", "_blank");
});

test("mobile drawer closes with escape and returns focus", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/", { waitUntil: "load" });
  const trigger = page.getByRole("button", { name: "Open navigation menu" });
  await trigger.click();
  await page.keyboard.press("Escape");
  await expect(page.getByRole("dialog", { name: "Mobile navigation" })).toBeHidden();
  await expect(trigger).toBeFocused();
});

test("dark theme applies to header and footer surfaces", async ({ page }) => {
  await page.addInitScript(() => localStorage.setItem("jp-theme-preference", "dark"));
  await page.emulateMedia({ colorScheme: "dark" });
  await page.goto("/", { waitUntil: "load" });
  await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
  await expect(page.getByRole("banner")).toBeVisible();
  await expect(page.getByRole("contentinfo")).toBeVisible();
});

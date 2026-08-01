import { test, expect } from "@playwright/test";
import { isThemeLabAllowed } from "../features/public-theme-v2/lab/is-theme-lab-allowed";

const HOMEPAGE_V2_PATH = "/__dev/jetpk-homepage-v2";

test.beforeAll(async ({ request }) => {
  expect((await request.get(HOMEPAGE_V2_PATH, { timeout: 120_000 })).ok()).toBeTruthy();
});

test("homepage v2 review route renders composition", async ({ page }) => {
  await page.goto(HOMEPAGE_V2_PATH, { waitUntil: "load" });
  await expect(page.getByTestId("jp-homepage-v2-composition")).toBeVisible();
  await expect(page.getByTestId("jp-hp-header")).toBeVisible();
  await expect(page.getByTestId("jp-hp-hero")).toBeVisible();
  await expect(page.getByTestId("jp-hp-search-panel")).toBeVisible();
  await expect(page.getByTestId("jp-hp-footer")).toBeVisible();
});

test("homepage v2 has noindex robots metadata", async ({ page }) => {
  await page.goto(HOMEPAGE_V2_PATH, { waitUntil: "load" });
  const robots = await page.locator('meta[name="robots"]').getAttribute("content");
  expect(robots?.toLowerCase()).toContain("noindex");
  expect(robots?.toLowerCase()).toContain("nofollow");
});

test("homepage v2 section and card counts", async ({ page }) => {
  await page.goto(HOMEPAGE_V2_PATH, { waitUntil: "load" });
  await expect(page.getByTestId("jp-hp-benefit-item")).toHaveCount(4);
  await expect(page.getByTestId("jp-hp-destination-card")).toHaveCount(5);
  await expect(page.getByTestId("jp-hp-offer-card")).toHaveCount(3);
  await expect(page.getByTestId("jp-hp-why-item")).toHaveCount(5);
  await expect(page.getByTestId("jp-hp-support")).toHaveCount(1);
  await expect(page.getByTestId("jp-hp-inspiration-card")).toHaveCount(4);
  await expect(page.getByTestId("jp-hp-discover")).toHaveCount(1);
});

test("homepage v2 theme switching updates data-jp-theme", async ({ page }) => {
  await page.goto(HOMEPAGE_V2_PATH, { waitUntil: "load" });
  const root = page.getByTestId("jp-theme-v2-root");
  await expect(root).toHaveAttribute("data-jp-theme", "light");
  await page.getByTestId("jp-hp-theme-toggle").click();
  await expect(root).toHaveAttribute("data-jp-theme", "dark");
});

test("homepage v2 has no horizontal overflow at mobile and tablet", async ({ page }) => {
  for (const viewport of [
    { width: 390, height: 844 },
    { width: 768, height: 1024 },
  ]) {
    await page.setViewportSize(viewport);
    await page.goto(HOMEPAGE_V2_PATH, { waitUntil: "load" });
    const overflow = await page.evaluate(() => {
      return document.documentElement.scrollWidth > document.documentElement.clientWidth;
    });
    expect(overflow).toBe(false);
  }
});

test("production homepage is not wired to V2 homepage composition", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });
  await expect(page.locator(".jp-homepage-v2")).toHaveCount(0);
  await expect(page.getByTestId("jp-homepage-v2-composition")).toHaveCount(0);
});

test("search CTA does not navigate or submit", async ({ page }) => {
  await page.goto(HOMEPAGE_V2_PATH, { waitUntil: "load" });
  const requests: string[] = [];
  page.on("request", (req) => {
    if (req.method() === "POST" || req.url().includes("/laravel/") || req.url().includes("/flights")) {
      requests.push(req.url());
    }
  });
  await page.getByTestId("jp-hp-search-cta").click();
  await page.waitForTimeout(500);
  expect(requests).toHaveLength(0);
  expect(page.url()).toContain(HOMEPAGE_V2_PATH);
});

test("review route is not linked from production homepage navigation", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });
  const links = await page.locator("a[href]").evaluateAll((els) =>
    els.map((el) => el.getAttribute("href") ?? ""),
  );
  expect(links.some((href) => href.includes("jetpk-homepage-v2"))).toBe(false);
});

test("search panel is marked as review fixture", async ({ page }) => {
  await page.goto(HOMEPAGE_V2_PATH, { waitUntil: "load" });
  await expect(page.getByTestId("jp-hp-search-panel")).toHaveAttribute("data-review-fixture", "true");
});

test("isThemeLabAllowed respects environment flag", () => {
  const original = process.env.JP_THEME_LAB_ENABLED;
  process.env.JP_THEME_LAB_ENABLED = "true";
  expect(isThemeLabAllowed()).toBe(true);
  process.env.JP_THEME_LAB_ENABLED = "false";
  expect(isThemeLabAllowed()).toBe(process.env.NODE_ENV !== "production");
  if (original === undefined) {
    delete process.env.JP_THEME_LAB_ENABLED;
  } else {
    process.env.JP_THEME_LAB_ENABLED = original;
  }
});

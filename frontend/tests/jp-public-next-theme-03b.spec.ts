import { test, expect } from "@playwright/test";
import { isThemeLabAllowed } from "../features/public-theme-v2/lab/is-theme-lab-allowed";

const HOMEPAGE_V2_PATH = "/__dev/jetpk-homepage-v2";
const CAPTURE_PATH = "/__dev/jetpk-homepage-v2?capture=1";

test.beforeAll(async ({ request }) => {
  expect((await request.get(HOMEPAGE_V2_PATH, { timeout: 120_000 })).ok()).toBeTruthy();
});

test("review route renders shell composition", async ({ page }) => {
  await page.goto(HOMEPAGE_V2_PATH, { waitUntil: "load" });
  await expect(page.getByTestId("jp-homepage-v2-composition")).toBeVisible();
  await expect(page.getByTestId("jp-hp-header")).toBeVisible();
  await expect(page.getByTestId("jp-hp-footer")).toBeVisible();
});

test("noindex,nofollow metadata", async ({ page }) => {
  await page.goto(HOMEPAGE_V2_PATH, { waitUntil: "load" });
  const robots = await page.locator('meta[name="robots"]').getAttribute("content");
  expect(robots?.toLowerCase()).toContain("noindex");
  expect(robots?.toLowerCase()).toContain("nofollow");
});

test("required section and card counts", async ({ page }) => {
  await page.goto(HOMEPAGE_V2_PATH, { waitUntil: "load" });
  await expect(page.getByTestId("jp-hp-benefit-item")).toHaveCount(4);
  await expect(page.getByTestId("jp-hp-destination-card")).toHaveCount(5);
  await expect(page.getByTestId("jp-hp-offer-card")).toHaveCount(3);
  await expect(page.getByTestId("jp-hp-why-item")).toHaveCount(5);
  await expect(page.getByTestId("jp-hp-support")).toHaveCount(1);
  await expect(page.getByTestId("jp-hp-inspiration-card")).toHaveCount(4);
});

test("header starts at y=0 in capture mode", async ({ page }) => {
  await page.setViewportSize({ width: 1122, height: 1330 });
  await page.goto(CAPTURE_PATH, { waitUntil: "load" });
  const headerBox = await page.locator('[data-landmark="header"]').boundingBox();
  expect(headerBox?.y ?? 99).toBeLessThan(2);
  await expect(page.locator(".jp-homepage-v2__dev-marker")).toHaveCount(0);
});

test("no horizontal overflow at mobile and tablet", async ({ page }) => {
  for (const viewport of [
    { width: 390, height: 844 },
    { width: 768, height: 1024 },
  ]) {
    await page.setViewportSize(viewport);
    await page.goto(HOMEPAGE_V2_PATH, { waitUntil: "load" });
    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
    );
    expect(overflow).toBe(false);
  }
});

test("light and dark theme toggle", async ({ page }) => {
  await page.goto(HOMEPAGE_V2_PATH, { waitUntil: "load" });
  const root = page.getByTestId("jp-theme-v2-root");
  await expect(root).toHaveAttribute("data-jp-theme", "light");
  await page.getByTestId("jp-hp-theme-toggle").click();
  await expect(root).toHaveAttribute("data-jp-theme", "dark");
});

test("production homepage is not wired to V2 composition", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });
  await expect(page.locator(".jp-homepage-v2")).toHaveCount(0);
});

test("search CTA does not submit or navigate", async ({ page }) => {
  await page.goto(HOMEPAGE_V2_PATH, { waitUntil: "load" });
  const requests: string[] = [];
  page.on("request", (req) => {
    if (req.method() === "POST" || req.url().includes("/laravel/") || req.url().includes("/flights")) {
      requests.push(req.url());
    }
  });
  await page.getByTestId("jp-hp-search-cta").click();
  await page.waitForTimeout(400);
  expect(requests).toHaveLength(0);
});

test("no href hash links on review route", async ({ page }) => {
  await page.goto(HOMEPAGE_V2_PATH, { waitUntil: "load" });
  const hashes = await page.locator('a[href="#"], a[href=""]').count();
  expect(hashes).toBe(0);
});

test("review route not linked from production navigation", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });
  const links = await page.locator("a[href]").evaluateAll((els) =>
    els.map((el) => el.getAttribute("href") ?? ""),
  );
  expect(links.some((href) => href.includes("jetpk-homepage-v2"))).toBe(false);
});

test("footer visible in canonical fold capture", async ({ page }) => {
  await page.setViewportSize({ width: 1122, height: 1330 });
  await page.goto(CAPTURE_PATH, { waitUntil: "load" });
  await expect(page.locator('[data-landmark="footer"]')).toBeVisible();
  const footerBox = await page.locator('[data-landmark="footer"]').boundingBox();
  expect(footerBox).not.toBeNull();
});

test("canonical geometry within contract tolerances", async ({ page }) => {
  await page.setViewportSize({ width: 1122, height: 1330 });
  await page.goto(CAPTURE_PATH, { waitUntil: "load" });

  const metrics = await page.evaluate(() => {
    const footer = document.querySelector('[data-landmark="footer"]');
    const footerRect = footer?.getBoundingClientRect();
    const docScrollHeight = document.documentElement.scrollHeight;
    const bodyScrollHeight = document.body.scrollHeight;
    const footerY = footerRect ? Math.round(footerRect.y + window.scrollY) : null;
    const footerBottom = footerRect ? Math.round(footerRect.bottom + window.scrollY) : null;
    const footerHeight = footerRect ? Math.round(footerRect.height) : null;
    const emptyBelowFooter =
      footerBottom != null ? docScrollHeight - footerBottom : null;
    return {
      scrollHeight: docScrollHeight,
      bodyScrollHeight,
      scrollWidth: document.documentElement.scrollWidth,
      footerY,
      footerHeight,
      footerBottom,
      emptyBelowFooter,
      documentFooterDelta:
        footerBottom != null ? Math.abs(docScrollHeight - footerBottom) : null,
    };
  });

  expect(metrics.scrollHeight).toBeGreaterThanOrEqual(1330 - 8);
  expect(metrics.scrollHeight).toBeLessThanOrEqual(1330 + 8);
  expect(metrics.scrollWidth).toBeLessThanOrEqual(1123);
  expect(metrics.footerY).toBeGreaterThanOrEqual(1195 - 8);
  expect(metrics.footerY).toBeLessThanOrEqual(1195 + 8);
  expect(metrics.footerBottom).toBeGreaterThanOrEqual(1330 - 8);
  expect(metrics.footerBottom).toBeLessThanOrEqual(1330 + 8);
  expect(metrics.emptyBelowFooter).toBeLessThanOrEqual(8);
  expect(metrics.documentFooterDelta).toBeLessThanOrEqual(8);
});

test("no element-bound horizontal overflow at canonical viewport", async ({ page }) => {
  await page.setViewportSize({ width: 1122, height: 1330 });
  await page.goto(CAPTURE_PATH, { waitUntil: "load" });

  const overflow = await page.evaluate(() => {
    const names = [
      "header",
      "hero",
      "search",
      "benefits",
      "discover",
      "destinations",
      "offers",
      "why",
      "support",
      "inspiration",
      "footer",
    ];
    return names
      .map((name) => {
        const el = document.querySelector(`[data-landmark="${name}"]`);
        if (!el) return { name, ok: false };
        const rect = el.getBoundingClientRect();
        return {
          name,
          ok: rect.left >= -1 && rect.right <= 1123,
          left: rect.left,
          right: rect.right,
        };
      })
      .filter((r) => !r.ok);
  });

  expect(overflow).toHaveLength(0);
});

test("isThemeLabAllowed respects environment flag", () => {
  const original = process.env.JP_THEME_LAB_ENABLED;
  process.env.JP_THEME_LAB_ENABLED = "true";
  expect(isThemeLabAllowed()).toBe(true);
  process.env.JP_THEME_LAB_ENABLED = "false";
  expect(isThemeLabAllowed()).toBe(process.env.NODE_ENV !== "production");
  if (original === undefined) delete process.env.JP_THEME_LAB_ENABLED;
  else process.env.JP_THEME_LAB_ENABLED = original;
});

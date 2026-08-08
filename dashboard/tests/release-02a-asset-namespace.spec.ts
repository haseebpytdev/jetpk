import { test, expect } from "@playwright/test";

const EXPECTED_PREFIX = (process.env.DASHBOARD_ASSET_PREFIX ?? "/dashboard-next").replace(/\/$/, "");

const BARE_NEXT_STATIC = /(?:src|href)=["']\/_next\/static\//;
const BARE_NEXT_ROOT = /(?:src|href)=["']\/_next\//;
const PREFIXED_NEXT_STATIC = new RegExp(
  `(?:src|href)=["']${EXPECTED_PREFIX.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}/_next/static/`,
);

function collectAssetUrls(html: string): string[] {
  const matches = html.matchAll(/(?:src|href)=["']([^"']+)["']/g);
  return [...matches].map((match) => match[1]);
}

function assertDashboardAssetNamespace(html: string, route: string): void {
  expect(html, `${route} must not emit bare /_next/static assets`).not.toMatch(BARE_NEXT_STATIC);

  const assets = collectAssetUrls(html);
  const nextAssets = assets.filter((url) => url.includes("/_next/"));
  expect(nextAssets.length, `${route} should reference Next build assets`).toBeGreaterThan(0);

  const prefixedAssets = nextAssets.filter((url) => url.startsWith(`${EXPECTED_PREFIX}/_next/`));
  expect(
    prefixedAssets.length,
    `${route} Next assets must use ${EXPECTED_PREFIX}/_next/* namespace`,
  ).toBe(nextAssets.length);

  expect(html, `${route} must include prefixed static assets`).toMatch(PREFIXED_NEXT_STATIC);

  const laravelPaths = assets.filter(
    (url) =>
      url.startsWith("/api/") ||
      url.startsWith("/admin/") ||
      url.startsWith("/staff/") ||
      url.startsWith("/auth/"),
  );
  for (const laravelPath of laravelPaths) {
    expect(laravelPath.startsWith(EXPECTED_PREFIX), `${route} Laravel URL must stay root-relative`).toBe(
      false,
    );
  }
}

test.describe("Release-02A dashboard asset namespace", () => {
  test("admin dashboard route and asset namespace", async ({ page, request }) => {
    const response = await request.get("/admin/dashboard", { maxRedirects: 0 });
    expect(response.status(), "admin dashboard must not redirect").toBe(200);

    await page.goto("/admin/dashboard", { waitUntil: "networkidle" });
    expect(page.url()).toMatch(/\/admin\/dashboard\/?$/);

    const html = await page.content();
    assertDashboardAssetNamespace(html, "/admin/dashboard");

    await expect(page.getByRole("heading", { name: "Dashboard", level: 1 })).toBeVisible({
      timeout: 60_000,
    });
  });

  test("staff dashboard route and asset namespace", async ({ page, request }) => {
    const response = await request.get("/staff/dashboard", { maxRedirects: 0 });
    expect(response.status(), "staff dashboard must not redirect").toBe(200);

    await page.goto("/staff/dashboard", { waitUntil: "networkidle" });
    expect(page.url()).toMatch(/\/staff\/dashboard\/?$/);

    const html = await page.content();
    assertDashboardAssetNamespace(html, "/staff/dashboard");

    await expect(page.getByRole("heading", { name: "Dashboard", level: 1 })).toBeVisible({
      timeout: 60_000,
    });
  });

  test("prefixed dashboard static assets return 200", async ({ page }) => {
    await page.goto("/admin/dashboard", { waitUntil: "domcontentloaded" });
    const html = await page.content();
    const assets = collectAssetUrls(html).filter((url) =>
      url.startsWith(`${EXPECTED_PREFIX}/_next/static/`),
    );
    expect(assets.length).toBeGreaterThan(0);

    const targets = {
      webpack: assets.find((url) => url.includes("webpack")) ?? assets[0],
      css: assets.find((url) => url.endsWith(".css")) ?? assets[0],
      page: assets.find((url) => url.includes("app/") || url.includes("pages/")) ?? assets[0],
    };

    for (const [label, assetUrl] of Object.entries(targets)) {
      const response = await page.request.get(assetUrl);
      expect(response.status(), `${label} asset ${assetUrl}`).toBe(200);
    }
  });
});

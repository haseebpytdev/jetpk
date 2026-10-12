import { expect, test, type Page } from "@playwright/test";
import path from "node:path";

const VIEWPORTS = [
  { name: "desktop", width: 1440, height: 900, tableTestId: "bookings-table" },
  { name: "tablet", width: 1024, height: 768, tableTestId: "bookings-mobile-cards" },
  { name: "mobile", width: 390, height: 844, tableTestId: "bookings-mobile-cards" },
] as const;

function attachHydrationMonitors(page: Page) {
  const hydrationWarnings: string[] = [];
  const pageErrors: string[] = [];

  page.on("console", (msg) => {
    const text = msg.text();
    if (msg.type() === "error" && (/hydration/i.test(text) || /Minified React error #418/.test(text))) {
      hydrationWarnings.push(text);
    }
  });

  page.on("pageerror", (error) => {
    pageErrors.push(error.message);
    if (/Minified React error #418|hydration/i.test(error.message)) {
      hydrationWarnings.push(error.message);
    }
  });

  return { hydrationWarnings, pageErrors };
}

for (const viewport of VIEWPORTS) {
  test(`DEF-F11-0008 admin bookings hydration @ ${viewport.name}`, async ({ browser }) => {
    const context = await browser.newContext({
      viewport: { width: viewport.width, height: viewport.height },
    });
    const page = await context.newPage();
    const monitors = attachHydrationMonitors(page);

    await page.goto(
      `/admin/dashboard/bookings?dataSourcePreview=fixture&defF110008=${viewport.name}`,
      { waitUntil: "domcontentloaded", timeout: 60_000 },
    );
    await expect(page.getByTestId("dashboard-shell")).toBeVisible({ timeout: 30_000 });
    await expect(page.getByTestId("bookings-filters")).toBeVisible({ timeout: 30_000 });
    await expect(page.getByTestId(viewport.tableTestId)).toBeVisible({ timeout: 30_000 });
    await page.waitForLoadState("networkidle", { timeout: 12_000 }).catch(() => {});
    await page.waitForTimeout(400);

    expect(monitors.hydrationWarnings, monitors.hydrationWarnings.join("\n")).toEqual([]);
    expect(
      monitors.pageErrors.filter((e) => !/ResizeObserver/i.test(e)),
      monitors.pageErrors.join("\n"),
    ).toEqual([]);

    await context.close();
  });
}

test("DEF-F11-0008 admin bookings with active search query", async ({ browser }) => {
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  const monitors = attachHydrationMonitors(page);

  await page.goto("/admin/dashboard/bookings?q=Emirates&dataSourcePreview=fixture&defF110008=search", {
    waitUntil: "domcontentloaded",
    timeout: 60_000,
  });
  await expect(page.getByTestId("bookings-table")).toBeVisible({ timeout: 30_000 });
  await page.waitForLoadState("networkidle", { timeout: 12_000 }).catch(() => {});
  await page.waitForTimeout(400);

  expect(monitors.hydrationWarnings).toEqual([]);
  expect(monitors.pageErrors.filter((e) => !/ResizeObserver/i.test(e))).toEqual([]);

  await context.close();
});

const liveStorage = path.resolve(__dirname, "../../tmp/jp-final-11-platform-admin-storage-state.json");

test("DEF-F11-0008 live production canary (optional)", async ({ browser }) => {
  test.skip(process.env.JP_DEF_F11_0008_LIVE !== "1", "Set JP_DEF_F11_0008_LIVE=1 to run live canary");

  const context = await browser.newContext({
    storageState: liveStorage,
    baseURL: "https://jetpakistan.pk",
    viewport: { width: 1440, height: 900 },
  });
  const page = await context.newPage();
  const monitors = attachHydrationMonitors(page);

  for (let i = 0; i < 3; i += 1) {
    await page.goto("/admin/dashboard/bookings", { waitUntil: "domcontentloaded", timeout: 90_000 });
    await expect(page.getByTestId("bookings-filters")).toBeVisible({ timeout: 30_000 });
    await expect(page.getByTestId("bookings-table")).toBeVisible({ timeout: 30_000 });
    await page.waitForLoadState("networkidle", { timeout: 12_000 }).catch(() => {});
    await page.waitForTimeout(400);
    expect(monitors.hydrationWarnings, `run ${i}`).toEqual([]);
  }

  await context.close();
});

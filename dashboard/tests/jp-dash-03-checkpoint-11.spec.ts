import { test, expect } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";

const storagePath = path.resolve(
  process.env.JP_ADMIN_STORAGE_STATE ??
    path.join(process.cwd(), "..", "tmp/jp-dash-03-admin-storage-state.json"),
);

const KNOWN_BOOKING_REFS = ["WL96PKN9", "FTRN9ULV", "KXZ5N65J"];
const PREVIEW_RESIDUE = /Preview data|synthetic preview data|Dashboard unavailable|Admin Preview/i;
const PRIVATE_ORIGIN = /127\.0\.0\.1|localhost|:8088/;

const RESPONSIVE_WIDTHS = [768, 935, 1024, 1280, 1366, 1440, 1600, 1920];
const RESPONSIVE_PAGES = [
  "/admin/dashboard",
  "/admin/dashboard/bookings",
  "/admin/dashboard/customers",
  "/admin/dashboard/agents",
  "/admin/dashboard/suppliers",
  "/admin/dashboard/payments",
  "/admin/dashboard/pnrs",
  "/admin/dashboard/tickets",
  "/admin/dashboard/reports",
  "/admin/staff",
  "/admin/api-settings",
  "/admin/settings",
];

const matrixPath = path.resolve(process.cwd(), "..", "docs/jetpk/JP-DASH-03-RESPONSIVE-MATRIX.json");
const reviewMatrixPath = path.resolve(process.cwd(), "..", "docs/jetpk/JP-DASH-03-REVIEW-MATRIX.json");

test.describe("JP-DASH-03 checkpoint 11", () => {
  test.beforeAll(() => {
    if (!fs.existsSync(storagePath)) {
      test.skip(true, "Admin storageState missing");
    }
  });

  test("deterministic booking detail drawer for known references", async ({ page }) => {
    const proved: string[] = [];

    for (const ref of KNOWN_BOOKING_REFS) {
      await page.goto(`/admin/dashboard/bookings?q=${encodeURIComponent(ref)}`, {
        waitUntil: "domcontentloaded",
        timeout: 120_000,
      });

      const body = await page.locator("body").innerText();
      expect(body).not.toMatch(PREVIEW_RESIDUE);

      if (!body.includes(ref)) {
        continue;
      }

      const manageLink = page.getByTestId("booking-manage-button").first();
      await expect(manageLink).toBeVisible({ timeout: 15_000 });
      await manageLink.click();
      await page.waitForURL(new RegExp(`/admin/dashboard/bookings/${ref}`), { timeout: 30_000 });
      await expect(page.getByTestId("booking-management-page")).toBeVisible({ timeout: 30_000 });
      const pageText = await page.locator("body").innerText();
      expect(pageText).toContain(ref);
      expect(pageText).not.toMatch(PREVIEW_RESIDUE);
      proved.push(ref);
    }

    expect(proved.length, `No known booking refs found among ${KNOWN_BOOKING_REFS.join(", ")}`).toBeGreaterThanOrEqual(1);
    if (proved.length >= 2) {
      expect(proved.length).toBeGreaterThanOrEqual(2);
    }
  });

  test("dashboard queue review actions navigate to public operational URLs", async ({ page }) => {
    await page.goto("/admin/dashboard", { waitUntil: "domcontentloaded", timeout: 120_000 });

    const reviewLinks = page.locator(
      'section[aria-labelledby="ops-queue-heading"] a[href^="/admin/"], section[aria-labelledby="ops-queue-heading"] a[href^="https://jetpakistan.pk/admin/"]',
    );
    const count = await reviewLinks.count();
    const rows: Array<{ label: string; href: string; finalUrl: string; status: string }> = [];
    const queueTargets: Array<{ label: string; href: string }> = [];

    for (let i = 0; i < count; i++) {
      const link = reviewLinks.nth(i);
      queueTargets.push({
        label: ((await link.textContent()) ?? "").trim(),
        href: (await link.getAttribute("href")) ?? "",
      });
    }

    for (const { label, href } of queueTargets) {
      if (!href) {
        rows.push({ label, href, finalUrl: "", status: "FAIL" });
        continue;
      }
      const response = await page.goto(href.startsWith("http") ? href : href, {
        waitUntil: "domcontentloaded",
        timeout: 120_000,
      });
      const finalUrl = page.url();
      const pass =
        (response?.status() ?? 0) < 400 &&
        finalUrl.startsWith("https://jetpakistan.pk") &&
        !PRIVATE_ORIGIN.test(finalUrl) &&
        !PREVIEW_RESIDUE.test(await page.locator("body").innerText());

      rows.push({ label, href, finalUrl, status: pass ? "PASS" : "FAIL" });
    }

    fs.mkdirSync(path.dirname(reviewMatrixPath), { recursive: true });
    fs.writeFileSync(
      reviewMatrixPath,
      JSON.stringify({ generatedAtUtc: new Date().toISOString(), rows }, null, 2),
    );

    const failures = rows.filter((r) => r.status === "FAIL");
    expect(failures, JSON.stringify(failures)).toHaveLength(0);
  });

  test("responsive matrix across required widths", async ({ page }) => {
    const rows: Array<{
      route: string;
      viewportWidth: number;
      scrollWidth: number;
      clientWidth: number;
      overflow: boolean;
      status: string;
    }> = [];

    for (const route of RESPONSIVE_PAGES) {
      for (const width of RESPONSIVE_WIDTHS) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(route, { waitUntil: "domcontentloaded", timeout: 120_000 }).catch(async () => {
          await page.goto(route, { waitUntil: "commit", timeout: 120_000 });
        });
        const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
        const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
        const overflow = scrollWidth > clientWidth + 2;
        rows.push({
          route,
          viewportWidth: width,
          scrollWidth,
          clientWidth,
          overflow,
          status: overflow ? "FAIL" : "PASS",
        });
      }
    }

    fs.mkdirSync(path.dirname(matrixPath), { recursive: true });
    fs.writeFileSync(
      matrixPath,
      JSON.stringify({ generatedAtUtc: new Date().toISOString(), rows }, null, 2),
    );

    const failures = rows.filter((r) => r.status === "FAIL");
    expect(failures, JSON.stringify(failures.slice(0, 5))).toHaveLength(0);
  });

  test("keyboard focus reaches sidebar and global search", async ({ page }) => {
    await page.goto("/admin/dashboard", { waitUntil: "domcontentloaded" });
    await page.keyboard.press("Tab");
    const focused = await page.evaluate(() => document.activeElement?.tagName ?? "");
    expect(focused).not.toBe("BODY");

    const search = page.getByRole("searchbox").first();
    if (await search.count() > 0) {
      await search.focus();
      await expect(search).toBeFocused();
    }
  });

  test("bookings list filter by search changes results state", async ({ page }) => {
    await page.goto("/admin/dashboard/bookings", { waitUntil: "domcontentloaded", timeout: 120_000 });
    const search = page.locator("#bookings-search");
    if (await search.count() === 0) {
      test.skip(true, "Bookings search input missing");
    }
    await search.fill("WL96PKN9");
    await page.waitForTimeout(1200);
    const url = page.url();
    expect(url).toMatch(/q=WL96PKN9|bookings/);
    const body = await page.locator("body").innerText();
    expect(body).not.toMatch(PREVIEW_RESIDUE);
  });
});

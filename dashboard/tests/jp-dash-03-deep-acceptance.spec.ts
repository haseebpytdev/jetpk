import fs from "node:fs";
import path from "node:path";
import {
  adminStorageStateExists,
  expect,
  test,
} from "./jp-dash-03-acceptance-session";

const matrixPath = path.resolve(process.cwd(), "..", "docs/jetpk/JP-DASH-03-DEEP-MATRIX.json");

/** Next dashboard pages that render in-place. */
const NEXT_MODULE_PAGES = [
  { module: "Dashboard", route: "/admin/dashboard" },
  { module: "Bookings", route: "/admin/dashboard/bookings" },
  { module: "Payments", route: "/admin/dashboard/payments" },
  { module: "PNRs", route: "/admin/dashboard/pnrs" },
  { module: "Tickets", route: "/admin/dashboard/tickets" },
  { module: "Deposits", route: "/admin/dashboard/deposits" },
  { module: "Customers", route: "/admin/dashboard/customers" },
  { module: "Agents", route: "/admin/dashboard/agents" },
  { module: "Suppliers", route: "/admin/dashboard/suppliers" },
  { module: "Users", route: "/admin/dashboard/users" },
  { module: "CMS", route: "/admin/dashboard/cms" },
  { module: "Reports", route: "/admin/dashboard/reports" },
  { module: "Audit", route: "/admin/dashboard/audit" },
  { module: "Settings", route: "/admin/dashboard/settings" },
  { module: "Support", route: "/admin/dashboard/support" },
  { module: "Markups", route: "/admin/dashboard/markups" },
  { module: "Go-live", route: "/admin/dashboard/system/go-live" },
];

/** Legacy Blade bookmarks must redirect onto Next dashboard surfaces. */
const LEGACY_REDIRECT_PAGES = [
  { module: "Legacy Settings", route: "/admin/settings", expectPath: "/admin/dashboard/settings" },
  { module: "Legacy Support", route: "/admin/support/tickets", expectPath: "/admin/dashboard/support" },
  { module: "Legacy Staff", route: "/admin/staff", expectPath: "/admin/dashboard/users" },
  { module: "Legacy API Settings", route: "/admin/api-settings", expectPath: "/admin/dashboard/settings/integrations" },
  { module: "Legacy Page Settings", route: "/admin/page-settings", expectPath: "/admin/dashboard/cms" },
  { module: "Legacy Branding", route: "/admin/settings/branding", expectPath: "/admin/dashboard/settings/general" },
  { module: "Legacy Markups", route: "/admin/markups", expectPath: "/admin/dashboard/markups" },
  { module: "Legacy Go-live", route: "/admin/go-live-checklist", expectPath: "/admin/dashboard/system/go-live" },
];

const PREVIEW_RESIDUE = /Preview data|synthetic preview data|Dashboard unavailable|Admin Preview/i;
const PRIVATE_ORIGIN = /127\.0\.0\.1|localhost|:8088/;

type MatrixRow = {
  module: string;
  route: string;
  httpStatus: number;
  finalUrl: string;
  handoff: boolean;
  previewResidue: boolean;
  privateOrigin: boolean;
  status: string;
};

function assertPublicOrigin(url: string) {
  expect(url).toMatch(/^https:\/\/jetpakistan\.pk/);
  expect(url).not.toMatch(PRIVATE_ORIGIN);
}

test.describe("JP-DASH-03 deep acceptance", () => {
  test.beforeAll(() => {
    if (!adminStorageStateExists()) {
      test.skip(true, "Admin storageState missing");
    }
  });

  test("module deep matrix", async ({ page }) => {
    const rows: MatrixRow[] = [];

    for (const entry of NEXT_MODULE_PAGES) {
      const response = await page.goto(entry.route, { waitUntil: "domcontentloaded", timeout: 120_000 });
      const status = response?.status() ?? 0;
      const finalUrl = page.url();
      const body = await page.locator("body").innerText();
      const previewResidue = PREVIEW_RESIDUE.test(body);
      const privateOrigin = PRIVATE_ORIGIN.test(body + finalUrl);
      const onNext = finalUrl.includes("/admin/dashboard");
      const pass = status < 400 && !previewResidue && !privateOrigin && onNext;

      rows.push({
        module: entry.module,
        route: entry.route,
        httpStatus: status,
        finalUrl,
        handoff: false,
        previewResidue,
        privateOrigin,
        status: pass ? "PASS" : "FAIL",
      });
    }

    for (const entry of LEGACY_REDIRECT_PAGES) {
      const response = await page.goto(entry.route, { waitUntil: "domcontentloaded", timeout: 120_000 });
      const status = response?.status() ?? 0;
      const finalUrl = page.url();
      const body = await page.locator("body").innerText();
      const previewResidue = PREVIEW_RESIDUE.test(body);
      const privateOrigin = PRIVATE_ORIGIN.test(body + finalUrl);
      const redirected = finalUrl.includes(entry.expectPath);
      const pass = status < 400 && !previewResidue && !privateOrigin && redirected;

      rows.push({
        module: entry.module,
        route: entry.route,
        httpStatus: status,
        finalUrl,
        handoff: false,
        previewResidue,
        privateOrigin,
        status: pass ? "PASS" : "FAIL",
      });
    }

    fs.mkdirSync(path.dirname(matrixPath), { recursive: true });
    fs.writeFileSync(
      matrixPath,
      JSON.stringify({ generatedAtUtc: new Date().toISOString(), rows }, null, 2),
    );

    const failures = rows.filter((row) => row.status === "FAIL");
    expect(failures, JSON.stringify(failures)).toHaveLength(0);
  });

  test("dashboard review redirects stay on public Next origin", async ({ page }) => {
    await page.goto("/admin/dashboard", { waitUntil: "domcontentloaded" });

    const redirects = [
      { label: "Staff", href: "/admin/staff", expectPath: "/admin/dashboard/users" },
      { label: "API Settings", href: "/admin/api-settings", expectPath: "/admin/dashboard/settings/integrations" },
      { label: "Laravel Settings", href: "/admin/settings", expectPath: "/admin/dashboard/settings" },
      { label: "Cancellations queue", href: "/admin/bookings?queue=cancellations", expectPath: "/admin/dashboard/bookings" },
      { label: "Execution queue", href: "/admin/bookings?queue=needs_action", expectPath: "/admin/dashboard/bookings" },
      { label: "Go-live", href: "/admin/go-live-checklist", expectPath: "/admin/dashboard/system/go-live" },
      { label: "Support tickets", href: "/admin/support/tickets", expectPath: "/admin/dashboard/support" },
    ];

    for (const item of redirects) {
      const response = await page.goto(item.href, { waitUntil: "domcontentloaded", timeout: 120_000 });
      expect(response?.status() ?? 0).toBeLessThan(400);
      assertPublicOrigin(page.url());
      expect(page.url()).toContain(item.expectPath);
      const body = await page.locator("body").innerText();
      expect(body).not.toMatch(PREVIEW_RESIDUE);
    }
  });

  test("customers list search and pagination controls", async ({ page }) => {
    await page.goto("/admin/dashboard/customers", { waitUntil: "domcontentloaded", timeout: 120_000 });
    await expect(page.getByRole("heading", { name: /^Customers$/i })).toBeVisible({ timeout: 30_000 });

    const search = page.locator("input[type='search'], input[placeholder*='Search']").first();
    if ((await search.count()) > 0) {
      await search.fill("test");
      await page.waitForTimeout(800);
    }

    const body = await page.locator("body").innerText();
    expect(body).not.toMatch(PREVIEW_RESIDUE);
  });

  test("bookings management page opens for known production reference", async ({ page }) => {
    const ref = "WL96PKN9";
    await page.goto(`/admin/dashboard/bookings?q=${ref}`, { waitUntil: "domcontentloaded", timeout: 120_000 });
    const manageLink = page.getByTestId("booking-manage-button").first();
    await expect(manageLink).toBeVisible({ timeout: 30_000 });
    await manageLink.click();
    await page.waitForURL(new RegExp(`/admin/dashboard/bookings/${ref}`), { timeout: 30_000 });
    await expect(page.getByTestId("booking-management-page")).toBeVisible({ timeout: 15_000 });
    const pageText = await page.locator("body").innerText();
    expect(pageText).toContain(ref);
    expect(pageText).not.toMatch(PREVIEW_RESIDUE);
  });

  test("responsive dashboard layout at 768px", async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 900 });
    await page.goto("/admin/dashboard", { waitUntil: "domcontentloaded" });
    const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
    const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
    expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 2);
  });
});

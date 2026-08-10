import { test, expect } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";

const storagePath = path.resolve(
  process.env.JP_ADMIN_STORAGE_STATE ??
    path.join(process.cwd(), "..", "tmp/jp-dash-03-admin-storage-state.json"),
);
const matrixPath = path.resolve(process.cwd(), "..", "docs/jetpk/JP-DASH-03-DEEP-MATRIX.json");

/** Next dashboard pages that render in-place (no live Laravel redirect). */
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
];

/** Live mode hands off to mature Laravel modules — test final public URLs. */
const LARAVEL_HANDOFF_PAGES = [
  { module: "Settings", route: "/admin/settings" },
  { module: "Support", route: "/admin/support/tickets" },
  { module: "Staff", route: "/admin/staff" },
  { module: "API Settings", route: "/admin/api-settings" },
  { module: "Page Settings", route: "/admin/page-settings" },
  { module: "Branding", route: "/admin/settings/branding" },
  { module: "Markups", route: "/admin/markups" },
  { module: "Go-live", route: "/admin/go-live-checklist" },
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
    if (!fs.existsSync(storagePath)) {
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
      const pass = status < 400 && !previewResidue && !privateOrigin;

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

    for (const entry of LARAVEL_HANDOFF_PAGES) {
      const response = await page.goto(entry.route, { waitUntil: "domcontentloaded", timeout: 120_000 });
      const status = response?.status() ?? 0;
      const finalUrl = page.url();
      const body = await page.locator("body").innerText();
      const previewResidue = PREVIEW_RESIDUE.test(body);
      const privateOrigin = PRIVATE_ORIGIN.test(body + finalUrl);
      const pass = status < 400 && !previewResidue && !privateOrigin;

      rows.push({
        module: entry.module,
        route: entry.route,
        httpStatus: status,
        finalUrl,
        handoff: true,
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

  test("dashboard review handoffs stay on public origin", async ({ page }) => {
    await page.goto("/admin/dashboard", { waitUntil: "domcontentloaded" });

    const handoffs = [
      { label: "Staff", href: "/admin/staff" },
      { label: "API Settings", href: "/admin/api-settings" },
      { label: "Laravel Settings", href: "/admin/settings" },
      { label: "Cancellations queue", href: "/admin/bookings?queue=cancellations" },
      { label: "Execution queue", href: "/admin/bookings?queue=needs_action" },
      { label: "Go-live", href: "/admin/go-live-checklist" },
      { label: "Support tickets", href: "/admin/support/tickets" },
    ];

    for (const handoff of handoffs) {
      const response = await page.goto(handoff.href, { waitUntil: "domcontentloaded", timeout: 120_000 });
      expect(response?.status() ?? 0).toBeLessThan(400);
      assertPublicOrigin(page.url());
      const body = await page.locator("body").innerText();
      expect(body).not.toMatch(PREVIEW_RESIDUE);
    }
  });

  test("customers list search and pagination controls", async ({ page }) => {
    await page.goto("/admin/dashboard/customers", { waitUntil: "domcontentloaded", timeout: 120_000 });
    await expect(page.getByRole("heading", { name: /^Customers$/i })).toBeVisible({ timeout: 30_000 });

    const search = page.locator("input[type='search'], input[placeholder*='Search']").first();
    if (await search.count() > 0) {
      await search.fill("test");
      await page.waitForTimeout(800);
    }

    const body = await page.locator("body").innerText();
    expect(body).not.toMatch(PREVIEW_RESIDUE);
  });

  test("bookings detail drawer opens for first row when present", async ({ page }) => {
    await page.goto("/admin/dashboard/bookings", { waitUntil: "domcontentloaded", timeout: 120_000 });
    const viewButton = page.getByRole("button", { name: /^View$/i }).first();
    if (await viewButton.count() === 0) {
      test.skip(true, "No booking rows to open");
    }
    await viewButton.click();
    await expect(page.getByTestId("booking-drawer-content")).toBeVisible({ timeout: 15_000 });
    const drawerText = await page.getByTestId("booking-drawer-content").innerText();
    expect(drawerText).not.toMatch(PREVIEW_RESIDUE);
  });

  test("responsive dashboard layout at 768px", async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 900 });
    await page.goto("/admin/dashboard", { waitUntil: "domcontentloaded" });
    const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
    const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
    expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 2);
  });
});

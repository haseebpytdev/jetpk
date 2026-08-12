import { test, expect, type APIResponse } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";

const storagePath = path.resolve(
  process.env.JP_ADMIN_STORAGE_STATE ?? path.join(process.cwd(), "..", "tmp/jp-dash-03-admin-storage-state.json"),
);

async function readJson<T>(response: APIResponse): Promise<T> {
  const text = await response.text();
  return JSON.parse(text.replace(/^\uFEFF/, "")) as T;
}

test.describe("JP-DASH-03 production acceptance", () => {
  test.beforeAll(() => {
    if (!fs.existsSync(storagePath)) {
      test.skip(true, "Admin storageState missing — run jp-dash-03-admin-login-bootstrap.mjs");
    }
  });

  test("authenticated admin dashboard renders without preview residue", async ({ page }) => {
    const consoleErrors: string[] = [];
    page.on("console", (msg) => {
      if (msg.type() === "error") consoleErrors.push(msg.text());
    });

    const response = await page.goto("/admin/dashboard", { waitUntil: "domcontentloaded" });
    expect(response?.status() ?? 0).toBeLessThan(400);

    await expect(page.getByTestId("dashboard-portal-label")).toBeVisible();
    const body = await page.locator("body").innerText();
    expect(body).not.toMatch(/127\.0\.0\.1|localhost|:8088/i);
    expect(body).not.toMatch(/Preview data|synthetic records|Dashboard unavailable/i);
    expect(consoleErrors.length).toBe(0);
  });

  test("admin staff management stays on Next dashboard origin", async ({ page }) => {
    await page.goto("/admin/dashboard/users", { waitUntil: "domcontentloaded" });
    await expect(page).toHaveURL(/^https:\/\/jetpakistan\.pk\/admin\/dashboard\/users/);
    const nav = page.getByLabel("Dashboard navigation");
    await expect(nav.getByRole("link", { name: "Staff" }).or(nav.getByRole("link", { name: "Users" })).first()).toBeVisible();
    expect(page.url()).not.toMatch(/127\.0\.0\.1|localhost|:8088/);
  });

  test("legacy admin bookings bookmark redirects to Next list", async ({ page }) => {
    await page.goto("/admin/bookings", { waitUntil: "domcontentloaded" });
    await expect(page).toHaveURL(/\/admin\/dashboard\/bookings\/?/);
  });

  test("legacy admin customers bookmark redirects to Next list", async ({ page }) => {
    await page.goto("/admin/customers", { waitUntil: "domcontentloaded" });
    await expect(page).toHaveURL(/\/admin\/dashboard\/customers\/?/);
  });

  test("legacy admin agents bookmark redirects to Next list", async ({ page }) => {
    await page.goto("/admin/agents", { waitUntil: "domcontentloaded" });
    await expect(page).toHaveURL(/\/admin\/dashboard\/agents\/?/);
  });

  test("admin grouped navigation renders production IA sections", async ({ page }) => {
    await page.goto("/admin/dashboard", { waitUntil: "domcontentloaded", timeout: 120_000 });
    const nav = page.getByLabel("Dashboard navigation");
    await expect(nav.getByText("Booking operations", { exact: true }).first()).toBeVisible({ timeout: 60_000 });
    await expect(nav.getByText("Finance", { exact: true }).first()).toBeVisible();
    await expect(nav.locator('a[href*="/admin/dashboard/bookings"]').first()).toBeVisible();
    await expect(nav.locator('a[href*="/admin/dashboard/payments"]').first()).toBeVisible();
  });

  test("staff grouped navigation renders scoped production IA", async ({ browser }) => {
    const staffStoragePath = path.resolve(
      process.env.JP_STAFF_STORAGE_STATE ??
        path.join(process.cwd(), "..", "tmp/jp-dash-03-staff-storage-state.json"),
    );
    test.skip(!fs.existsSync(staffStoragePath), "Staff storageState missing — run jp-dash-03-automated-login.mjs staff");

    const context = await browser.newContext({ storageState: staffStoragePath });
    const page = await context.newPage();
    try {
      await page.goto("/staff/dashboard", { waitUntil: "domcontentloaded", timeout: 120_000 });
      const nav = page.getByLabel("Dashboard navigation");
      await expect(nav.getByText("Booking operations", { exact: true }).first()).toBeVisible({ timeout: 60_000 });
      await expect(nav.getByText("Finance", { exact: true }).first()).toBeVisible();
      await expect(nav.locator('a[href*="/staff/dashboard/bookings"]').first()).toBeVisible();
      await expect(nav.getByRole("link", { name: "Markups" })).toHaveCount(0);
    } finally {
      await context.close();
    }
  });

  test("dashboard branding logo renders from public config on production", async ({ page }) => {
    const configResponse = await page.request.get("/api/public/content/config");
    expect(configResponse.ok()).toBeTruthy();
    const config = await readJson<{ logo_url?: string | null; brand_name?: string }>(configResponse);
    const brandName = (config.brand_name ?? "JetPakistan").trim() || "JetPakistan";
    const logoUrl = config.logo_url?.trim() ?? "";

    await page.goto("/admin/dashboard", { waitUntil: "domcontentloaded", timeout: 120_000 });
    const nav = page.getByLabel("Dashboard navigation");

    if (logoUrl !== "") {
      const logo = nav.getByTestId("dashboard-brand-logo");
      await expect(logo).toBeVisible({ timeout: 60_000 });
      const src = await logo.getAttribute("src");
      expect(src).toBeTruthy();
      expect(src).toMatch(/^(\/|https?:\/\/)/);
    } else {
      await expect(nav.getByText(brandName, { exact: true }).first()).toBeVisible();
    }
  });

  test("dashboard body uses Plus Jakarta Sans computed font on production", async ({ page }) => {
    await page.goto("/admin/dashboard", { waitUntil: "domcontentloaded", timeout: 120_000 });
    await expect(page.getByLabel("Dashboard navigation")).toBeVisible({ timeout: 60_000 });
    const fontFamily = await page.evaluate(() => getComputedStyle(document.body).fontFamily);
    expect(fontFamily.toLowerCase()).toMatch(/plus jakarta|jakarta/);
  });

  test("public homepage uses Plus Jakarta Sans computed font on production", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    try {
      await page.goto("/", { waitUntil: "domcontentloaded", timeout: 120_000 });
      const fontFamily = await page.evaluate(() => getComputedStyle(document.body).fontFamily);
      expect(fontFamily.toLowerCase()).toMatch(/plus jakarta|jakarta/);
    } finally {
      await context.close();
    }
  });

  test("public homepage renders configured brand logo on production", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    try {
      const configResponse = await page.request.get("/api/public/content/config", { timeout: 60_000 });
      expect(configResponse.ok()).toBeTruthy();
      const config = await readJson<{ logo_url?: string | null; brand_name?: string }>(configResponse);
      const brandName = (config.brand_name ?? "JetPakistan").trim() || "JetPakistan";
      const logoUrl = config.logo_url?.trim() ?? "";

      await page.goto("/", { waitUntil: "domcontentloaded", timeout: 120_000 });
      await page.setViewportSize({ width: 1440, height: 900 });
      const homeBrand = page.getByRole("link", { name: "JetPakistan home" }).first();
      await expect(homeBrand).toBeVisible({ timeout: 60_000 });

      if (logoUrl !== "") {
        const logo = homeBrand.locator("img").first();
        await expect(logo).toBeVisible({ timeout: 60_000 });
        const src = await logo.getAttribute("src");
        expect(src).toBeTruthy();
        expect(src).toMatch(/branding|storage|logo/i);
      } else {
        await expect(homeBrand.getByText(brandName, { exact: true })).toBeVisible();
      }
    } finally {
      await context.close();
    }
  });

  test("public homepage has no private-origin hrefs", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    try {
      await page.goto("/", { waitUntil: "domcontentloaded", timeout: 120_000 });
      const hrefs = await page.locator("a[href]").evaluateAll((els) =>
        els.map((el) => el.getAttribute("href") ?? "").filter(Boolean),
      );
      const privateOrigins = hrefs.filter((href) =>
        /127\.0\.0\.1|localhost|:8088|:8000/i.test(href),
      );
      expect(privateOrigins, `private origins found: ${privateOrigins.join(", ")}`).toEqual([]);
    } finally {
      await context.close();
    }
  });

  test("legacy staff bookings bookmark redirects to Next list", async ({ browser }) => {
    const staffStoragePath = path.resolve(
      process.env.JP_STAFF_STORAGE_STATE ??
        path.join(process.cwd(), "..", "tmp/jp-dash-03-staff-storage-state.json"),
    );
    test.skip(!fs.existsSync(staffStoragePath), "Staff storageState missing — run jp-dash-03-automated-login.mjs staff");

    const context = await browser.newContext({ storageState: staffStoragePath });
    const page = await context.newPage();
    try {
      await page.goto("/staff/bookings", { waitUntil: "domcontentloaded", timeout: 120_000 });
      await expect(page).toHaveURL(/\/staff\/dashboard\/bookings\/?/, { timeout: 30_000 });
    } finally {
      await context.close();
    }
  });

  test("booking management lifecycle panels render on production reference", async ({ page }) => {
    const ref = "WL96PKN9";
    const apiResponse = await page.request.get(`/api/dashboard/bookings/${encodeURIComponent(ref)}`);
    test.skip(!apiResponse.ok(), `booking API unavailable for ${ref}`);

    await page.goto(`/admin/dashboard/bookings/${ref}`, { waitUntil: "domcontentloaded", timeout: 120_000 });
    const management = page.getByTestId("booking-management-page");
    await expect(management).toBeVisible({ timeout: 60_000 });
    const panels = management.getByTestId("booking-management-panels");
    await expect(panels).toBeVisible();

    await expect(panels.getByTestId("booking-status-timeline")).toBeVisible();
    await expect(panels.getByTestId("booking-internal-notes")).toBeVisible();
    await expect(panels.getByTestId("booking-communications")).toBeVisible();
    await expect(panels.getByTestId("booking-documents")).toBeVisible();

    const body = await page.locator("body").innerText();
    expect(body).toContain(ref);
    expect(body).not.toMatch(/Preview data|synthetic records|Dashboard unavailable/i);
  });

  test("live operations review queue does not expose fixture workspace", async ({ page }) => {
    await page.goto("/admin/dashboard/operations/review", { waitUntil: "domcontentloaded", timeout: 120_000 });
    await expect(page.getByTestId("operational-review-queue")).toBeVisible({ timeout: 60_000 });

    const body = await page.locator("body").innerText();
    expect(body).not.toMatch(/Preview data|synthetic records|fixture workspace/i);
    // Fixture workspace heading must not remain as the live surface.
    expect(body).not.toMatch(/Approve or reject cancellation and refund requests before execution/i);
    expect(await page.getByTestId("laravel-live-redirect").count()).toBe(0);
  });

  test("payments list surface renders without preview residue when ledger empty", async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto("/admin/dashboard/payments", { waitUntil: "domcontentloaded", timeout: 120_000 });
    await expect(page.getByTestId("dashboard-portal-label")).toBeVisible({ timeout: 60_000 });
    const body = await page.locator("body").innerText();
    expect(body).not.toMatch(/Preview data|synthetic records|Dashboard unavailable/i);
    expect(body).not.toMatch(/127\.0\.0\.1|localhost|:8088/i);
    await expect(page.getByRole("heading", { name: /payment/i }).first()).toBeVisible();
  });

  test("payments drawer shows operational review section", async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });

    const listResponse = await page.request.get("/api/dashboard/payments?page=1&pageSize=1");
    if (!listResponse.ok()) {
      test.skip(true, "payments API unavailable on production");
    }
    const listPayload = await readJson<{
      data?: { transactions?: Array<{ transactionId: string }> };
    }>(listResponse);
    const representative = listPayload.data?.transactions?.[0]?.transactionId;
    if (!representative) {
      test.skip(true, "NO_REPRESENTATIVE_PRODUCTION_PAYMENT_RECORD");
      return;
    }

    await page.goto(
      `/admin/dashboard/payments?selectedTransactionId=${encodeURIComponent(representative)}`,
      { waitUntil: "domcontentloaded" },
    );

    await expect(page.getByRole("dialog")).toBeVisible({ timeout: 60_000 });
    const content = page.getByTestId("payment-drawer-content");
    await expect(content.getByRole("heading", { name: "Operational review" })).toBeVisible();
    await expect(
      content.getByTestId(/payment-actions-preview|payment-review-actions|payment-actions-unavailable/),
    ).toBeVisible();
  });
});

import { test, expect } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";

const storagePath = path.resolve(
  process.env.JP_ADMIN_STORAGE_STATE ?? path.join(process.cwd(), "..", "tmp/jp-dash-03-admin-storage-state.json"),
);

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

  test("laravel staff handoff stays on public origin", async ({ page }) => {
    await page.goto("/admin/dashboard", { waitUntil: "domcontentloaded" });
    const staff = page.locator("aside a[href='/admin/staff']").first();
    await expect(staff).toBeVisible();
    await staff.click();
    await page.waitForLoadState("domcontentloaded");
    const url = page.url();
    expect(url).toMatch(/^https:\/\/jetpakistan\.pk\/admin\/staff/);
    expect(url).not.toMatch(/127\.0\.0\.1|localhost|:8088/);
  });
});

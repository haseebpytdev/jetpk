import { test, expect, chromium } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";

const agentStoragePath = path.resolve(
  process.env.JP_AGENT_STORAGE_STATE ?? path.join(process.cwd(), "..", "tmp/jp-dash-03-agent-storage-state.json"),
);
const customerStoragePath = path.resolve(
  process.env.JP_CUSTOMER_STORAGE_STATE ??
    path.join(process.cwd(), "..", "tmp/jp-dash-03-customer-storage-state.json"),
);

async function withPortalSession(storagePath: string, dashboardPath: string, assert: (page: import("@playwright/test").Page) => Promise<void>) {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ storageState: storagePath });
  const page = await context.newPage();

  try {
    const response = await page.goto(dashboardPath, { waitUntil: "domcontentloaded", timeout: 120_000 });
    expect(response?.status() ?? 0).toBeLessThan(400);
    await assert(page);
  } finally {
    await browser.close();
  }
}

test.describe("JP-DASH-03 portal acceptance", () => {
  test("agent dashboard renders authenticated shell", async () => {
    test.skip(!fs.existsSync(agentStoragePath), "Agent storageState missing — run acceptance:automated-login:all");

    await withPortalSession(agentStoragePath, "/agent/dashboard", async (page) => {
      await expect(page.getByTestId("agent-dashboard-shell")).toBeVisible({ timeout: 60_000 });
      const body = await page.locator("body").innerText();
      expect(body).not.toMatch(/127\.0\.0\.1|localhost|:8088/i);
      expect(body).not.toMatch(/sign in|log in/i);
    });
  });

  test("customer dashboard renders authenticated shell", async () => {
    test.skip(!fs.existsSync(customerStoragePath), "Customer storageState missing — run acceptance:automated-login:all");

    await withPortalSession(customerStoragePath, "/customer/dashboard", async (page) => {
      await expect(page.getByTestId("customer-dashboard-shell")).toBeVisible({ timeout: 60_000 });
      const body = await page.locator("body").innerText();
      expect(body).not.toMatch(/127\.0\.0\.1|localhost|:8088/i);
      expect(body).not.toMatch(/sign in|log in/i);
    });
  });
});

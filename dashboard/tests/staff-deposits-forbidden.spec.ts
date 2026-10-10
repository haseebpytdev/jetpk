import fs from "node:fs";
import path from "node:path";
import { test, expect } from "@playwright/test";
import { createReadOnlyErrorEnvelope } from "@/lib/read-only/error-envelope";
import { ReadOnlyServiceError } from "@/lib/read-only/read-only-service";
import { classifyDepositsModuleError } from "@/features/deposits/deposits-page-content";

const runLiveProbe = process.env.JP_FINAL_11_RUN_LIVE_PROBE === "1";
const staffStorageState = process.env.JP_FINAL_11_STAFF_STORAGE_STATE;
const liveBaseUrl = process.env.JP_FINAL_11_LIVE_BASE_URL ?? "https://jetpakistan.pk";

test.describe("DEF-F11-0007 staff deposits forbidden handling", () => {
  test("classifies dashboard deposits 403 as forbidden (not rethrow)", () => {
    const error = new ReadOnlyServiceError(
      createReadOnlyErrorEnvelope({ code: "forbidden", referenceIdSafe: "DEP-FORBIDDEN" }),
    );
    expect(classifyDepositsModuleError(error)).toBe("forbidden");
  });

  test("admin deposits workspace still renders in fixture mode", async ({ page }) => {
    await page.goto("/admin/dashboard/deposits?dataSourcePreview=fixture", { waitUntil: "load" });
    await expect(page.getByTestId("deposits-workspace")).toBeVisible({ timeout: 60_000 });
  });

  test("live staff session: deposits API is 403 and direct URL shows access denied without RSC", async ({ browser }) => {
    test.skip(
      !runLiveProbe || !staffStorageState || !fs.existsSync(staffStorageState),
      "set JP_FINAL_11_RUN_LIVE_PROBE=1 and JP_FINAL_11_STAFF_STORAGE_STATE for production closure",
    );

    const context = await browser.newContext({ storageState: staffStorageState });
    const api = await context.request.get(`${liveBaseUrl}/laravel/api/dashboard/deposits`, {
      headers: { Accept: "application/json" },
    });
    expect(api.status()).toBe(403);

    const page = await context.newPage();
    const rscErrors: string[] = [];
    page.on("pageerror", (error) => rscErrors.push(error.message));

    const response = await page.goto(`${liveBaseUrl}/staff/dashboard/deposits`, { waitUntil: "domcontentloaded" });
    expect(response?.status() ?? 0).toBeLessThan(500);

    await expect(page.getByTestId("dashboard-access-denied")).toBeVisible({ timeout: 60_000 });
    await expect(page.getByTestId("deposits-workspace")).toHaveCount(0);
    expect(rscErrors.filter((line) => /Server Components render/i.test(line))).toHaveLength(0);

    await context.close();
  });
});

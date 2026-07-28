import { test, expect } from "@playwright/test";
import { getSettingsModule } from "@/services/settings-service";
import { containsSensitiveKeys } from "@/lib/read-only/sensitive-fields";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/admin/dashboard/settings", { timeout: 120_000 })).ok()).toBeTruthy();
});

const baseQuery = {
  selectedSection: "overview" as const,
  validationState: "all" as const,
  state: "",
  preview: false,
  tab: "",
  previewError: false,
  previewLoading: false,
  previewEmpty: false,
};

test("fixture settings module loads", async () => {
  const result = await getSettingsModule(baseQuery);
  expect(result.overview).toBeDefined();
  expect(containsSensitiveKeys(result)).toBe(false);
});

test("settings source notice", async ({ page }) => {
  await page.goto("/admin/dashboard/settings?dataSourcePreview=fixture", { waitUntil: "load" });
  await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
});

test("settings general subsection", async ({ page }) => {
  await page.goto("/admin/dashboard/settings/general", { waitUntil: "load" });
  await expect(page.getByText(/General/i).first()).toBeVisible({ timeout: 60_000 });
});

test("settings security subsection", async ({ page }) => {
  await page.goto("/admin/dashboard/settings/security", { waitUntil: "load" });
  await expect(page.locator("main")).toContainText(/Security|MFA|password/i);
});

test("settings integrations subsection", async ({ page }) => {
  await page.goto("/admin/dashboard/settings/integrations", { waitUntil: "load" });
  await expect(page.locator("main")).toBeVisible({ timeout: 60_000 });
});

test("settings browser back forward", async ({ page }) => {
  await page.goto("/admin/dashboard/settings/general", { waitUntil: "load" });
  await page.goto("/admin/dashboard/settings/security", { waitUntil: "load" });
  await page.goBack();
  await expect(page).toHaveURL(/settings\/general/);
});

test("settings no secrets in fixture payload", async () => {
  const result = await getSettingsModule(baseQuery);
  const payload = JSON.stringify(result);
  expect(payload).not.toContain("api_key");
  expect(payload).not.toContain("APP_KEY");
});

test("settings live read-only notice", async ({ page }) => {
  await page.goto("/admin/dashboard/settings?dataSourcePreview=live", { waitUntil: "load" });
  await expect(page.getByTestId("live-readonly-notice")).toBeVisible();
});

test("settings subsection source state transitions", async ({ page }) => {
  await page.goto("/admin/dashboard/settings?dataSourcePreview=stale", { waitUntil: "load" });
  await expect(page.getByTestId("stale-data-notice")).toBeVisible();
});

test("settings no overflow at 390px", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/admin/dashboard/settings", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
  expect(overflow).toBeFalsy();
});

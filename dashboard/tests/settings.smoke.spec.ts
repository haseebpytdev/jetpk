import { test, expect } from "@playwright/test";
import {
  applyGeneralSettingsPreview,
  expectCmsReady,
  expectReportsReady,
  expectSettingsReady,
  expectUsersReady,
  resetGeneralSettingsPreview,
  resetIntegrationSettingsPreview,
  resetNotificationSettingsPreview,
  resetSecuritySettingsPreview,
  selectFilterOption,
} from "./helpers";

test.beforeAll(async ({ request }) => {
  await request.get("/admin/dashboard/settings", { timeout: 120_000 });
});

test("settings overview route renders", async ({ page }) => {
  await page.goto("/admin/dashboard/settings", { waitUntil: "load" });
  await expectSettingsReady(page);
  await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
});

test("settings section navigation renders", async ({ page }) => {
  await page.goto("/admin/dashboard/settings", { waitUntil: "load" });
  const nav = page.getByRole("navigation", { name: "Settings sections" });
  await expect(nav.getByRole("link", { name: "Overview", exact: true })).toBeVisible();
  await expect(nav.getByRole("link", { name: "General", exact: true })).toBeVisible();
  await expect(nav.getByRole("link", { name: "Security", exact: true })).toBeVisible();
  await expect(nav.getByRole("link", { name: "Notifications", exact: true })).toBeVisible();
  await expect(nav.getByRole("link", { name: "Integrations", exact: true })).toBeVisible();
});

test("overview metric grid renders", async ({ page }) => {
  await page.goto("/admin/dashboard/settings", { waitUntil: "load" });
  await expect(page.getByTestId("settings-metric-grid")).toBeVisible();
  await expect(page.getByTestId("settings-metric-grid").getByText("General")).toBeVisible();
});

test("category readiness list renders", async ({ page }) => {
  await page.goto("/admin/dashboard/settings", { waitUntil: "load" });
  await expect(page.getByText("Category readiness")).toBeVisible();
  await expect(page.getByRole("link", { name: "Open" }).first()).toBeVisible();
});

test("Laravel boundary note is visible", async ({ page }) => {
  await page.goto("/admin/dashboard/settings", { waitUntil: "load" });
  await expect(page.getByTestId("settings-laravel-boundary-note")).toBeVisible();
});

test("general settings route renders", async ({ page }) => {
  await page.goto("/admin/dashboard/settings/general", { waitUntil: "load" });
  await expect(page.getByTestId("general-settings-workspace")).toBeVisible();
});

test("security settings route renders", async ({ page }) => {
  await page.goto("/admin/dashboard/settings/security", { waitUntil: "load" });
  await expect(page.getByTestId("security-settings-workspace")).toBeVisible();
});

test("notification settings route renders", async ({ page }) => {
  await page.goto("/admin/dashboard/settings/notifications", { waitUntil: "load" });
  await expect(page.getByTestId("notification-settings-workspace")).toBeVisible();
});

test("integration settings route renders", async ({ page }) => {
  await page.goto("/admin/dashboard/settings/integrations", { waitUntil: "load" });
  await expect(page.getByTestId("integration-settings-workspace")).toBeVisible();
});

test("general settings shows JetPakistan baseline", async ({ page }) => {
  await page.goto("/admin/dashboard/settings/general", { waitUntil: "load" });
  await expect(page.getByTestId("general-settings-workspace")).toContainText("JetPakistan");
});

test("apply general settings preview works", async ({ page }) => {
  await page.goto("/admin/dashboard/settings/general", { waitUntil: "load" });
  await applyGeneralSettingsPreview(page, "Organization display name", "JetPakistan Preview QA");
  await expect(page.getByTestId("general-settings-workspace")).toContainText("JetPakistan Preview QA");
});

test("reset general settings preview works", async ({ page }) => {
  await page.goto("/admin/dashboard/settings/general", { waitUntil: "load" });
  await applyGeneralSettingsPreview(page, "Organization display name", "JetPakistan Preview QA");
  await resetGeneralSettingsPreview(page);
  await expect(page.getByTestId("general-settings-workspace")).toContainText("JetPakistan");
  await expect(page.getByText("Unsaved preview")).toHaveCount(0);
});

test("general settings validation summary renders", async ({ page }) => {
  await page.goto("/admin/dashboard/settings/general", { waitUntil: "load" });
  await expect(page.getByTestId("general-settings-workspace").getByText(/Validation/i).first()).toBeVisible();
});

test("security settings preview apply and reset", async ({ page }) => {
  await page.goto("/admin/dashboard/settings/security", { waitUntil: "load" });
  const form = page.getByTestId("security-settings-workspace").getByTestId("settings-local-preview-form");
  const control = form.getByLabel("Password minimum length");
  await control.fill("14");
  await form.getByRole("button", { name: "Apply to preview" }).click();
  await expect(page.getByText("Unsaved preview")).toBeVisible();
  await resetSecuritySettingsPreview(page);
  await expect(page.getByText("Unsaved preview")).toHaveCount(0);
});

test("security settings active preview values render", async ({ page }) => {
  await page.goto("/admin/dashboard/settings/security", { waitUntil: "load" });
  await expect(page.getByTestId("security-settings-workspace")).toContainText("MFA requirement policy");
});

test("notification categories list renders", async ({ page }) => {
  await page.goto("/admin/dashboard/settings/notifications", { waitUntil: "load" });
  const workspace = page.getByTestId("notification-settings-workspace");
  const categories = workspace.getByLabel("Notification categories");
  await expect(categories).toBeVisible();
  await expect(categories.getByText("Booking", { exact: true })).toBeVisible();
  await expect(categories.getByText("Audit", { exact: true })).toBeVisible();
});

test("notification settings preview reset works", async ({ page }) => {
  await page.goto("/admin/dashboard/settings/notifications", { waitUntil: "load" });
  const form = page.getByTestId("notification-settings-workspace").getByTestId("settings-local-preview-form");
  await form.getByLabel("Category enabled").uncheck();
  await form.getByRole("button", { name: "Apply to preview" }).click();
  await expect(page.getByText("Unsaved preview")).toBeVisible();
  await resetNotificationSettingsPreview(page);
  await expect(page.getByText("Unsaved preview")).toHaveCount(0);
});

test("integration records distinguish GDS and NDC", async ({ page }) => {
  await page.goto("/admin/dashboard/settings/integrations", { waitUntil: "load" });
  await expect(page.getByTestId("integration-record-sabreGds")).toContainText("Sabre GDS");
  await expect(page.getByTestId("integration-record-sabreNdc")).toContainText("Sabre NDC");
});

test("integration settings preview reset works", async ({ page }) => {
  await page.goto("/admin/dashboard/settings/integrations", { waitUntil: "load" });
  const form = page.getByTestId("integration-settings-workspace").getByTestId("settings-local-preview-form");
  await form.getByLabel("Configuration completeness (%)").fill("99");
  await form.getByRole("button", { name: "Apply to preview" }).click();
  await expect(page.getByText("Unsaved preview")).toBeVisible();
  await resetIntegrationSettingsPreview(page);
  await expect(page.getByText("Unsaved preview")).toHaveCount(0);
});

test("integration records show no credential fields", async ({ page }) => {
  await page.goto("/admin/dashboard/settings/integrations", { waitUntil: "load" });
  const records = page.getByTestId("integration-settings-workspace");
  const text = await records.textContent();
  expect(text).not.toMatch(/\bpassword\b|\bapikey\b|\bapi_key\b|\bpcc\b|\blniata\b/i);
  await expect(records.locator('input[type="password"]')).toHaveCount(0);
});

test("settings section nav marks current page", async ({ page }) => {
  await page.goto("/admin/dashboard/settings/security", { waitUntil: "load" });
  await expect(page.getByRole("link", { name: "Security", exact: true })).toHaveAttribute("aria-current", "page");
});

test("browser back and forward works", async ({ page }) => {
  await page.goto("/admin/dashboard/settings/integrations", { waitUntil: "load" });
  await expectSettingsReady(page);
  await page.goto("/admin/dashboard/settings/integrations?validationState=blocked", { waitUntil: "load" });
  await expect(page).toHaveURL(/validationState=blocked/);
  const workspace = page.getByTestId("integration-settings-workspace");
  await expect(workspace).toBeVisible();
  await expect(workspace.getByTestId("settings-validation-summary")).toContainText("Blocking");

  await page.getByRole("link", { name: "Security", exact: true }).click();
  await expect(page).toHaveURL(/\/settings\/security/);
  await expect(page.getByTestId("security-settings-workspace")).toBeVisible();

  await page.goBack();
  await expect(page).toHaveURL(/\/settings\/integrations/);
  await expect(page).toHaveURL(/validationState=blocked/);
  await expect(workspace).toBeVisible();
  await expect(workspace.getByTestId("settings-validation-summary")).toContainText("Blocking");
  await expectSettingsReady(page);

  await page.goForward();
  await expect(page).toHaveURL(/\/settings\/security/);
  await expect(page.getByTestId("security-settings-workspace")).toBeVisible();
  await expectSettingsReady(page);
});

test("overview open link navigates to general", async ({ page }) => {
  await page.goto("/admin/dashboard/settings", { waitUntil: "load" });
  await page.getByRole("link", { name: "Open" }).first().click();
  await expect(page).toHaveURL(/\/admin\/dashboard\/settings\/general/, { timeout: 30_000 });
  await expectSettingsReady(page);
});

test("timezone select supports fixture values", async ({ page }) => {
  await page.goto("/admin/dashboard/settings/general", { waitUntil: "load" });
  const timezone = page.locator("#settings-preview-timezone");
  await selectFilterOption(timezone, "UTC");
  await page.getByTestId("settings-local-preview-form").getByRole("button", { name: "Apply to preview" }).click();
  await expect(page.getByTestId("general-settings-workspace")).toContainText("UTC");
});

test("loading state works", async ({ page }) => {
  await page.goto("/admin/dashboard/settings?previewLoading=1", { waitUntil: "load" });
  await expect(page.getByTestId("settings-loading-state")).toBeVisible();
});

test("empty state works", async ({ page }) => {
  await page.goto("/admin/dashboard/settings?previewEmpty=1", { waitUntil: "load" });
  await expect(page.getByText(/No settings data/i)).toBeVisible();
});

test("error state works", async ({ page }) => {
  await page.goto("/admin/dashboard/settings?previewError=1", { waitUntil: "load" });
  await expect(page.getByText(/Unable to load settings/i)).toBeVisible();
});

test("invalid URL values fall back safely", async ({ page }) => {
  await page.goto("/admin/dashboard/settings/general?validationState=invalid", { waitUntil: "load" });
  await expectSettingsReady(page);
  await expect(page.getByTestId("general-settings-workspace")).toBeVisible();
});

test("360px overview has no overflow", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 740 });
  await page.goto("/admin/dashboard/settings", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  expect(overflow).toBe(false);
});

test("390px general settings has no overflow", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/admin/dashboard/settings/general", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  expect(overflow).toBe(false);
});

test("no brand switcher on settings", async ({ page }) => {
  await page.goto("/admin/dashboard/settings", { waitUntil: "load" });
  const body = await page.locator("body").textContent();
  expect(body).not.toMatch(/Parwaaz|YoursDomain|haseeb-master/i);
});

test("no mutation request occurs on general preview", async ({ page }) => {
  const requests: string[] = [];
  page.on("request", (req) => {
    if (["POST", "PUT", "PATCH", "DELETE"].includes(req.method())) {
      requests.push(`${req.method()} ${req.url()}`);
    }
  });
  await page.goto("/admin/dashboard/settings/general", { waitUntil: "load" });
  await applyGeneralSettingsPreview(page, "Public support label", "JetPakistan Ops");
  expect(requests).toEqual([]);
});

test("refresh restores general fixture values", async ({ page }) => {
  await page.goto("/admin/dashboard/settings/general", { waitUntil: "load" });
  await applyGeneralSettingsPreview(page, "Organization display name", "Temporary Preview Name");
  await page.reload({ waitUntil: "load" });
  await expect(page.getByTestId("general-settings-workspace")).toContainText("JetPakistan");
  await expect(page.getByText("Unsaved preview")).toHaveCount(0);
});

test("users route regression remains functional", async ({ page }) => {
  await page.goto("/admin/dashboard/users", { waitUntil: "load" });
  await expectUsersReady(page);
});

test("reports route regression remains functional", async ({ page }) => {
  await page.goto("/admin/dashboard/reports", { waitUntil: "load" });
  await expectReportsReady(page);
});

test("cms route regression remains functional", async ({ page }) => {
  await page.goto("/admin/dashboard/cms", { waitUntil: "load" });
  await expectCmsReady(page);
});

test("all settings subroutes render preview banner", async ({ page }) => {
  const routes = [
    "/admin/dashboard/settings",
    "/admin/dashboard/settings/general",
    "/admin/dashboard/settings/security",
    "/admin/dashboard/settings/notifications",
    "/admin/dashboard/settings/integrations",
  ];
  for (const route of routes) {
    await page.goto(route, { waitUntil: "load" });
    await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
  }
});

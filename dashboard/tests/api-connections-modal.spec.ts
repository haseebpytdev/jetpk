import { test, expect } from "@playwright/test";

test.beforeEach(async ({ page }) => {
  await page.goto("/admin/dashboard/api-connections", { waitUntil: "load" });
  await expect(page.getByTestId("api-connections-workspace")).toBeVisible();
});

test("add api connection opens modal without inline create panel", async ({ page }) => {
  await expect(page.getByTestId("api-connection-create-panel")).toHaveCount(0);
  await page.getByTestId("api-connection-add-card").click();
  const modal = page.getByTestId("api-connection-create-modal");
  await expect(modal).toBeVisible();
  await expect(modal).toHaveAttribute("role", "dialog");
  await expect(modal).toHaveAttribute("aria-modal", "true");
  await expect(page.getByTestId("api-provider-catalog-cards")).toBeVisible();
});

test("airblue configure step shows minimal masked fields and endpoint preview", async ({ page }) => {
  await page.getByTestId("api-connection-add-card").click();
  await page.getByTestId("api-provider-card-airblue").click();
  const modal = page.getByTestId("api-connection-create-modal");
  await expect(modal.getByTestId("api-connection-create-configure")).toBeVisible();
  await expect(modal.getByTestId("airblue-endpoint-preview")).toContainText("https://otatest4.zapways.com/v2.0/OTAAPI.asmx");
  await expect(modal.getByTestId("api-create-field-client_id")).toBeVisible();
  await expect(modal.getByTestId("api-create-field-client_key")).toHaveAttribute("type", "password");
  await expect(modal.getByTestId("api-create-field-agent_id")).toBeVisible();
  await expect(modal.getByTestId("api-create-field-agent_password")).toHaveAttribute("type", "password");
  await expect(modal.getByTestId("api-create-field-username")).toHaveCount(0);
  await expect(modal.getByTestId("api-create-field-password")).toHaveCount(0);
  await expect(modal.getByTestId("api-create-field-agency_id")).toHaveCount(0);
  await expect(modal.getByTestId("api-create-field-api_channel")).toHaveCount(0);
  await expect(modal.getByTestId("api-create-field-agent_type")).toHaveCount(0);
  await expect(modal.getByTestId("api-create-field-tls_cert_path")).toHaveCount(0);
});

test("airblue live selection updates endpoint preview and auto name", async ({ page }) => {
  await page.getByTestId("api-connection-add-card").click();
  await page.getByTestId("api-provider-card-airblue").click();
  const modal = page.getByTestId("api-connection-create-modal");
  await expect(modal.getByTestId("api-create-connection-name")).toHaveValue("AirBlue Zapways TEST v2");
  await modal.getByTestId("api-create-environment").selectOption("live");
  await expect(modal.getByTestId("airblue-endpoint-preview")).toContainText("https://ota4.zapways.com/v2.0/OTAAPI.asmx");
  await expect(modal.getByTestId("api-create-connection-name")).toHaveValue("AirBlue Zapways LIVE v2");
  await modal.getByTestId("api-create-environment").selectOption("sandbox");
  await expect(modal.getByTestId("airblue-endpoint-preview")).toContainText("https://otatest4.zapways.com/v2.0/OTAAPI.asmx");
});

test("modal back and close controls work", async ({ page }) => {
  await page.getByTestId("api-connection-add-card").click();
  await page.getByTestId("api-provider-card-airblue").click();
  await page.getByRole("button", { name: "Back" }).click();
  await expect(page.getByTestId("api-provider-catalog-cards")).toBeVisible();
  await page.getByRole("button", { name: "Close", exact: true }).click();
  await expect(page.getByTestId("api-connection-create-modal")).toHaveCount(0);
});

test("modal layout is usable on mobile viewport", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.getByTestId("api-connection-add-card").click();
  const modal = page.getByTestId("api-connection-create-modal");
  await expect(modal).toBeVisible();
  const box = await modal.boundingBox();
  expect(box?.width ?? 0).toBeGreaterThan(300);
});

import { test, expect } from "@playwright/test";

test.beforeAll(async ({ request }) => {
  await request.get("/admin/dashboard/integrations", { timeout: 120_000 });
});

test("integrations overview renders metrics and cards", async ({ page }) => {
  await page.goto("/admin/dashboard/integrations", { waitUntil: "load" });
  await expect(page.getByTestId("integrations-hub")).toBeVisible();
  await expect(page.getByRole("heading", { name: "Integrations" })).toBeVisible();
  await expect(page.getByText("Configure, test and monitor every external service connected to JetPakistan.")).toBeVisible();
  await expect(page.getByText("Active")).toBeVisible();
  await expect(page.getByTestId("integration-card-sabre")).toBeVisible();
  await expect(page.getByTestId("integration-card-abhipay")).toBeVisible();
});

test("category filtering shows flights and payments", async ({ page }) => {
  await page.goto("/admin/dashboard/integrations", { waitUntil: "load" });
  await page.getByRole("button", { name: "Flights", exact: true }).click();
  await expect(page.getByTestId("integration-card-sabre")).toBeVisible();
  await expect(page.getByTestId("integration-card-abhipay")).toHaveCount(0);

  await page.getByRole("button", { name: "Payments", exact: true }).click();
  await expect(page.getByTestId("integration-card-abhipay")).toBeVisible();
  await expect(page.getByTestId("integration-card-sabre")).toHaveCount(0);
});

test("provider detail opens for sabre and abhipay settings with masked secret", async ({ page }) => {
  await page.goto("/admin/dashboard/integrations", { waitUntil: "load" });
  await page.getByTestId("integration-card-sabre").getByRole("button", { name: "Settings" }).click();
  await expect(page.getByRole("dialog").getByRole("heading", { name: "Sabre" })).toBeVisible();
  await page.getByRole("button", { name: "Close" }).click();

  await page.getByTestId("integration-card-abhipay").getByRole("button", { name: "Settings" }).click();
  await expect(page.getByRole("dialog").getByRole("heading", { name: "AbhiPay" })).toBeVisible();
  await expect(page.getByText("•••••••••• configured")).toBeVisible();
  await expect(page.locator('input[type="password"]')).toHaveCount(0);
  await page.getByRole("button", { name: "Replace" }).click();
  await expect(page.locator('input[type="password"]')).toBeVisible();
});

test("test connection and health history", async ({ page }) => {
  await page.goto("/admin/dashboard/integrations?provider=abhipay", { waitUntil: "load" });
  await page.getByRole("button", { name: "Health" }).click();
  await expect(page.getByTestId("health-history")).toBeVisible();
  await page.getByRole("button", { name: "Test Connection" }).first().click();
  await expect(page.getByText(/Test Connection completed/i)).toBeVisible({ timeout: 10_000 });
});

test("add integration wizard and custom api activation guard", async ({ page }) => {
  await page.goto("/admin/dashboard/integrations", { waitUntil: "load" });
  await page.getByRole("button", { name: "+ Add Integration" }).click();
  await expect(page.getByTestId("add-integration-wizard")).toBeVisible();
  await expect(page.getByTestId("add-integration-category")).toBeVisible();
  await page.getByRole("button", { name: "Next" }).click();
  await expect(page.getByTestId("add-integration-provider")).toBeVisible();
  await page.getByRole("button", { name: "Custom API" }).click();
  await page.getByRole("button", { name: "Next" }).click();
  await page.getByRole("button", { name: "Next" }).click();
  await expect(page.getByTestId("add-integration-auth")).toBeVisible();
  await page.getByRole("button", { name: "Next" }).click();
  await page.getByRole("button", { name: "Next" }).click();
  await expect(page.getByTestId("add-integration-health-test")).toBeVisible();
  await page.getByRole("button", { name: "Next" }).click();
  await expect(page.getByTestId("custom-api-adapter-block")).toBeVisible();
  await expect(page.getByText(/runtime adapter is still required/i)).toBeVisible();
});

test("test payment confirm and live diagnostic blocked", async ({ page }) => {
  await page.goto("/admin/dashboard/integrations?provider=abhipay", { waitUntil: "load" });
  await page.getByRole("button", { name: "Health" }).click();
  await page.getByRole("button", { name: "Test Payment" }).click();
  await expect(page.getByTestId("abhipay-test-payment-confirm")).toBeVisible();
  await expect(page.getByRole("button", { name: "Create PKR 1.00 diagnostic" })).toBeVisible();
  await page.getByRole("button", { name: "Cancel" }).click();

  await page.goto("/admin/dashboard/integrations?provider=abhipay&scenario=live-abhipay", { waitUntil: "load" });
  await page.getByRole("button", { name: "Health" }).click();
  await expect(page.getByTestId("live-test-payment-blocked")).toBeVisible();
  await expect(page.getByRole("button", { name: "Test Payment" })).toBeDisabled();
});

test("integrations overview is usable on mobile viewport", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/admin/dashboard/integrations", { waitUntil: "load" });
  await expect(page.getByTestId("integrations-hub")).toBeVisible();
  await expect(page.getByTestId("integration-card-abhipay")).toBeVisible();
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
  expect(overflow).toBeFalsy();
});

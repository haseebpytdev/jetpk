import { expect, test } from "@playwright/test";
import {
  mockAgentPortalApis,
  mockCustomerPortalApis,
  setPortalSessionFixture,
} from "./helpers/portal-session-fixtures";

const MATRIX_VIEWPORTS = [
  { name: "360x800", width: 360, height: 800 },
  { name: "768x1024", width: 768, height: 1024 },
  { name: "1440x900", width: 1440, height: 900 },
] as const;

for (const viewport of MATRIX_VIEWPORTS) {
  test(`homepage production smoke renders at ${viewport.name}`, async ({ page }) => {
    await page.setViewportSize({ width: viewport.width, height: viewport.height });
    await page.goto("/", { waitUntil: "load" });
    await expect(page.getByTestId("search-module")).toBeVisible();
    await expect(page.getByRole("heading", { name: "Destinations on the Rise" })).toBeVisible();
    await expect(page.getByRole("heading", { name: "Why JetPakistan" })).toBeVisible();
  });
}

test("production preview homepage has no client-side exception overlay", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });
  await expect(page.getByText("Application error: a client-side exception has occurred")).toHaveCount(0);
  await expect(page.getByTestId("search-module")).toBeVisible();
});

test("airport picker keyboard selection commits IATA on production smoke path", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });
  const fromField = page.getByRole("combobox", { name: "From" });
  await fromField.click();
  await fromField.fill("Lahore");
  await expect(page.getByRole("option", { name: /LHE/i })).toBeVisible();
  await page.keyboard.press("ArrowDown");
  await page.keyboard.press("Enter");
  await expect(fromField).toHaveValue(/LHE/i);
});

for (const viewport of MATRIX_VIEWPORTS) {
  test(`customer portal dark theme renders at ${viewport.name}`, async ({ page }) => {
    await page.setViewportSize({ width: viewport.width, height: viewport.height });
    await page.addInitScript(() => localStorage.setItem("jp-theme-preference", "dark"));
    await page.emulateMedia({ colorScheme: "dark" });
    await setPortalSessionFixture(page, "customer");
    await mockCustomerPortalApis(page);
    await page.goto("/customer/dashboard", { waitUntil: "load" });
    await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
    await expect(page.getByTestId("customer-dashboard-shell")).toBeVisible();
  });

  test(`agent portal dark theme renders at ${viewport.name}`, async ({ page }) => {
    await page.setViewportSize({ width: viewport.width, height: viewport.height });
    await page.addInitScript(() => localStorage.setItem("jp-theme-preference", "dark"));
    await page.emulateMedia({ colorScheme: "dark" });
    await setPortalSessionFixture(page, "agent");
    await mockAgentPortalApis(page);
    await page.goto("/agent/dashboard", { waitUntil: "load" });
    await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
    await expect(page.getByTestId("agent-dashboard-shell")).toBeVisible();
  });
}

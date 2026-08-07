import { expect, test } from "@playwright/test";

const MATRIX_VIEWPORTS = [
  { name: "360x800", width: 360, height: 800 },
  { name: "768x1024", width: 768, height: 1024 },
  { name: "1280x800", width: 1280, height: 800 },
] as const;

for (const viewport of MATRIX_VIEWPORTS) {
  test(`admin overview renders at ${viewport.name}`, async ({ page }) => {
    await page.setViewportSize({ width: viewport.width, height: viewport.height });
    await page.goto("/admin/dashboard", { waitUntil: "load" });
    await expect(page.getByRole("heading", { name: "Dashboard", level: 1 })).toBeVisible();
    await expect(page.getByTestId("dashboard-portal-label")).toContainText("Admin console");
  });

  test(`staff overview renders at ${viewport.name}`, async ({ page }) => {
    await page.setViewportSize({ width: viewport.width, height: viewport.height });
    await page.goto("/staff/dashboard", { waitUntil: "load" });
    await expect(page.getByRole("heading", { name: "Dashboard", level: 1 })).toBeVisible();
    await expect(page.getByTestId("dashboard-portal-label")).toContainText("Staff console");
  });
}

test("admin overview dark theme renders readable shell", async ({ page }) => {
  await page.addInitScript(() => localStorage.setItem("jp-theme-preference", "dark"));
  await page.emulateMedia({ colorScheme: "dark" });
  await page.goto("/admin/dashboard?dataSourcePreview=fixture", { waitUntil: "load" });
  await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
  await expect(page.getByRole("heading", { name: "Dashboard", level: 1 })).toBeVisible();
});

test("staff overview dark theme renders readable shell", async ({ page }) => {
  await page.addInitScript(() => localStorage.setItem("jp-theme-preference", "dark"));
  await page.emulateMedia({ colorScheme: "dark" });
  await page.goto("/staff/dashboard?dataSourcePreview=fixture", { waitUntil: "load" });
  await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
  await expect(page.getByRole("heading", { name: "Dashboard", level: 1 })).toBeVisible();
});

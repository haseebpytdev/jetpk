import { test, expect } from "@playwright/test";

test.beforeAll(async ({ request }) => {
  const response = await request.get("/testdash", { timeout: 120_000 });
  expect(response.ok()).toBeTruthy();
});

const viewports = [
  { width: 360, height: 740 },
  { width: 390, height: 844 },
  { width: 430, height: 932 },
  { width: 768, height: 1024 },
  { width: 1024, height: 768 },
  { width: 1280, height: 720 },
  { width: 1440, height: 900 },
  { width: 1920, height: 1080 },
];

for (const viewport of viewports) {
  test(`overview renders at ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await page.goto("/testdash", { waitUntil: "load" });
    await expect(page.getByRole("heading", { name: "Dashboard", level: 1 })).toBeVisible({
      timeout: 60_000,
    });
    await expect(page.getByText(/Preview mode/i)).toBeVisible();
    await expect(page.getByText("Pending Deposits")).toBeVisible();
  });
}

test("planned module stub", async ({ page }) => {
  await page.goto("/testdash/planned/bookings", { waitUntil: "load" });
  await expect(page.getByText(/Planned module/i)).toBeVisible({ timeout: 30_000 });
  await expect(page.getByText("admin.bookings")).toBeVisible();
});

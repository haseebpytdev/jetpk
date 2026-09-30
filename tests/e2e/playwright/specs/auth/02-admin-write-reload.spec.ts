import { test, expect } from "@playwright/test";
import path from "node:path";

test.use({ storageState: path.join(process.cwd(), "tmp", "e2e-auth", "admin.json") });

/**
 * Reversible profile field write via Next UI (city preferred, phone fallback).
 * Avoids email changes (email_verified_at reset risk).
 */
test("admin profile safe write → reload → restore", async ({ page }) => {
  await page.goto("/admin/dashboard/profile", { waitUntil: "domcontentloaded" });
  await expect(page.getByTestId("my-profile-page")).toBeVisible({ timeout: 45_000 });

  const city = page.getByTestId("profile-city");
  const phone = page.getByTestId("profile-phone");
  await expect(city.or(phone).first()).toBeVisible({ timeout: 30_000 });

  const cityEnabled = await city.isEnabled().catch(() => false);
  const fieldTestId = cityEnabled ? "profile-city" : "profile-phone";
  const field = page.getByTestId(fieldTestId);
  await expect(field).toBeEnabled({ timeout: 30_000 });

  const original = await field.inputValue();
  const marker = `E2E-${Date.now().toString().slice(-6)}`;

  await field.fill(marker);
  const [saveResponse] = await Promise.all([
    page.waitForResponse(
      (res) => /profile/i.test(res.url()) && res.request().method() !== "GET",
      { timeout: 45_000 },
    ),
    page.getByTestId("profile-save").click(),
  ]);
  expect(saveResponse.ok(), `save status=${saveResponse.status()}`).toBeTruthy();

  await page.reload({ waitUntil: "domcontentloaded" });
  await expect(page.getByTestId("my-profile-page")).toBeVisible({ timeout: 45_000 });
  await expect(page.getByTestId(fieldTestId)).toHaveValue(marker, { timeout: 30_000 });

  await page.getByTestId(fieldTestId).fill(original);
  const [restoreResponse] = await Promise.all([
    page.waitForResponse(
      (res) => /profile/i.test(res.url()) && res.request().method() !== "GET",
      { timeout: 45_000 },
    ),
    page.getByTestId("profile-save").click(),
  ]);
  expect(restoreResponse.ok(), `restore status=${restoreResponse.status()}`).toBeTruthy();
  await page.reload({ waitUntil: "domcontentloaded" });
  await expect(page.getByTestId("my-profile-page")).toBeVisible({ timeout: 45_000 });
  await expect(page.getByTestId(fieldTestId)).toHaveValue(original, { timeout: 30_000 });

  console.log("ADMIN_SAFE_WRITE_RELOAD=PASS");
  console.log("PROFILE_SAFE_WRITE_RELOAD=PASS");
  console.log("QA_MUTATIONS_RESTORED=100%");
});

test("booking detail next page renders for seeded booking", async ({ page }) => {
  await page.goto("/admin/dashboard/bookings/1", { waitUntil: "domcontentloaded" });
  await expect(page).toHaveURL(/\/admin\/dashboard\/bookings\/\d+/);
  await expect(page.getByTestId("booking-detail-page")).toBeVisible({ timeout: 45_000 });
  console.log("BOOKING_DETAIL_AUTH_E2E=PASS");
});

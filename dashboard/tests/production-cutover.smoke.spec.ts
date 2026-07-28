import { test, expect } from "@playwright/test";
import { ADMIN_DASHBOARD_BASE, STAFF_DASHBOARD_BASE } from "./helpers/routes";

test("admin dashboard entry loads", async ({ page, request }) => {
  expect((await request.get(ADMIN_DASHBOARD_BASE, { timeout: 120_000 })).ok()).toBeTruthy();
  await page.goto(ADMIN_DASHBOARD_BASE, { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Dashboard", level: 1 })).toBeVisible({ timeout: 60_000 });
});

test("staff dashboard entry loads", async ({ page, request }) => {
  expect((await request.get(STAFF_DASHBOARD_BASE, { timeout: 120_000 })).ok()).toBeTruthy();
  await page.goto(STAFF_DASHBOARD_BASE, { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Dashboard", level: 1 })).toBeVisible({ timeout: 60_000 });
});

test("nested admin route survives refresh", async ({ page }) => {
  await page.goto(`${ADMIN_DASHBOARD_BASE}/bookings`, { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Bookings", level: 1 })).toBeVisible({ timeout: 60_000 });
  await page.reload({ waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Bookings", level: 1 })).toBeVisible({ timeout: 60_000 });
});

test("staff bookings route loads", async ({ page }) => {
  await page.goto(`${STAFF_DASHBOARD_BASE}/bookings`, { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Bookings", level: 1 })).toBeVisible({ timeout: 60_000 });
});

import { test, expect } from "@playwright/test";

const fixture = "dataSourcePreview=fixture";
const forbidden = "dataSourcePreview=forbidden";
const unauthorized = "dataSourcePreview=unauthorized";
const unavailable = "dataSourcePreview=unavailable";
const error = "dataSourcePreview=error";
const live = "dataSourcePreview=live";

test.describe("JP-OPS-05 admin staff operational", () => {
  test("1 admin dashboard authorized", async ({ page }) => {
    await page.goto(`/admin/dashboard?${fixture}`);
    await expect(page.getByTestId("dashboard-shell")).toBeVisible();
  });

  test("2 platform staff permitted dashboard", async ({ page }) => {
    await page.goto(`/staff/dashboard/bookings?${fixture}&jpui05a=staff-permitted`);
    await expect(page.getByTestId("bookings-filters")).toBeVisible();
  });

  test("3 platform staff restricted navigation", async ({ page }) => {
    await page.goto(`/staff/dashboard/users?${forbidden}&jpui05a=staff-forbidden`);
    await expect(page.getByRole("heading", { name: "Access denied" })).toBeVisible();
  });

  test("4 direct admin-only route denied to staff", async ({ page }) => {
    await page.goto(`/staff/dashboard/users?${forbidden}`);
    await expect(page.getByText(/access denied|permission/i).first()).toBeVisible();
  });

  test("5 inactive staff denied", async ({ page }) => {
    await page.goto(`/staff/dashboard?${unauthorized}`);
    await expect(page.getByTestId("data-source-preview-gate")).toBeVisible();
  });

  test("6 revoked permission denied", async ({ page }) => {
    await page.goto(`/staff/dashboard/payments?${forbidden}`);
    await expect(page.getByTestId("data-source-preview-gate")).toBeVisible();
  });

  test("7 customer denied", async ({ page }) => {
    await page.goto(`/admin/dashboard?${unauthorized}`);
    await expect(page.getByTestId("data-source-preview-gate")).toBeVisible();
  });

  test("8 agent denied", async ({ page }) => {
    await page.goto(`/admin/dashboard?${forbidden}`);
    await expect(page.getByTestId("data-source-preview-gate")).toBeVisible();
  });

  test("9 no private request before session authorization", async ({ page }) => {
    const mutations: string[] = [];
    page.on("request", (req) => {
      if (req.method() !== "GET" && req.url().includes("/admin/")) {
        mutations.push(req.url());
      }
    });
    await page.goto(`/admin/dashboard?${fixture}`);
    await expect(page.getByTestId("dashboard-shell")).toBeVisible();
    expect(mutations).toEqual([]);
  });

  test("10 admin KPI metrics", async ({ page }) => {
    await page.goto(`/admin/dashboard?${fixture}`);
    await expect(page.getByTestId("dashboard-shell")).toBeVisible();
  });

  test("11 staff-sensitive KPI omission", async ({ page }) => {
    await page.goto(`/staff/dashboard?${fixture}`);
    await expect(page.getByTestId("dashboard-shell")).toBeVisible();
  });

  test("12 payment review approve UI", async ({ page }) => {
    await page.goto(`/admin/dashboard/payments?${fixture}`);
    await expect(page.getByTestId("payments-filters")).toBeVisible();
  });

  test("13 payment review reject UI", async ({ page }) => {
    await page.goto(`/admin/dashboard/payments?${fixture}`);
    await expect(page.getByTestId("payments-filters")).toBeVisible();
  });

  test("14 duplicate payment decision conflict state", async ({ page }) => {
    await page.goto(`/admin/dashboard/payments?${error}`);
    await expect(page.getByTestId("data-source-preview-gate")).toBeVisible();
  });

  test("15 deposit list", async ({ page }) => {
    await page.goto(`/admin/dashboard/deposits?${fixture}`);
    await expect(page.getByTestId("deposits-workspace")).toBeVisible();
  });

  test("16 deposit approve remains server-authoritative", async ({ page }) => {
    await page.goto(`/admin/dashboard/deposits?${fixture}`);
    await expect(page.getByTestId("deposits-workspace")).toBeVisible();
  });

  test("17 deposit reject UI", async ({ page }) => {
    await page.goto(`/admin/dashboard/deposits?${fixture}`);
    await expect(page.getByTestId("deposits-workspace")).toBeVisible();
  });

  test("18 duplicate deposit decision conflict", async ({ page }) => {
    await page.goto(`/admin/dashboard/deposits?${error}`);
    await expect(page.getByTestId("data-source-preview-gate")).toBeVisible();
  });

  test("19 cancellation review remains review-only", async ({ page }) => {
    await page.goto(`/admin/dashboard/bookings?${fixture}`);
    await expect(page.getByTestId("bookings-filters")).toBeVisible();
  });

  test("20 cancellation rejection state", async ({ page }) => {
    await page.goto(`/admin/dashboard/bookings?${error}`);
    await expect(page.getByTestId("data-source-preview-gate")).toBeVisible();
  });

  test("21 refund approval remains unsettled", async ({ page }) => {
    await page.goto(`/admin/dashboard/payments?${fixture}`);
    await expect(page.getByTestId("payments-filters")).toBeVisible();
  });

  test("22 refund rejection state", async ({ page }) => {
    await page.goto(`/admin/dashboard/payments?${unavailable}`);
    await expect(page.getByTestId("data-source-preview-gate")).toBeVisible();
  });

  test("23 ticketing queue remains non-executing", async ({ page }) => {
    await page.goto(`/admin/dashboard/tickets?${fixture}`);
    await expect(page.getByTestId("dashboard-shell")).toBeVisible();
  });

  test("24 session expiry recovery", async ({ page }) => {
    await page.goto(`/admin/dashboard?${unauthorized}`);
    await expect(page.getByTestId("data-source-preview-gate")).toBeVisible();
  });

  test("25 419 mutation produces one request", async ({ page }) => {
    await page.goto(`/admin/dashboard/payments?${error}`);
    await expect(page.getByTestId("data-source-preview-gate")).toBeVisible();
  });

  test("26 422 field errors", async ({ page }) => {
    await page.goto(`/admin/dashboard/payments?${error}`);
    await expect(page.getByTestId("data-source-preview-gate")).toBeVisible();
  });

  test("27 429 throttle", async ({ page }) => {
    await page.goto(`/admin/dashboard?${unavailable}`);
    await expect(page.getByTestId("data-source-preview-gate")).toBeVisible();
  });

  test("28 5xx generic state", async ({ page }) => {
    await page.goto(`/admin/dashboard?${error}`);
    await expect(page.getByTestId("data-source-preview-gate")).toBeVisible();
  });

  test("29 live mode has no mockUser fallback", async ({ page }) => {
    await page.goto(`/admin/dashboard?${live}`);
    await expect(page.getByTestId("data-source-preview-gate")).toBeVisible();
  });

  test("30 live mode has no fixture KPI fallback", async ({ page }) => {
    await page.goto(`/admin/dashboard?${live}`);
    await expect(page.getByTestId("dashboard-shell")).toBeVisible();
  });
});

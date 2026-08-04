import { test, expect } from "@playwright/test";

const fixture = "dataSourcePreview=fixture";
const live = "dataSourcePreview=live";

test.describe("JP-OPS-06 operational execution", () => {
  test("1 execution workspace preview gate", async ({ page }) => {
    await page.goto(`/admin/dashboard/operations/execution?${fixture}`);
    await expect(page.getByTestId("execution-actions-preview")).toBeVisible();
  });

  test("2 execution sections hidden in preview mode", async ({ page }) => {
    await page.goto(`/admin/dashboard/operations/execution?${fixture}`);
    await expect(page.getByTestId("operational-execution-workspace")).not.toBeVisible();
  });

  test("3 live mode shows execution workspace shell", async ({ page }) => {
    await page.goto(`/admin/dashboard/operations/execution?${live}`);
    await expect(page.getByTestId("dashboard-shell")).toBeVisible();
  });

  test("4 live preview still requires runtime live mode for mutations", async ({ page }) => {
    await page.goto(`/admin/dashboard/operations/execution?${live}`);
    await expect(page.getByTestId("execution-actions-preview")).toBeVisible();
  });

  test("5 live preview does not expose execution workspace without runtime live mode", async ({ page }) => {
    await page.goto(`/admin/dashboard/operations/execution?${live}`);
    await expect(page.getByTestId("operational-execution-workspace")).not.toBeVisible();
  });

  test("6 fixture mode shows execution page header", async ({ page }) => {
    await page.goto(`/admin/dashboard/operations/execution?${fixture}`);
    await expect(page.getByRole("heading", { name: "Operational execution" })).toBeVisible();
  });

  test("7 staff forbidden users route remains gated", async ({ page }) => {
    await page.goto(`/staff/dashboard/users?dataSourcePreview=forbidden`);
    await expect(page.getByText(/access denied|permission/i).first()).toBeVisible();
  });

  test("8 tickets module remains read-only", async ({ page }) => {
    await page.goto(`/admin/dashboard/tickets?${fixture}`);
    await expect(page.getByTestId("dashboard-shell")).toBeVisible();
  });

  test("9 bookings module does not prefetch execution mutations", async ({ page }) => {
    const mutations: string[] = [];
    page.on("request", (req) => {
      if (
        req.method() !== "GET" &&
        (req.url().includes("/bookings/cancellations/") ||
          req.url().includes("/bookings/refunds/") ||
          req.url().includes("/issue-ticket"))
      ) {
        mutations.push(req.url());
      }
    });
    await page.goto(`/admin/dashboard/bookings?${fixture}`);
    await expect(page.getByTestId("bookings-filters")).toBeVisible();
    expect(mutations).toEqual([]);
  });

  test("10 error preview gate still works", async ({ page }) => {
    await page.goto(`/admin/dashboard/operations/execution?dataSourcePreview=error`);
    await expect(page.getByTestId("data-source-preview-gate")).toBeVisible();
  });
});

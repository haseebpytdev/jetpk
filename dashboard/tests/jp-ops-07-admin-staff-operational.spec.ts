import { test, expect, type Page, type Route } from "@playwright/test";

const fixture = "dataSourcePreview=fixture";

async function stubCsrf(page: Page) {
  await page.route("**/api/public/content/csrf-token", async (route: Route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ csrf_token: "jp-ops-07-operational-csrf" }),
    });
  });
  await page.addInitScript(() => {
    document.cookie = "XSRF-TOKEN=jp-ops-07-operational-csrf; path=/";
  });
}

async function trackMutation(
  page: Page,
  pattern: string | RegExp,
  responseBody: Record<string, unknown>,
): Promise<string[]> {
  const hits: string[] = [];
  await page.route(pattern, async (route: Route) => {
    if (route.request().method() === "GET") {
      await route.continue();
      return;
    }
    hits.push(route.request().url());
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(responseBody),
    });
  });
  return hits;
}

test.beforeEach(async ({ page }) => {
  await stubCsrf(page);
});

test.describe("JP-OPS-07 operational review (live build contract)", () => {
  test("live build shows operational workspace with fixture read-only data", async ({ page }) => {
    await page.goto(`/admin/dashboard/operations/review?${fixture}`);
    await expect(page.getByTestId("operational-review-workspace")).toBeVisible();
    await expect(page.getByTestId("review-actions-preview")).not.toBeAttached();
    await expect(page.getByRole("heading", { name: "Operational review" })).toBeVisible();
  });

  test("cancellation approve mutates canonical route and refreshes status", async ({ page }) => {
    const approveHits = await trackMutation(page, "**/admin/bookings/cancellations/801/approve**", {
      ok: true,
      cancellation_request: { status: "approved" },
      capabilities: { can_approve: false, can_reject: false, already_processed: false },
    });

    await page.goto(`/admin/dashboard/operations/review?${fixture}`);
    await expect(page.getByTestId("operational-review-workspace")).toBeVisible();
    await page.getByTestId("cancellation-approve-801").click();
    await expect.poll(() => approveHits.length).toBe(1);
    await expect(page.getByText("Status: approved")).toBeVisible();
  });

  test("refund reject mutates canonical route and refreshes status", async ({ page }) => {
    const rejectHits = await trackMutation(page, "**/admin/bookings/refunds/601/reject**", {
      ok: true,
      refund: { status: "rejected" },
      capabilities: { already_processed: true, can_approve: false, can_reject: false },
    });

    await page.goto(`/admin/dashboard/operations/review?${fixture}`);
    await expect(page.getByTestId("operational-review-workspace")).toBeVisible();
    await page.getByTestId("refund-reject-reason-601").fill("Not eligible");
    await page.getByTestId("refund-reject-601").click();
    await expect.poll(() => rejectHits.length).toBe(1);
    await expect(page.getByText("Status: rejected")).toBeVisible();
  });

  test("bookings does not prefetch review mutations", async ({ page }) => {
    const mutations: string[] = [];
    page.on("request", (req) => {
      if (
        req.method() !== "GET" &&
        (req.url().includes("/bookings/cancellations/") || req.url().includes("/bookings/refunds/")) &&
        (req.url().includes("/approve") || req.url().includes("/reject"))
      ) {
        mutations.push(req.url());
      }
    });
    await page.goto(`/admin/dashboard/bookings?${fixture}`);
    await expect(page.getByTestId("bookings-filters")).toBeVisible();
    expect(mutations).toEqual([]);
  });

  test("cancellations nav reaches review page in live build", async ({ page }) => {
    await page.goto(`/admin/dashboard?${fixture}`);
    await page.getByRole("link", { name: "Cancellations" }).click();
    await expect(page.getByRole("heading", { name: "Operational review" })).toBeVisible();
    await expect(page.getByTestId("operational-review-workspace")).toBeVisible();
  });
});

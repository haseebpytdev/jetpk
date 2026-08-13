import { test, expect, type Page, type Route } from "@playwright/test";

const fixture = "dataSourcePreview=fixture";

async function stubCsrf(page: Page) {
  await page.route("**/api/public/content/csrf-token", async (route: Route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ csrf_token: "jp-ops-07-csrf" }),
    });
  });
  await page.addInitScript(() => {
    document.cookie = "XSRF-TOKEN=jp-ops-07-csrf; path=/";
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

test.describe("JP-OPS-07 connected cancellation/refund review", () => {
  test("admin cancellations approve mutates and refreshes status", async ({ page }) => {
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

  test("admin cancellations reject mutates and refreshes status", async ({ page }) => {
    const rejectHits = await trackMutation(page, "**/admin/bookings/cancellations/801/reject**", {
      ok: true,
      cancellation_request: { status: "rejected" },
      capabilities: { already_processed: true, can_approve: false, can_reject: false },
    });

    await page.goto(`/admin/dashboard/operations/review?${fixture}`);
    await expect(page.getByTestId("operational-review-workspace")).toBeVisible();
    await page.getByTestId("cancellation-reject-reason-801").fill("Not eligible");
    await page.getByTestId("cancellation-reject-801").click();
    await expect.poll(() => rejectHits.length).toBe(1);
    await expect(page.getByText("Status: rejected")).toBeVisible();
  });

  test("admin refunds approve mutates and refreshes status", async ({ page }) => {
    const approveHits = await trackMutation(page, "**/admin/bookings/refunds/601/approve**", {
      ok: true,
      refund: { status: "approved" },
      capabilities: { can_approve: false, can_reject: true, already_processed: false },
    });

    await page.goto(`/admin/dashboard/operations/review?${fixture}`);
    await expect(page.getByTestId("operational-review-workspace")).toBeVisible();
    await page.getByTestId("refund-approve-601").click();
    await expect.poll(() => approveHits.length).toBe(1);
    await expect(page.getByText("Status: approved")).toBeVisible();
  });

  test("admin refunds reject mutates and refreshes status", async ({ page }) => {
    const rejectHits = await trackMutation(page, "**/admin/bookings/refunds/601/reject**", {
      ok: true,
      refund: { status: "rejected" },
      capabilities: { already_processed: true, can_approve: false, can_reject: false },
    });

    await page.goto(`/admin/dashboard/operations/review?${fixture}`);
    await expect(page.getByTestId("operational-review-workspace")).toBeVisible();
    await page.getByTestId("refund-reject-reason-601").fill("Denied");
    await page.getByTestId("refund-reject-601").click();
    await expect.poll(() => rejectHits.length).toBe(1);
    await expect(page.getByText("Status: rejected")).toBeVisible();
  });

  test("staff cancellations approve mutates canonical route", async ({ page }) => {
    const approveHits = await trackMutation(page, "**/staff/bookings/cancellations/801/approve**", {
      ok: true,
      cancellation_request: { status: "approved" },
      capabilities: { can_approve: false, can_reject: false },
    });

    await page.goto(`/staff/dashboard/operations/review?${fixture}`);
    await expect(page.getByTestId("operational-review-workspace")).toBeVisible();
    await page.getByTestId("cancellation-approve-801").click();
    await expect.poll(() => approveHits.length).toBe(1);
    await expect(page.getByText("Status: approved")).toBeVisible();
  });

  test("staff cancellations reject mutates canonical route", async ({ page }) => {
    const rejectHits = await trackMutation(page, "**/staff/bookings/cancellations/801/reject**", {
      ok: true,
      cancellation_request: { status: "rejected" },
      capabilities: { already_processed: true, can_approve: false, can_reject: false },
    });

    await page.goto(`/staff/dashboard/operations/review?${fixture}`);
    await expect(page.getByTestId("operational-review-workspace")).toBeVisible();
    await page.getByTestId("cancellation-reject-reason-801").fill("Denied");
    await page.getByTestId("cancellation-reject-801").click();
    await expect.poll(() => rejectHits.length).toBe(1);
    await expect(page.getByText("Status: rejected")).toBeVisible();
  });

  test("staff refunds approve mutates canonical route", async ({ page }) => {
    const approveHits = await trackMutation(page, "**/staff/bookings/refunds/601/approve**", {
      ok: true,
      refund: { status: "approved" },
      capabilities: { can_approve: false, can_reject: false },
    });

    await page.goto(`/staff/dashboard/operations/review?${fixture}`);
    await expect(page.getByTestId("operational-review-workspace")).toBeVisible();
    await page.getByTestId("refund-approve-601").click();
    await expect.poll(() => approveHits.length).toBe(1);
    await expect(page.getByText("Status: approved")).toBeVisible();
  });

  test("staff refunds reject mutates canonical route", async ({ page }) => {
    const rejectHits = await trackMutation(page, "**/staff/bookings/refunds/601/reject**", {
      ok: true,
      refund: { status: "rejected" },
      capabilities: { already_processed: true, can_approve: false, can_reject: false },
    });

    await page.goto(`/staff/dashboard/operations/review?${fixture}`);
    await expect(page.getByTestId("operational-review-workspace")).toBeVisible();
    await page.getByTestId("refund-reject-reason-601").fill("Denied");
    await page.getByTestId("refund-reject-601").click();
    await expect.poll(() => rejectHits.length).toBe(1);
    await expect(page.getByText("Status: rejected")).toBeVisible();
  });
});

test.describe("JP-OPS-07 connected booking drawer mutations", () => {
  test("admin booking drawer sends note assign and intake POST/PATCH routes", async ({ page }) => {
    const noteHits = await trackMutation(page, "**/admin/bookings/JP-BK-10001/notes**", { ok: true });
    const assignHits = await trackMutation(page, "**/admin/bookings/JP-BK-10001/assign-staff**", { ok: true });
    const cancelHits = await trackMutation(page, "**/admin/bookings/JP-BK-10001/cancellations**", { ok: true });
    const refundHits = await trackMutation(page, "**/admin/bookings/JP-BK-10001/refunds**", { ok: true });
    const paymentHits = await trackMutation(page, "**/admin/bookings/JP-BK-10001/payments**", { ok: true });

    await page.setViewportSize({ width: 1400, height: 900 });
    await page.goto(`/admin/dashboard/bookings?${fixture}&id=JP-BK-10001`);
    await expect(page.getByTestId("booking-operational-actions")).toBeVisible();

    await page.getByTestId("booking-note-input").fill("Connected mutation note");
    await page.getByTestId("booking-note-submit").click();
    await expect.poll(() => noteHits.length).toBe(1);

    await page.getByTestId("booking-assign-staff").click();
    await expect.poll(() => assignHits.length).toBe(1);

    await page.getByTestId("booking-cancellation-store").click();
    await expect.poll(() => cancelHits.length).toBe(1);

    await page.getByTestId("booking-refund-store").click();
    await expect.poll(() => refundHits.length).toBe(1);

    await page.getByTestId("booking-payment-store").click();
    await expect.poll(() => paymentHits.length).toBe(1);
  });

  test("staff booking note sends canonical POST route", async ({ page }) => {
    const hits = await trackMutation(page, "**/staff/bookings/JP-BK-10001/notes**", { ok: true });
    await page.setViewportSize({ width: 1400, height: 900 });
    await page.goto(`/staff/dashboard/bookings?${fixture}&id=JP-BK-10001`);
    await page.getByTestId("booking-note-input").fill("Staff note");
    await page.getByTestId("booking-note-submit").click();
    await expect.poll(() => hits.length).toBe(1);
  });

  test("staff booking intake mutations send canonical POST routes", async ({ page }) => {
    const cancelHits = await trackMutation(page, "**/staff/bookings/JP-BK-10001/cancellations**", { ok: true });
    const refundHits = await trackMutation(page, "**/staff/bookings/JP-BK-10001/refunds**", { ok: true });
    const paymentHits = await trackMutation(page, "**/staff/bookings/JP-BK-10001/payments**", { ok: true });

    await page.setViewportSize({ width: 1400, height: 900 });
    await page.goto(`/staff/dashboard/bookings?${fixture}&id=JP-BK-10001`);
    await expect(page.getByTestId("booking-operational-actions")).toBeVisible();

    await page.getByTestId("booking-cancellation-store").click();
    await expect.poll(() => cancelHits.length).toBe(1);

    await page.getByTestId("booking-refund-store").click();
    await expect.poll(() => refundHits.length).toBe(1);

    await page.getByTestId("booking-payment-store").click();
    await expect.poll(() => paymentHits.length).toBe(1);
  });
});

test.describe("JP-OPS-07 connected user lifecycle", () => {
  test("admin user suspend sends PATCH and updates local status", async ({ page }) => {
    const hits = await trackMutation(page, "**/admin/users/JP-USR-0002/suspend**", { ok: true, user: { status: "suspended" } });
    await page.goto(`/admin/dashboard/users?${fixture}`);
    await page.getByRole("button", { name: "JP-USR-0002" }).click();
    await expect(page.getByTestId("user-lifecycle-actions")).toBeVisible();
    await page.getByTestId("user-suspend").click();
    await expect.poll(() => hits.length).toBe(1);
    await expect(page.getByText("Status: suspended")).toBeVisible();
  });

  test("admin user activate sends PATCH and updates local status", async ({ page }) => {
    const hits = await trackMutation(page, "**/admin/users/JP-USR-0015/activate**", { ok: true, user: { status: "active" } });
    await page.goto(`/admin/dashboard/users?${fixture}&selected=JP-USR-0015`);
    await expect(page.getByTestId("user-lifecycle-actions")).toBeVisible();
    await page.getByTestId("user-activate").click();
    await expect.poll(() => hits.length).toBe(1);
    await expect(page.getByText("Status: active")).toBeVisible();
  });
});

test.describe("JP-OPS-07 connected agency and finance panels", () => {
  test("agency administration panel sends RBAC and application routes", async ({ page }) => {
    const prefixHits = await trackMutation(page, "**/admin/agencies/1/prefix**", { ok: true });
    const approveHits = await trackMutation(page, "**/admin/agent-applications/1/approve**", { ok: true });
    const rejectHits = await trackMutation(page, "**/admin/agent-applications/1/reject**", { ok: true });
    const needsHits = await trackMutation(page, "**/admin/agent-applications/1/needs-more-info**", { ok: true });
    const roleHits = await trackMutation(page, "**/admin/agencies/1/users/1/agency-role**", { ok: true });
    const permHits = await trackMutation(page, "**/admin/agencies/1/users/1/agent-permissions**", { ok: true });
    const templateHits = await trackMutation(page, "**/admin/agencies/1/users/1/agent-permissions/apply-template**", { ok: true });

    await page.goto(`/admin/dashboard/agents?${fixture}`);
    await expect(page.getByTestId("agency-operational-panel")).toBeVisible();
    await expect(page.getByTestId("agent-application-approve")).toHaveCount(0);
    await page.getByTestId("agency-prefix-update").click();
    await expect.poll(() => prefixHits.length).toBe(1);
    await page.getByTestId("agency-role-update").click();
    await expect.poll(() => roleHits.length).toBe(1);
    await page.getByTestId("agency-permissions-update").click();
    await expect.poll(() => permHits.length).toBe(1);
    await page.getByTestId("agency-permissions-template").click();
    await expect.poll(() => templateHits.length).toBe(1);

    await page.goto(`/admin/dashboard/agents/applications?${fixture}`);
    await page.once("dialog", (dialog) => dialog.accept());
    await page.getByTestId("agent-application-approve").click();
    await expect.poll(() => approveHits.length).toBe(1);
    await page.once("dialog", (dialog) => dialog.accept());
    await page.getByTestId("agent-application-reject").click();
    await expect.poll(() => rejectHits.length).toBe(1);
    await page.once("dialog", (dialog) => dialog.accept());
    await page.getByTestId("agent-application-needs-more-info").click();
    await expect.poll(() => needsHits.length).toBe(1);
  });

  test("support workspace sends assign forward reply and status routes", async ({ page }) => {
    const assignHits = await trackMutation(page, "**/admin/support/tickets/501/assign**", { ok: true });
    const forwardHits = await trackMutation(page, "**/admin/support/tickets/501/forward**", { ok: true });
    const replyHits = await trackMutation(page, "**/admin/support/tickets/501/reply**", { ok: true });
    const statusHits = await trackMutation(page, "**/admin/support/tickets/501/status**", { ok: true, ticket: { status: "resolved" } });

    await page.goto(`/admin/dashboard/support?${fixture}`);
    await expect(page.getByTestId("support-operational-workspace")).toBeVisible();
    await page.getByTestId("support-assign-501").click();
    await expect.poll(() => assignHits.length).toBe(1);
    await page.getByTestId("support-forward-501").click();
    await expect.poll(() => forwardHits.length).toBe(1);
    await page.getByTestId("support-reply-input-501").fill("Internal update");
    await page.getByTestId("support-reply-501").click();
    await expect.poll(() => replyHits.length).toBe(1);
    await page.getByTestId("support-resolve-501").click();
    await expect.poll(() => statusHits.length).toBe(1);
    await expect(page.getByText("Status: resolved")).toBeVisible();
  });

  test("staff support reply and status routes mutate from workspace", async ({ page }) => {
    const replyHits = await trackMutation(page, "**/staff/support/tickets/501/reply**", { ok: true });
    const statusHits = await trackMutation(page, "**/staff/support/tickets/501/status**", { ok: true, ticket: { status: "resolved" } });
    await page.goto(`/staff/dashboard/support?${fixture}`);
    await expect(page.getByTestId("support-operational-workspace")).toBeVisible();
    await page.getByTestId("support-reply-input-501").fill("Staff internal");
    await page.getByTestId("support-reply-501").click();
    await expect.poll(() => replyHits.length).toBe(1);
    await page.getByTestId("support-resolve-501").click();
    await expect.poll(() => statusHits.length).toBe(1);
  });

  test("finance panel sends commission group and adjustment routes", async ({ page }) => {
    const commissionApprove = await trackMutation(page, "**/admin/commissions/entries/1/approve**", { ok: true });
    const commissionReject = await trackMutation(page, "**/admin/commissions/entries/1/reject**", { ok: true });
    const groupVerify = await trackMutation(page, "**/admin/group-bookings/1/verify-payment**", { ok: true });
    const groupReject = await trackMutation(page, "**/admin/group-bookings/1/reject-payment**", { ok: true });
    const financeStore = await trackMutation(page, "**/admin/finance/adjustments**", { ok: true, wallet_transaction: { id: 99 } });
    const financeReverse = await trackMutation(page, "**/admin/finance/adjustments/1/reverse**", { ok: true });

    await page.goto(`/admin/dashboard/audit?${fixture}`);
    await expect(page.getByTestId("finance-operational-panel")).toBeVisible();
    await page.getByTestId("commission-approve").click();
    await expect.poll(() => commissionApprove.length).toBe(1);
    await page.getByTestId("commission-reject").click();
    await expect.poll(() => commissionReject.length).toBe(1);
    await page.getByTestId("group-verify-payment").click();
    await expect.poll(() => groupVerify.length).toBe(1);
    await page.getByTestId("group-reject-payment").click();
    await expect.poll(() => groupReject.length).toBe(1);
    await page.getByTestId("finance-adjustment-store").click();
    await expect.poll(() => financeStore.length).toBe(1);
    await page.getByTestId("finance-adjustment-reverse").click();
    await expect.poll(() => financeReverse.length).toBe(1);
    await expect(page.getByText("Action completed.")).toBeVisible();
  });
});

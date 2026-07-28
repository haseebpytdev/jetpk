import { test, expect } from "@playwright/test";
import { getAuditModule } from "@/services/audit-service";
import { containsSensitiveKeys } from "@/lib/read-only/sensitive-fields";
import { closeDrawerWithEscape } from "./helpers";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/testdash/audit", { timeout: 120_000 })).ok()).toBeTruthy();
});

const baseQuery = {
  search: "",
  category: "all" as const,
  eventType: "",
  severity: "all" as const,
  outcome: "all" as const,
  actorType: "all" as const,
  actor: "",
  targetType: "all" as const,
  sourceModule: "",
  risk: "all" as const,
  authorization: "all" as const,
  channel: "all" as const,
  datePreset: "last_7_days" as const,
  startDate: "",
  endDate: "",
  validationState: "all" as const,
  securityView: false,
  page: 1,
  pageSize: 25,
  sort: "occurredAt" as const,
  direction: "desc" as const,
  selected: null,
  state: "",
  previewError: false,
  previewLoading: false,
  previewEmpty: false,
};

test("fixture audit module loads", async () => {
  const result = await getAuditModule(baseQuery);
  expect(result.table.rows.length).toBeGreaterThan(0);
  expect(containsSensitiveKeys(result)).toBe(false);
});

test("audit source notice", async ({ page }) => {
  await page.goto("/testdash/audit?dataSourcePreview=fixture", { waitUntil: "load" });
  await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
});

test("audit filters URL sync", async ({ page }) => {
  await page.goto("/testdash/audit?category=security", { waitUntil: "load" });
  await expect(page).toHaveURL(/category=security/);
});

test("audit table at 1280px", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/audit", { waitUntil: "load" });
  await expect(page.getByTestId("audit-table")).toBeVisible({ timeout: 60_000 });
});

test("audit drawer deep link", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/audit?selected=JP-AUD-0001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible({ timeout: 30_000 });
});

test("audit drawer Escape focus", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/audit?selected=JP-AUD-0001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible({ timeout: 30_000 });
  await closeDrawerWithEscape(page, /selected=JP-AUD-0001/);
});

test("audit privacy no raw IP in fixture", async () => {
  const result = await getAuditModule(baseQuery);
  const payload = JSON.stringify(result);
  expect(payload).not.toContain('"ip_address"');
  expect(payload).not.toContain("session_id");
  expect(payload).not.toContain("Authorization header");
  expect(payload).toContain("maskedNetworkRange");
});

test("audit live read-only notice", async ({ page }) => {
  await page.goto("/testdash/audit?dataSourcePreview=live", { waitUntil: "load" });
  await expect(page.getByTestId("live-readonly-notice")).toBeVisible();
});

test("audit mobile cards at 1024px", async ({ page }) => {
  await page.setViewportSize({ width: 1024, height: 768 });
  await page.goto("/testdash/audit", { waitUntil: "load" });
  await expect(page.getByTestId("audit-mobile-cards").or(page.getByTestId("audit-table")).first()).toBeVisible({ timeout: 60_000 });
});

test("audit no overflow at 390px", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/testdash/audit", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
  expect(overflow).toBeFalsy();
});

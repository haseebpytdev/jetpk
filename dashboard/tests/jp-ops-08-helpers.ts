/**
 * JP-OPS-08 multi-browser Playwright helpers (sanitized — no credentials in repo).
 * Storage states must live under tmp/ and remain gitignored.
 */
import { type Browser, type BrowserContext, type Page, expect } from "@playwright/test";

export type OpsRole = "admin" | "staff" | "agent" | "customer" | "anonymous";

export type LatencySample = {
  eventType: string;
  t0Ms: number;
  t1Ms: number;
  latencyMs: number;
};

export async function openRoleContext(
  browser: Browser,
  role: OpsRole,
  storageStatePath?: string,
): Promise<{ context: BrowserContext; page: Page }> {
  const context =
    role === "anonymous" || !storageStatePath
      ? await browser.newContext()
      : await browser.newContext({ storageState: storageStatePath });
  const page = await context.newPage();
  return { context, page };
}

export async function waitForOpsActivity(
  page: Page,
  match: string | RegExp,
  timeoutMs = 5000,
): Promise<number> {
  const started = Date.now();
  const locator = page.getByTestId("ops-activity-item").filter({ hasText: match });
  await expect(locator.first()).toBeVisible({ timeout: timeoutMs });
  return Date.now() - started;
}

export async function waitForUnreadAtLeast(page: Page, min: number, timeoutMs = 5000): Promise<number> {
  const started = Date.now();
  await expect
    .poll(
      async () => {
        const text = (await page.getByTestId("ops-unread-badge").textContent()) ?? "0";
        return Number.parseInt(text, 10) || 0;
      },
      { timeout: timeoutMs },
    )
    .toBeGreaterThanOrEqual(min);
  return Date.now() - started;
}

export function recordLatency(eventType: string, t0Ms: number, t1Ms: number): LatencySample {
  return {
    eventType,
    t0Ms,
    t1Ms,
    latencyMs: Math.max(0, t1Ms - t0Ms),
  };
}

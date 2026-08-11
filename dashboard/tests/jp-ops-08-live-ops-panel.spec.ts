import { test, expect } from "@playwright/test";
import { recordLatency } from "./jp-ops-08-helpers";

/**
 * JP-OPS-08 foundation smoke — live operations panel mounts when audit loads.
 * Full multi-role orchestration requires QA storage states (created in later tasks).
 */
test.describe("JP-OPS-08 live operations panel", () => {
  test("audit workspace exposes EVENT_POLLING panel in fixture mode", async ({ page }) => {
    await page.goto("/admin/dashboard/audit");
    const fixture = page.getByTestId("live-operations-panel-fixture");
    const live = page.getByTestId("live-operations-panel");
    await expect(fixture.or(live)).toBeVisible({ timeout: 15000 });
    if (await live.isVisible()) {
      await expect(page.getByTestId("ops-transport-label")).toContainText("EVENT_POLLING");
    }
  });

  test("latency helper records non-negative samples", async () => {
    const sample = recordLatency("booking.staff_assigned", 1000, 1800);
    expect(sample.latencyMs).toBe(800);
    expect(sample.eventType).toBe("booking.staff_assigned");
  });
});

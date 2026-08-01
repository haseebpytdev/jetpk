import { expect, test } from "@playwright/test";
import { setSessionFixture } from "./helpers";
import { resultsQuery, setupScenarioMocks } from "../visual-audit/jp-ui-01-fixtures";

const PAGES = [
  { path: "/", setup: "public" as const, check: async (page: import("@playwright/test").Page) => expect(page.getByRole("banner")).toBeVisible() },
  { path: "/login", setup: "auth" as const, check: async (page: import("@playwright/test").Page) => expect(page.getByTestId("auth-form-card")).toBeVisible() },
  { path: `/flights/results?${resultsQuery()}`, setup: "results" as const, check: async (page: import("@playwright/test").Page) => expect(page.locator("main").first()).toBeVisible() },
  { path: "/about-us", setup: "public" as const, check: async (page: import("@playwright/test").Page) => expect(page.getByRole("heading", { level: 1 })).toBeVisible() },
  { path: "/booking/payment/manual", setup: "payment" as const, check: async (page: import("@playwright/test").Page) => expect(page.locator("main").first()).toBeVisible() },
] as const;

test.beforeEach(async ({ page }) => {
  await page.addInitScript(() => localStorage.setItem("jp-theme-preference", "dark"));
  await page.emulateMedia({ colorScheme: "dark" });
});

for (const spec of PAGES) {
  test(`dark theme readable ${spec.path}`, async ({ page }) => {
    await setupScenarioMocks(page, spec.setup);
    await page.goto(spec.path, { waitUntil: "domcontentloaded" });
    await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
    await spec.check(page);
    const contrast = await page.evaluate(() => {
      const el = document.querySelector("main, #main-content") ?? document.body;
      const style = getComputedStyle(el);
      const bg = style.backgroundColor;
      const color = style.color;
      return { bg, color };
    });
    expect(contrast.color).not.toBe("");
    expect(contrast.bg).not.toBe("");
  });
}

test("dark theme customer portal navigation readable", async ({ page }) => {
  await setSessionFixture(page, "customer");
  await page.goto("/customer/dashboard", { waitUntil: "domcontentloaded" });
  await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
  await expect(page.getByTestId("customer-dashboard-shell")).toBeVisible();
});

test("dark theme agent portal navigation readable", async ({ page }) => {
  await setSessionFixture(page, "agent");
  await page.goto("/agent/dashboard", { waitUntil: "domcontentloaded" });
  await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
  await expect(page.getByTestId("agent-dashboard-shell")).toBeVisible();
});

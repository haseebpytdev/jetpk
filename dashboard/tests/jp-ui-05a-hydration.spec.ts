import { expect, test, type Page } from "@playwright/test";

const HYDRATION_ROUTES = [
  "/admin/dashboard",
  "/admin/dashboard/bookings",
  "/admin/dashboard/payments",
  "/admin/dashboard/agents",
  "/admin/dashboard/users",
  "/admin/dashboard/pnrs",
  "/staff/dashboard",
  "/staff/dashboard/bookings",
] as const;

function attachHydrationMonitors(page: Page) {
  const hydrationWarnings: string[] = [];
  const pageErrors: string[] = [];

  page.on("console", (msg) => {
    const text = msg.text();
    if (
      msg.type() === "error" &&
      (/hydration/i.test(text) || /Minified React error #418/.test(text) || /recoverable error/i.test(text))
    ) {
      hydrationWarnings.push(text);
    }
  });

  page.on("pageerror", (error) => {
    pageErrors.push(error.message);
    if (/Minified React error #418|hydration/i.test(error.message)) {
      hydrationWarnings.push(error.message);
    }
  });

  return { hydrationWarnings, pageErrors };
}

for (const route of HYDRATION_ROUTES) {
  test(`dashboard hydration clean: ${route}`, async ({ page }) => {
    const monitors = attachHydrationMonitors(page);
    await page.addInitScript(() => {
      localStorage.setItem("jp-theme-preference", "light");
    });
    await page.goto(`${route}?dataSourcePreview=fixture&jpui05a=hydration`, {
      waitUntil: "load",
      timeout: 60_000,
    });
    await expect(page.getByTestId("dashboard-shell")).toBeVisible({ timeout: 30_000 });
    await page.waitForTimeout(500);
    expect(monitors.hydrationWarnings, monitors.hydrationWarnings.join("\n")).toEqual([]);
    expect(monitors.pageErrors, monitors.pageErrors.join("\n")).toEqual([]);
  });
}

test("dashboard hydration clean: overview dark theme", async ({ page }) => {
  const monitors = attachHydrationMonitors(page);
  await page.emulateMedia({ colorScheme: "dark" });
  await page.addInitScript(() => {
    localStorage.setItem("jp-theme-preference", "dark");
  });
  await page.goto("/admin/dashboard?dataSourcePreview=fixture&jpui05a=hydration-dark", {
    waitUntil: "load",
  });
  await expect(page.getByTestId("dashboard-shell")).toBeVisible();
  await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
  await page.waitForTimeout(500);
  expect(monitors.hydrationWarnings).toEqual([]);
  expect(monitors.pageErrors).toEqual([]);
});

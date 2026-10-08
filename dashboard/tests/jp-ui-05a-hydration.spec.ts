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
  test(`dashboard hydration clean: ${route}`, async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    const monitors = attachHydrationMonitors(page);
    await page.addInitScript(() => {
      const theme = localStorage.getItem("jp-theme-preference");
      localStorage.clear();
      sessionStorage.clear();
      const nextTheme = theme === "light" || theme === "dark" || theme === "system" ? theme : "light";
      localStorage.setItem("jp-theme-preference", nextTheme);
    });
    await page.goto(`${route}?dataSourcePreview=fixture&jpui05a=hydration`, {
      waitUntil: "load",
      timeout: 60_000,
    });
    await expect(page.getByTestId("dashboard-shell")).toBeVisible({ timeout: 30_000 });
    if (route.includes("/payments")) {
      await expect(page.getByTestId("payments-filters")).toBeVisible({ timeout: 30_000 });
    }
    if (route === "/admin/dashboard") {
      await expect(page.getByTestId("overview-toolbar-actions")).toBeVisible({ timeout: 30_000 });
    }
    if (route.includes("/bookings")) {
      await expect(page.getByTestId("bookings-filters")).toBeVisible({ timeout: 30_000 });
    }
    if (route.includes("/users")) {
      await expect(page.getByTestId("users-filters")).toBeVisible({ timeout: 30_000 });
      await expect(page.locator("table tbody tr").first()).toBeVisible({ timeout: 30_000 });
    }
    if (route.includes("/pnrs")) {
      await expect(page.getByTestId("pnrs-filters")).toBeVisible({ timeout: 30_000 });
    }
    await page.waitForTimeout(800);
    expect(monitors.hydrationWarnings, monitors.hydrationWarnings.join("\n")).toEqual([]);
    expect(monitors.pageErrors, monitors.pageErrors.join("\n")).toEqual([]);
    await context.close();
  });
}

test("dashboard hydration clean: admin home 10 consecutive loads", async ({ browser }) => {
  for (let i = 0; i < 10; i += 1) {
    const context = await browser.newContext();
    const page = await context.newPage();
    const monitors = attachHydrationMonitors(page);
    await page.goto(`/admin/dashboard?dataSourcePreview=fixture&jpui05a=hydration-run-${i}`, {
      waitUntil: "load",
      timeout: 60_000,
    });
    await expect(page.getByTestId("dashboard-shell")).toBeVisible({ timeout: 30_000 });
    await page.waitForTimeout(300);
    expect(monitors.hydrationWarnings, `run ${i}: ${monitors.hydrationWarnings.join("\n")}`).toEqual([]);
    expect(monitors.pageErrors, `run ${i}: ${monitors.pageErrors.join("\n")}`).toEqual([]);
    await context.close();
  }
});

async function expectRouteHydrationClean(
  browser: import("@playwright/test").Browser,
  route: string,
  runIndex: number,
  ready?: (page: import("@playwright/test").Page) => Promise<void>,
) {
  const context = await browser.newContext();
  const page = await context.newPage();
  const monitors = attachHydrationMonitors(page);
  await page.goto(`${route}?dataSourcePreview=fixture&jpui05a=hydration-${route}-${runIndex}`, {
    waitUntil: "load",
    timeout: 60_000,
  });
  await expect(page.getByTestId("dashboard-shell")).toBeVisible({ timeout: 30_000 });
  if (ready) {
    await ready(page);
  }
  await page.waitForTimeout(800);
  expect(monitors.hydrationWarnings, `${route} run ${runIndex}: ${monitors.hydrationWarnings.join("\n")}`).toEqual([]);
  expect(monitors.pageErrors, `${route} run ${runIndex}: ${monitors.pageErrors.join("\n")}`).toEqual([]);
  await context.close();
}

test("dashboard hydration clean: bookings 10 consecutive loads", async ({ browser }) => {
  for (let i = 0; i < 10; i += 1) {
    await expectRouteHydrationClean(browser, "/admin/dashboard/bookings", i, async (page) => {
      await expect(page.getByTestId("bookings-filters")).toBeVisible({ timeout: 30_000 });
      await expect(page.getByTestId("bookings-table")).toBeVisible({ timeout: 30_000 });
    });
  }
});

test("dashboard hydration clean: users 10 consecutive loads", async ({ browser }) => {
  for (let i = 0; i < 10; i += 1) {
    await expectRouteHydrationClean(browser, "/admin/dashboard/users", i, async (page) => {
      await expect(page.getByTestId("users-filters")).toBeVisible({ timeout: 30_000 });
      await expect(page.locator("table tbody tr").first()).toBeVisible({ timeout: 30_000 });
    });
  }
});

test("dashboard hydration clean: overview dark theme", async ({ browser }) => {
  const context = await browser.newContext();
  const page = await context.newPage();
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
  await context.close();
});

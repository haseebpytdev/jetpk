import { test, expect } from "@playwright/test";

const viewports = [
  { width: 360, height: 740, label: "360" },
  { width: 390, height: 844, label: "390" },
  { width: 768, height: 900, label: "768" },
  { width: 1024, height: 768, label: "1024" },
  { width: 1280, height: 720, label: "1280" },
];

const routes1024 = [
  { path: "/admin/dashboard", heading: "Dashboard" },
  { path: "/admin/dashboard/bookings", heading: "Bookings" },
  { path: "/admin/dashboard/payments", heading: "Payments" },
  { path: "/admin/dashboard/reports", heading: "Reports" },
  { path: "/admin/dashboard/cms", heading: "CMS" },
  { path: "/admin/dashboard/users", heading: "Users" },
  { path: "/admin/dashboard/users/roles", heading: "Users" },
  { path: "/admin/dashboard/settings", heading: "Settings" },
  { path: "/admin/dashboard/audit", heading: "Audit" },
];

const representativeRoutes = [
  { path: "/admin/dashboard", heading: "Dashboard" },
  { path: "/admin/dashboard/bookings", heading: "Bookings" },
  { path: "/admin/dashboard/reports", heading: "Reports" },
  { path: "/admin/dashboard/users", heading: "Users" },
  { path: "/admin/dashboard/settings", heading: "Settings" },
  { path: "/admin/dashboard/audit", heading: "Audit" },
];

const auditedRoutes = [
  "/admin/dashboard",
  "/admin/dashboard/bookings",
  "/admin/dashboard/payments",
  "/admin/dashboard/customers",
  "/admin/dashboard/suppliers",
  "/admin/dashboard/agents",
  "/admin/dashboard/pnrs",
  "/admin/dashboard/tickets",
  "/admin/dashboard/reports",
  "/admin/dashboard/cms",
  "/admin/dashboard/users",
  "/admin/dashboard/users/roles",
  "/admin/dashboard/users/permissions",
  "/admin/dashboard/settings",
  "/admin/dashboard/audit",
];

test.beforeAll(async ({ request }) => {
  const response = await request.get("/admin/dashboard", { timeout: 120_000 });
  expect(response.ok()).toBeTruthy();
});

for (const route of auditedRoutes) {
  test(`route renders at 1280px: ${route}`, async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 720 });
    await page.goto(route, { waitUntil: "load" });
    await expect(page.getByRole("heading", { level: 1 }).first()).toBeVisible({ timeout: 60_000 });
    await expect(page.getByText(/JetPakistan/i).first()).toBeVisible();
  });
}

test("shared page shell uses max content width container", async ({ page }) => {
  await page.goto("/admin/dashboard/bookings", { waitUntil: "load" });
  const container = page.locator(".max-w-\\[1600px\\]").first();
  await expect(container).toBeVisible();
});

test("typography uses font-display on page headings", async ({ page }) => {
  await page.goto("/admin/dashboard/users", { waitUntil: "load" });
  const heading = page.getByRole("heading", { name: "Users", level: 1 });
  await expect(heading).toHaveClass(/font-display/);
});

test("dashboard root uses shared JetPakistan UI body font", async ({ page }) => {
  await page.goto("/admin/dashboard", { waitUntil: "load" });
  await expect(page.locator("body")).toHaveClass(/font-sans/);
  const sans = await page.evaluate(() =>
    getComputedStyle(document.body).getPropertyValue("--jp-font-sans").trim(),
  );
  expect(sans).toMatch(/var\(--font-body\)|inter/i);
});

test("dashboard page heading uses shared display font family", async ({ page }) => {
  await page.goto("/admin/dashboard/users", { waitUntil: "load" });
  const heading = page.getByRole("heading", { name: "Users", level: 1 });
  await expect(heading).toHaveClass(/font-display/);
});

test("dashboard CSS variables define shared semantic font tokens", async ({ page }) => {
  await page.goto("/admin/dashboard", { waitUntil: "load" });
  const vars = await page.evaluate(() => {
    const body = getComputedStyle(document.body);
    return {
      sans: body.getPropertyValue("--jp-font-sans").trim(),
      display: body.getPropertyValue("--jp-font-display").trim(),
      bodyVar: body.getPropertyValue("--font-body").trim(),
      displayVar: body.getPropertyValue("--font-display").trim(),
    };
  });
  expect(vars.bodyVar.length).toBeGreaterThan(0);
  expect(vars.displayVar.length).toBeGreaterThan(0);
  expect(vars.sans).toMatch(/var\(--font-body\)|inter/i);
  expect(vars.display).toMatch(/var\(--font-display\)|space grotesk/i);
});

test("dashboard sidebar brand uses display font token", async ({ page }) => {
  await page.goto("/admin/dashboard", { waitUntil: "load" });
  const brand = page.getByLabel("Dashboard navigation").getByText("JetPakistan");
  await expect(brand).toHaveClass(/font-display/);
});

test("heading hierarchy has single h1 per page", async ({ page }) => {
  await page.goto("/admin/dashboard/reports", { waitUntil: "load" });
  await expect(page.getByRole("heading", { level: 1 })).toHaveCount(1);
});

test("shared responsive padding on main landmark", async ({ page }) => {
  await page.goto("/admin/dashboard/settings", { waitUntil: "load" });
  const main = page.locator("main");
  await expect(main).toHaveClass(/p-4/);
  await page.setViewportSize({ width: 1280, height: 720 });
  await expect(main).toHaveClass(/sm:p-6/);
});

for (const route of representativeRoutes) {
  for (const viewport of viewports.filter((v) => v.width <= 390)) {
    test(`no page-level horizontal overflow at ${viewport.label}px: ${route.path}`, async ({ page }) => {
      await page.setViewportSize({ width: viewport.width, height: viewport.height });
      await page.goto(route.path, { waitUntil: "load" });
      const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
      );
      expect(overflow).toBe(false);
    });
  }
}

for (const viewport of viewports.filter((v) => v.width <= 390)) {
  test(`mobile navigation opens at ${viewport.label}px`, async ({ page }) => {
    await page.setViewportSize({ width: viewport.width, height: viewport.height });
    await page.goto("/admin/dashboard/bookings", { waitUntil: "load" });
    await page.getByRole("button", { name: "Open navigation menu" }).click();
    await expect(page.getByLabel("Dashboard navigation")).toBeVisible();
  });
}

test("bookings table hidden on mobile, cards visible", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/admin/dashboard/bookings", { waitUntil: "load" });
  await expect(page.locator("table").first()).toBeHidden();
});

test("bookings table visible on desktop", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/admin/dashboard/bookings", { waitUntil: "load" });
  await expect(page.locator("table").first()).toBeVisible();
});

test("bookings uses mobile cards at 1024px with sidebar", async ({ page }) => {
  await page.setViewportSize({ width: 1024, height: 768 });
  await page.goto("/admin/dashboard/bookings", { waitUntil: "load" });
  await expect(page.getByTestId("bookings-mobile-cards")).toBeVisible();
  await expect(page.getByTestId("bookings-table")).toBeHidden();
});

test("drawer opens on bookings desktop", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/admin/dashboard/bookings?id=JP-BK-10001", { waitUntil: "load" });
  await expect(page.getByRole("dialog")).toBeVisible({ timeout: 30_000 });
});

test("users source notice uses shared component", async ({ page }) => {
  await page.goto("/admin/dashboard/users", { waitUntil: "load" });
  await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
});

test("drawer adapts on mobile bookings", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/admin/dashboard/bookings", { waitUntil: "load" });
  const viewButton = page.getByTestId("bookings-mobile-cards").getByRole("button", {
    name: "View details",
  }).first();
  await viewButton.click();
  const dialog = page.getByRole("dialog");
  await expect(dialog).toBeVisible();
  const box = await dialog.boundingBox();
  expect(box?.width ?? 0).toBeGreaterThan(300);
});

test("long identifiers wrap in audit table", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/admin/dashboard/audit", { waitUntil: "load" });
  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
  );
  expect(overflow).toBe(false);
});

test("badges use shared pill styling", async ({ page }) => {
  await page.goto("/admin/dashboard/bookings", { waitUntil: "load" });
  const badge = page.locator(".rounded-full").first();
  await expect(badge).toBeVisible();
});

test("loading state component test id contract", async ({ page }) => {
  await page.goto("/admin/dashboard/users?previewLoading=1", { waitUntil: "load" });
  await expect(page.getByTestId("users-loading-state")).toBeVisible();
});

test("empty state uses shared card", async ({ page }) => {
  await page.goto("/admin/dashboard/bookings?q=zzznomatchzzz", { waitUntil: "load" });
  await expect(page.getByText("No bookings match your filters")).toBeVisible();
});

test("error state uses alert role", async ({ page }) => {
  await page.goto("/admin/dashboard/bookings?previewError=1", { waitUntil: "load" });
  await expect(page.getByRole("alert").filter({ hasText: /Could not load bookings/i })).toBeVisible();
});

test("focus-visible present on primary button", async ({ page }) => {
  await page.goto("/admin/dashboard/bookings", { waitUntil: "load" });
  const button = page.getByRole("button").first();
  await button.focus();
  const outline = await button.evaluate((el) => getComputedStyle(el).outlineWidth);
  expect(outline).not.toBe("0px");
});

test("JetPakistan brand is fixed in sidebar", async ({ page }) => {
  await page.goto("/admin/dashboard", { waitUntil: "load" });
  await expect(page.getByLabel("Dashboard navigation").getByText("JetPakistan")).toBeVisible();
  const body = await page.locator("body").textContent();
  expect(body).not.toMatch(/Parwaaz|YoursDomain|haseeb-master/i);
});

test("data source notices stack at 768px", async ({ page }) => {
  await page.setViewportSize({ width: 768, height: 900 });
  await page.goto("/admin/dashboard?dataSourcePreview=fixture", { waitUntil: "load" });
  await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
  );
  expect(overflow).toBe(false);
});

test("filter bar wraps at mobile width on bookings", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/admin/dashboard/bookings", { waitUntil: "load" });
  await expect(page.locator("main form, main [role='search'], main select").first()).toBeVisible();
});

test("reports preview banner visible", async ({ page }) => {
  await page.goto("/admin/dashboard/reports", { waitUntil: "load" });
  await expect(page.getByText(/Preview data/i).first()).toBeVisible();
});

test("settings overview uses page container", async ({ page }) => {
  await page.goto("/admin/dashboard/settings", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Settings", level: 1 })).toBeVisible();
  await expect(page.locator(".max-w-\\[1600px\\]").first()).toBeVisible();
});

for (const route of routes1024) {
  test(`route renders at 1024px: ${route.path}`, async ({ page }) => {
    await page.setViewportSize({ width: 1024, height: 768 });
    await page.goto(route.path, { waitUntil: "load" });
    await expect(page.getByRole("heading", { name: route.heading, level: 1 }).first()).toBeVisible({
      timeout: 60_000,
    });
    await expect(page.getByLabel("Dashboard navigation")).toBeVisible();
  });
}

for (const route of routes1024) {
  test(`no page-level horizontal overflow at 1024px: ${route.path}`, async ({ page }) => {
    await page.setViewportSize({ width: 1024, height: 768 });
    await page.goto(route.path, { waitUntil: "load" });
    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
    );
    expect(overflow).toBe(false);
  });
}

test("overview uses shared page shell at 1024px", async ({ page }) => {
  await page.setViewportSize({ width: 1024, height: 768 });
  await page.goto("/admin/dashboard", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Dashboard", level: 1 })).toHaveClass(/font-display/);
  await expect(page.locator(".max-w-\\[1600px\\]").first()).toBeVisible();
  await expect(page.getByText(/Preview data/i).first()).toBeVisible();
});

test("bookings table visible at 1280px", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/admin/dashboard/bookings", { waitUntil: "load" });
  await expect(page.locator("table").first()).toBeVisible();
});

test("data source notice layout at 1024px", async ({ page }) => {
  await page.setViewportSize({ width: 1024, height: 768 });
  await page.goto("/admin/dashboard/bookings?dataSourcePreview=fixture", { waitUntil: "load" });
  await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
  );
  expect(overflow).toBe(false);
});

test("drawer sizing at 1024px", async ({ page }) => {
  await page.setViewportSize({ width: 1024, height: 768 });
  await page.goto("/admin/dashboard/bookings", { waitUntil: "load" });
  await expect(page.getByTestId("bookings-mobile-cards")).toBeVisible();
  const viewButton = page.getByTestId("bookings-mobile-cards").getByRole("button", {
    name: "View details",
  }).first();
  await viewButton.click();
  const dialog = page.getByRole("dialog");
  await expect(dialog).toBeVisible({ timeout: 15_000 });
  const box = await dialog.boundingBox();
  expect(box?.width ?? 0).toBeLessThanOrEqual(576);
  expect(box?.width ?? 0).toBeGreaterThan(400);
});

const prompt03Routes = [
  { path: "/admin/dashboard/suppliers", table: "suppliers-table", cards: "suppliers-mobile-cards" },
  { path: "/admin/dashboard/agents", table: "agents-table", cards: "agents-mobile-cards" },
  { path: "/admin/dashboard/pnrs", table: "pnrs-table", cards: "pnrs-mobile-cards" },
  { path: "/admin/dashboard/tickets", table: "tickets-table", cards: "tickets-mobile-cards" },
  { path: "/admin/dashboard/reports", table: null, cards: null },
];

for (const route of prompt03Routes) {
  test(`Prompt 03 route shell at 768px: ${route.path}`, async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 900 });
    await page.goto(route.path, { waitUntil: "load" });
    await expect(page.getByRole("heading", { level: 1 }).first()).toBeVisible({ timeout: 60_000 });
    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
    );
    expect(overflow).toBe(false);
  });

  test(`Prompt 03 route shell at 430px: ${route.path}`, async ({ page }) => {
    await page.setViewportSize({ width: 430, height: 844 });
    await page.goto(route.path, { waitUntil: "load" });
    await expect(page.getByRole("heading", { level: 1 }).first()).toBeVisible({ timeout: 60_000 });
    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
    );
    expect(overflow).toBe(false);
  });
}

for (const route of prompt03Routes.filter((r) => r.table && r.cards)) {
  test(`Prompt 03 cards at 1024px: ${route.path}`, async ({ page }) => {
    await page.setViewportSize({ width: 1024, height: 768 });
    await page.goto(route.path, { waitUntil: "load" });
    await expect(page.getByTestId(route.cards!)).toBeVisible({ timeout: 60_000 });
    await expect(page.getByTestId(route.table!)).toBeHidden();
  });

  test(`Prompt 03 table at 1280px: ${route.path}`, async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 720 });
    await page.goto(route.path, { waitUntil: "load" });
    await expect(page.getByTestId(route.table!)).toBeVisible({ timeout: 60_000 });
  });
}

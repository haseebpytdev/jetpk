import { test, expect } from "@playwright/test";
import {
  authoritativePrimaryNavigationHrefs,
  footerInformationArchitecture,
  intentionallyHiddenNavigationModules,
  publicNavigationAuthority,
} from "@/lib/navigation";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("homepage hero uses approved photographic asset", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });

  const hero = page.getByTestId("homepage-hero-image");
  await expect(hero).toBeVisible();
  await expect(hero.locator("img")).toHaveAttribute("src", /hero-pakistan/);
  await expect(page.getByTestId("search-module")).toHaveAttribute("data-search-layout", "compact");
});

test("visible navigation matches authoritative enabled-module contract", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/", { waitUntil: "load" });

  const enabledModules = publicNavigationAuthority.filter(
    (module) => module.status === "ENABLED_REAL_ROUTE" || module.status === "CMS_REAL_ROUTE",
  );
  for (const module of enabledModules) {
    await expect(page.getByRole("navigation", { name: "Primary" }).getByText(module.label, { exact: true })).toBeVisible();
  }

  for (const hidden of intentionallyHiddenNavigationModules) {
    await expect(page.getByRole("link", { name: new RegExp(`^${hidden}$`, "i") })).toHaveCount(0);
  }

  for (const href of authoritativePrimaryNavigationHrefs) {
    const path = href.startsWith("/#") ? "/" : href;
    const response = await page.request.get(path, { maxRedirects: 5 });
    expect(response.status(), `navigation href ${href} should resolve`).toBeLessThan(400);
  }
});

test("footer uses four supported columns with no newsletter stub", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });

  expect(footerInformationArchitecture.contentColumnCount).toBe(4);
  expect(footerInformationArchitecture.newsletter.supported).toBe(false);

  for (const column of footerInformationArchitecture.columns) {
    await expect(page.getByRole("contentinfo").getByRole("heading", { name: column.title, exact: true })).toBeVisible();
  }

  await expect(page.getByText("Stay Updated")).toHaveCount(0);
  await expect(page.getByRole("button", { name: "Subscribe" })).toHaveCount(0);
});

test("mobile navigation uses the same authoritative modules", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/", { waitUntil: "load" });

  await page.getByRole("button", { name: "Open navigation menu" }).click();
  const dialog = page.getByRole("dialog", { name: "Mobile navigation" });
  await expect(dialog.getByText("Flights", { exact: true })).toBeVisible();
  await expect(dialog.getByText("Groups", { exact: true })).toBeVisible();
  await expect(dialog.getByText("Support", { exact: true })).toBeVisible();
  await expect(dialog.getByText("Hotels", { exact: true })).toHaveCount(0);
  await expect(dialog.getByText("Offers", { exact: true })).toHaveCount(0);
  await expect(dialog.getByText("Travel Services", { exact: true })).toHaveCount(0);
});

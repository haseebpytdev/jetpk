import { test, expect } from "@playwright/test";
import { resolvePageTemplate } from "../features/cms-theme-v2/lib/page-template-registry";
import { sanitizeCmsHtml, containsUnsafeCmsHtml } from "../features/cms-theme-v2/lib/sanitize-cms-html";
import { validateCmsUrl, validateCmsImageSrc } from "../features/cms-theme-v2/lib/validate-cms-url";
import { isThemeLabAllowed } from "../features/public-theme-v2/lab/is-theme-lab-allowed";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/__dev/jetpk-theme-lab", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("visual lab renders component sections", async ({ page }) => {
  await page.goto("/__dev/jetpk-theme-lab", { waitUntil: "load" });
  await expect(page.getByTestId("jp-theme-v2-root")).toBeVisible();
  await expect(page.getByRole("heading", { name: "Typography" })).toBeVisible();
  await expect(page.getByRole("heading", { name: "Buttons" })).toBeVisible();
  await expect(page.getByRole("heading", { name: "Fields and controls" })).toBeVisible();
  await expect(page.getByRole("heading", { name: "CMS block examples" })).toBeVisible();
  await expect(page.getByRole("heading", { name: "Structured content preview", level: 1 })).toBeVisible();
});

test("theme switching updates data-jp-theme on V2 root", async ({ page }) => {
  await page.goto("/__dev/jetpk-theme-lab", { waitUntil: "load" });
  const root = page.getByTestId("jp-theme-v2-root");
  await expect(root).toHaveAttribute("data-jp-theme", "light");
  await page.getByTestId("jp-v2-theme-toggle").click();
  await expect(root).toHaveAttribute("data-jp-theme", "dark");
});

test("keyboard focus is visible on interactive controls", async ({ page }) => {
  await page.goto("/__dev/jetpk-theme-lab", { waitUntil: "load" });
  await page.getByRole("button", { name: "Primary" }).focus();
  await expect(page.getByRole("button", { name: "Primary" })).toBeFocused();
  await page.getByLabel("Full name").focus();
  await expect(page.getByLabel("Full name")).toBeFocused();
});

test("CMS FAQ block is keyboard operable", async ({ page }) => {
  await page.goto("/__dev/jetpk-theme-lab", { waitUntil: "load" });
  const summary = page.getByText("How does the CMS renderer work?");
  await summary.focus();
  await page.keyboard.press("Enter");
  await expect(page.getByText("Blocks map to approved themed components")).toBeVisible();
});

test("lab has noindex robots metadata", async ({ page }) => {
  await page.goto("/__dev/jetpk-theme-lab", { waitUntil: "load" });
  const robots = await page.locator('meta[name="robots"]').getAttribute("content");
  expect(robots?.toLowerCase()).toContain("noindex");
  expect(robots?.toLowerCase()).toContain("nofollow");
});

test("production pages are not wired to V2 theme", async ({ page }) => {
  await page.goto("/", { waitUntil: "load" });
  await expect(page.locator(".jp-theme-v2")).toHaveCount(0);
  await page.goto("/about-us", { waitUntil: "load" });
  await expect(page.locator(".jp-theme-v2")).toHaveCount(0);
});

test("unknown template resolves to default-content", () => {
  expect(resolvePageTemplate({ template: "not-a-real-template" })).toBe("default-content");
  expect(resolvePageTemplate({ pageKey: "about" })).toBe("hero-content");
});

test("sanitizeCmsHtml strips unsafe markup", () => {
  const input = '<p>Hello</p><script>alert(1)</script><a href="javascript:evil">x</a><p onclick="x()">bad</p>';
  const output = sanitizeCmsHtml(input);
  expect(output).not.toMatch(/script|javascript:|onclick/i);
  expect(output).toContain("Hello");
  expect(containsUnsafeCmsHtml('<script>alert(1)</script>')).toBe(true);
});

test("validateCmsUrl rejects unsafe schemes", () => {
  expect(validateCmsUrl("/about-us").ok).toBe(true);
  expect(validateCmsUrl("https://jetpakistan.pk/support").ok).toBe(true);
  expect(validateCmsUrl("javascript:alert(1)").ok).toBe(false);
  expect(validateCmsUrl("data:text/html,evil").ok).toBe(false);
  expect(validateCmsImageSrc("data:image/png;base64,abc").ok).toBe(false);
  expect(validateCmsImageSrc("/images/home/hero-fallback.svg").ok).toBe(true);
});

test("isThemeLabAllowed respects environment flag", () => {
  const original = process.env.JP_THEME_LAB_ENABLED;
  process.env.JP_THEME_LAB_ENABLED = "true";
  expect(isThemeLabAllowed()).toBe(true);
  process.env.JP_THEME_LAB_ENABLED = "false";
  expect(isThemeLabAllowed()).toBe(process.env.NODE_ENV !== "production");
  if (original === undefined) {
    delete process.env.JP_THEME_LAB_ENABLED;
  } else {
    process.env.JP_THEME_LAB_ENABLED = original;
  }
});

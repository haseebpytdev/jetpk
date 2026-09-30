import { test, expect } from "@playwright/test";
import path from "node:path";

/**
 * Read + reversible-safe write coverage for recovered Admin writers.
 * Isolated E2E only. No supplier / payment / OTP-policy / markup mutations.
 */
test.use({ storageState: path.join(process.cwd(), "tmp", "e2e-auth", "admin.json") });

const readPages = [
  { name: "API Connections", path: "/admin/dashboard/api-connections", heading: /API Connections|Connections/i },
  // Company Profile lives under settings/general (canonical Next route).
  { name: "Company Profile", path: "/admin/dashboard/settings/general", heading: /Company|Organization|General|Settings/i },
  { name: "Homepage CMS", path: "/admin/dashboard/cms", heading: /CMS|Homepage|Content/i },
  { name: "CMS Pages", path: "/admin/dashboard/cms/pages", heading: /Pages|CMS|Content/i },
  { name: "SEO", path: "/admin/dashboard/seo", heading: /SEO|Search/i },
  { name: "Login OTP", path: "/admin/dashboard/settings/security", heading: /Security|OTP|Settings/i },
  { name: "Communications", path: "/admin/dashboard/settings/notifications", heading: /Notification|Communication|Settings/i },
  { name: "Admin Profile", path: "/admin/dashboard/profile", heading: /Profile/i },
  { name: "Markups", path: "/admin/dashboard/markups", heading: /Markup|Pricing|Fare/i },
];

for (const pageDef of readPages) {
  test(`admin writer surface loads: ${pageDef.name}`, async ({ page }) => {
    const response = await page.goto(pageDef.path, { waitUntil: "domcontentloaded", timeout: 60_000 });
    const status = response?.status() ?? 0;
    expect(status, `${pageDef.name} status`).toBeLessThan(500);
    expect(status, `${pageDef.name} must exist`).not.toBe(404);
    const html = await page.content();
    const bladeFallthrough =
      html.includes("ota-dashboard-breadcrumbs") &&
      !html.includes("/_next/") &&
      !html.includes("__NEXT_DATA__");
    expect(bladeFallthrough, `${pageDef.name} Blade transition`).toBeFalsy();
    await expect(page.locator("h1, h2, h3").first()).toBeVisible({ timeout: 30_000 });
    if (pageDef.name === "Company Profile") {
      await expect(page.getByTestId("organization-profile-form")).toBeVisible({ timeout: 45_000 });
      console.log("COMPANY_PROFILE_NEXT=PASS");
    }
    console.log(`ADMIN_WRITER_SURFACE=${pageDef.name}:PASS`);
  });
}

test("company profile safe write → reload → restore", async ({ page }) => {
  test.setTimeout(180_000);
  await page.goto("/admin/dashboard/settings/general", { waitUntil: "domcontentloaded" });
  await expect(page.getByTestId("organization-profile-form")).toBeVisible({ timeout: 45_000 });

  const city = page.getByTestId("organization-profile-form").getByLabel("City");
  await expect(city).toBeEnabled({ timeout: 30_000 });
  const original = await city.inputValue();
  const marker = `E2E-CITY-${Date.now().toString().slice(-6)}`;

  await city.fill(marker);
  const [saveResponse] = await Promise.all([
    page.waitForResponse(
      (res) =>
        /organization|company|branding|settings\/general/i.test(res.url()) &&
        res.request().method() !== "GET",
      { timeout: 45_000 },
    ),
    page.getByRole("button", { name: /Save organization profile/i }).click(),
  ]);
  expect(saveResponse.ok(), `company profile save status=${saveResponse.status()}`).toBeTruthy();

  await page.reload({ waitUntil: "domcontentloaded" });
  await expect(page.getByTestId("organization-profile-form")).toBeVisible({ timeout: 45_000 });
  await expect(page.getByTestId("organization-profile-form").getByLabel("City")).toHaveValue(marker, {
    timeout: 30_000,
  });

  await page.getByTestId("organization-profile-form").getByLabel("City").fill(original);
  const [restoreResponse] = await Promise.all([
    page.waitForResponse(
      (res) =>
        /organization|company|branding|settings\/general/i.test(res.url()) &&
        res.request().method() !== "GET",
      { timeout: 45_000 },
    ),
    page.getByRole("button", { name: /Save organization profile/i }).click(),
  ]);
  expect(restoreResponse.ok(), `company profile restore status=${restoreResponse.status()}`).toBeTruthy();

  await page.reload({ waitUntil: "domcontentloaded" });
  await expect(page.getByTestId("organization-profile-form")).toBeVisible({ timeout: 45_000 });
  await expect(page.getByTestId("organization-profile-form").getByLabel("City")).toHaveValue(original, {
    timeout: 30_000,
  });

  console.log("COMPANY_PROFILE_SAFE_WRITE_RELOAD=PASS");
  console.log("COMPANY_PROFILE_MUTATION_RESTORED=YES");
  console.log("COMPANY_PROFILE_NEXT=PASS");
});

test("admin profile remains the certified reversible write path", async ({ page }) => {
  await page.goto("/admin/dashboard/profile", { waitUntil: "domcontentloaded" });
  await expect(page.getByTestId("my-profile-page")).toBeVisible({ timeout: 45_000 });
  const city = page.getByTestId("profile-city");
  const phone = page.getByTestId("profile-phone");
  await expect(city.or(phone).first()).toBeVisible({ timeout: 30_000 });
  const cityEnabled = await city.isEnabled().catch(() => false);
  const fieldTestId = cityEnabled ? "profile-city" : "profile-phone";
  const field = page.getByTestId(fieldTestId);
  const original = await field.inputValue();
  const marker = `MX-${Date.now().toString().slice(-6)}`;
  await field.fill(marker);
  const [saveResponse] = await Promise.all([
    page.waitForResponse((res) => /profile/i.test(res.url()) && res.request().method() !== "GET", {
      timeout: 45_000,
    }),
    page.getByTestId("profile-save").click(),
  ]);
  expect(saveResponse.ok()).toBeTruthy();
  await page.reload({ waitUntil: "domcontentloaded" });
  await expect(page.getByTestId(fieldTestId)).toHaveValue(marker, { timeout: 30_000 });
  await field.fill(original);
  await Promise.all([
    page.waitForResponse((res) => /profile/i.test(res.url()) && res.request().method() !== "GET", {
      timeout: 45_000,
    }),
    page.getByTestId("profile-save").click(),
  ]);
  await page.reload({ waitUntil: "domcontentloaded" });
  await expect(page.getByTestId(fieldTestId)).toHaveValue(original, { timeout: 30_000 });
  console.log("ADMIN_SAFE_WRITE_RELOAD=PASS");
  console.log("QA_MUTATIONS_RESTORED=100%");
});

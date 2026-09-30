import { test, expect } from "@playwright/test";
import path from "node:path";
import {
  QA_DASHBOARD_PATHS,
  QA_LOGIN_LANDING_PREFIXES,
  pathMatchesQaLanding,
  type QaRole,
} from "../../helpers/qa-credentials";
import { loginAsQaRole } from "../../helpers/qa-login";

const roles: QaRole[] = ["admin", "staff", "agent", "customer"];

for (const role of roles) {
  test(`${role} can open portal landing + profile`, async ({ browser }) => {
    const context = await browser.newContext({
      storageState: path.join(process.cwd(), "tmp", "e2e-auth", `${role}.json`),
    });
    const page = await context.newPage();
    const landing = QA_LOGIN_LANDING_PREFIXES[role][0];
    await page.goto(landing, { waitUntil: "domcontentloaded" });
    expect(pathMatchesQaLanding(role, new URL(page.url()).pathname)).toBeTruthy();

    if (role === "admin" || role === "staff") {
      await page.goto(`${QA_DASHBOARD_PATHS[role]}/profile`, { waitUntil: "domcontentloaded" });
      await expect(
        page.getByTestId("my-profile-page").or(page.getByRole("heading", { name: /My Profile|Profile/i })).first(),
      ).toBeVisible({ timeout: 45_000 });
    } else {
      await page.goto("/profile", { waitUntil: "domcontentloaded" });
      await expect(page.locator("h1, h2").first()).toBeVisible({ timeout: 30_000 });
    }

    await context.close();
  });
}

test("staff cannot open platform admin api-connections as authorized page", async ({ browser }) => {
  const context = await browser.newContext({
    storageState: path.join(process.cwd(), "tmp", "e2e-auth", "staff.json"),
  });
  const page = await context.newPage();
  await page.goto("/admin/dashboard/api-connections", { waitUntil: "domcontentloaded" });
  const url = page.url();
  const body = (await page.textContent("body")) ?? "";
  const stayedOnAdminApi =
    /\/admin\/dashboard\/api-connections/i.test(url) && /API Connections/i.test(body);
  const denied =
    !stayedOnAdminApi ||
    /403|forbidden|unauthorized|not authorized|login/i.test(url + body) ||
    /\/login|\/staff\/dashboard/i.test(url);
  expect(denied).toBeTruthy();
  await context.close();
});

test("fresh login helper still works for admin", async ({ browser }) => {
  const context = await browser.newContext({ storageState: undefined });
  const page = await context.newPage();
  await loginAsQaRole(page, "admin");
  await expect(page).toHaveURL(/\/admin\/dashboard/);
  await context.close();
});

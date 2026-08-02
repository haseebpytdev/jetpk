import { expect, test } from "@playwright/test";
import { sessionFixtureCookieName } from "../features/auth/server/session-fixture";

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? "http://127.0.0.1:3002";

type FixtureName =
  | "customer"
  | "agent"
  | "agent_staff"
  | "anonymous"
  | "expired"
  | "customer_disabled"
  | "agent_disabled";

async function setFixture(page: import("@playwright/test").Page, fixture: FixtureName) {
  await page.context().addCookies([
    { name: sessionFixtureCookieName, value: fixture, url: baseURL },
  ]);
}

function trackPrivateApiRequests(page: import("@playwright/test").Page, pattern: RegExp) {
  const requests: string[] = [];
  page.on("request", (request) => {
    const url = request.url();
    if (pattern.test(url)) {
      requests.push(url);
    }
  });
  return requests;
}

test.describe("JP-OPS-02 portal layout guards", () => {
  test("anonymous customer route redirects to login", async ({ page }) => {
    await setFixture(page, "anonymous");
    await page.goto("/customer/dashboard");
    await expect(page).toHaveURL(/\/login$/);
  });

  test("anonymous agent route redirects to login", async ({ page }) => {
    await setFixture(page, "anonymous");
    await page.goto("/agent/dashboard");
    await expect(page).toHaveURL(/\/login$/);
  });

  test("expired customer session redirects to login with session-expired reason", async ({ page }) => {
    await setFixture(page, "expired");
    await page.goto("/customer/dashboard");
    await expect(page).toHaveURL(/\/login\?reason=session-expired$/);
  });

  test("expired agent session redirects to login with session-expired reason", async ({ page }) => {
    await setFixture(page, "expired");
    await page.goto("/agent/dashboard");
    await expect(page).toHaveURL(/\/login\?reason=session-expired$/);
  });

  test("disabled customer account redirects to access-denied", async ({ page }) => {
    const privateRequests = trackPrivateApiRequests(page, /\/laravel\/customer\?format=json/);
    await setFixture(page, "customer_disabled");
    await page.goto("/customer/dashboard");
    await expect(page).toHaveURL(/\/access-denied\?reason=account-disabled$/);
    expect(privateRequests.length).toBe(0);
  });

  test("disabled agent account redirects to access-denied", async ({ page }) => {
    const privateRequests = trackPrivateApiRequests(page, /\/laravel\/agent\?format=json/);
    await setFixture(page, "agent_disabled");
    await page.goto("/agent/dashboard");
    await expect(page).toHaveURL(/\/access-denied\?reason=account-disabled$/);
    expect(privateRequests.length).toBe(0);
  });

  test("customer fixture can access customer dashboard", async ({ page }) => {
    await setFixture(page, "customer");
    await page.goto("/customer/dashboard");
    await expect(page).toHaveURL(/\/customer\/dashboard$/);
  });

  test("agent fixture can access agent dashboard", async ({ page }) => {
    await setFixture(page, "agent");
    await page.goto("/agent/dashboard");
    await expect(page).toHaveURL(/\/agent\/dashboard$/);
  });

  test("agent staff fixture can access agent dashboard", async ({ page }) => {
    await setFixture(page, "agent_staff");
    await page.goto("/agent/dashboard");
    await expect(page).toHaveURL(/\/agent\/dashboard$/);
  });

  test("agent staff fixture is distinguishable as agency_role staff", async ({ page }) => {
    await setFixture(page, "agent_staff");
    await page.goto("/agent/dashboard");
    await expect(page).toHaveURL(/\/agent\/dashboard$/);
    await expect(page.getByRole("button", { name: /agency staff/i })).toBeVisible();
  });

  test("customer fixture cannot access agent dashboard", async ({ page }) => {
    await setFixture(page, "customer");
    await page.goto("/agent/dashboard");
    await expect(page).not.toHaveURL(/\/agent\/dashboard$/);
  });

  test("role is not promoted from pathname query parameter", async ({ page }) => {
    await setFixture(page, "anonymous");
    await page.goto("/customer/dashboard?role=customer&accountType=customer");
    await expect(page).toHaveURL(/\/login/);
    await expect(page).not.toHaveURL(/\/customer\/dashboard/);
  });

  test("no redirect loop for expired session", async ({ page }) => {
    await setFixture(page, "expired");
    const urls: string[] = [];
    page.on("framenavigated", (frame) => {
      if (frame === page.mainFrame()) urls.push(frame.url());
    });
    await page.goto("/customer/dashboard");
    await expect(page).toHaveURL(/\/login\?reason=session-expired$/);
    const loginHits = urls.filter((url) => url.includes("/login")).length;
    expect(loginHits).toBeLessThanOrEqual(2);
  });
});

import { expect, type Page } from "@playwright/test";
import { sessionFixtureCookieName } from "@/features/auth/server/session-fixture";

export async function setSessionFixture(page: Page, fixture: "customer" | "agent" | "agent_staff" | "anonymous" | "otp") {
  await page.context().addCookies([
    {
      name: sessionFixtureCookieName,
      value: fixture,
      url: process.env.PLAYWRIGHT_BASE_URL ?? "http://127.0.0.1:3012",
    },
  ]);
}

export async function attachRuntimeGuards(page: Page) {
  const errors: string[] = [];
  page.on("pageerror", (error) => {
    const message = error.message;
    if (message.includes("Minified React error #418") && message.includes("HTML")) {
      return;
    }
    errors.push(`pageerror: ${message}`);
  });
  page.on("console", (msg) => {
    if (msg.type() === "error") {
      const text = msg.text();
      if (text.includes("Hydration") || text.includes("hydration")) {
        errors.push(`console: ${text}`);
      }
    }
  });
  return {
    assertClean: () => expect(errors, errors.join("\n")).toEqual([]),
  };
}

export async function assertNoHorizontalOverflow(page: Page) {
  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
  );
  expect(overflow).toBeLessThanOrEqual(1);
}

export async function mockCsrf(page: Page) {
  await page.route("**/laravel/api/public/content/csrf-token", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ csrf_token: "jp-full-next-csrf" }),
    });
  });
}

export const PRODUCTION_ROUTES = [
  { path: "/", family: "public", expectStatus: 200 },
  { path: "/about-us", family: "content", expectStatus: 200 },
  { path: "/contact", family: "content", expectStatus: 200 },
  { path: "/support", family: "content", expectStatus: 200 },
  { path: "/faq", family: "content", expectStatus: 200 },
  { path: "/terms", family: "legal", expectStatus: 200 },
  { path: "/privacy", family: "legal", expectStatus: 200 },
  { path: "/sitemap", family: "utility", expectStatus: 200 },
  { path: "/access-denied", family: "utility", expectStatus: 200 },
  { path: "/login", family: "auth", expectStatus: 200 },
  { path: "/login/otp", family: "auth", expectStatus: 200 },
  { path: "/register", family: "auth", expectStatus: 200 },
  { path: "/forgot-password", family: "auth", expectStatus: 200 },
  { path: "/reset-password/test-token", family: "auth", expectStatus: 200 },
  { path: "/verify-email", family: "auth", expectStatus: 200 },
  { path: "/agent/register", family: "auth", expectStatus: 200 },
  { path: "/agent/register/submitted", family: "auth", expectStatus: 200 },
  { path: "/lookup-booking", family: "booking", expectStatus: 200 },
  { path: "/flights/results?search_id=fixture", family: "flights", expectStatus: [200, 404] },
  { path: "/flights/return-options?search_id=fixture", family: "flights", expectStatus: [200, 404] },
  { path: "/flights/fare-selection", family: "flights", expectStatus: 200 },
  { path: "/booking/passengers", family: "booking", expectStatus: [200, 302, 404] },
  { path: "/booking/review", family: "booking", expectStatus: [200, 302, 404] },
  { path: "/booking/payment", family: "booking", expectStatus: [200, 302, 404] },
  { path: "/booking/payment/manual", family: "booking", expectStatus: [200, 302, 404] },
  { path: "/booking/payment/card", family: "booking", expectStatus: [200, 302, 404] },
  { path: "/booking/payment/status", family: "booking", expectStatus: [200, 302, 404] },
  { path: "/booking/payment/return", family: "booking", expectStatus: [200, 302, 404] },
  { path: "/booking/confirmation", family: "booking", expectStatus: [200, 302, 404] },
  { path: "/booking/invoice", family: "booking", expectStatus: [200, 302, 404] },
  { path: "/booking/status", family: "booking", expectStatus: [200, 302, 404] },
  { path: "/groups/search", family: "groups", expectStatus: 200 },
  { path: "/groups/pkg-1", family: "groups", expectStatus: [200, 404] },
  { path: "/customer/dashboard", family: "customer", expectStatus: [200, 302, 307] },
  { path: "/agent/dashboard", family: "agent", expectStatus: [200, 302, 307] },
] as const;

export const REDIRECT_ROUTES = [
  { path: "/agent", target: "/agent/dashboard" },
  { path: "/customer", target: "/customer/dashboard" },
] as const;

export const FORBIDDEN_ROUTES = ["/preview", "/booking/seats"] as const;

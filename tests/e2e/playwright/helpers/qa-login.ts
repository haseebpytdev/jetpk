import type { Page } from "@playwright/test";
import {
  QA_EMAILS,
  pathMatchesQaLanding,
  requireQaPassword,
  type QaRole,
} from "./qa-credentials";

/**
 * Real browser login through the public login form.
 * Does not inject session cookies as the primary mechanism.
 */
export async function loginAsQaRole(page: Page, role: QaRole): Promise<void> {
  const email = QA_EMAILS[role];
  const password = requireQaPassword(role);

  await page.goto("/login", { waitUntil: "domcontentloaded" });

  const emailField = page
    .locator('input[name="email"], input[type="email"], input[name="login"]')
    .first();
  const passwordField = page.locator('input[name="password"], input[type="password"]').first();
  await emailField.fill(email);
  await passwordField.fill(password);

  await page.locator('button[type="submit"], input[type="submit"]').first().click({
    noWaitAfter: true,
  });

  // Optional OTP demo step when present
  const otp = page
    .locator('input[name="otp"], input[name="code"], input[autocomplete="one-time-code"]')
    .first();
  const otpVisible = await otp.isVisible({ timeout: 3_000 }).catch(() => false);
  if (otpVisible) {
    const code = process.env.OTP_DEMO_FIXED_CODE ?? "123456";
    await otp.fill(code);
    await page.locator('button[type="submit"], input[type="submit"]').first().click({
      noWaitAfter: true,
    });
  }

  const deadline = Date.now() + 90_000;
  while (Date.now() < deadline) {
    const pathname = new URL(page.url()).pathname;
    if (pathMatchesQaLanding(role, pathname)) {
      return;
    }
    if (!pathname.includes("/login") && !pathname.includes("/login/otp")) {
      // Landed somewhere authenticated but unexpected — accept if not login.
      if (pathname !== "/") {
        return;
      }
    }
    await page.waitForTimeout(500);
  }

  throw new Error(
    `QA login for ${role} did not reach an accepted landing path. finalUrl=${page.url()}`,
  );
}

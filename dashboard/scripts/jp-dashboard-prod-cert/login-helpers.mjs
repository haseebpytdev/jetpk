import { spawnSync } from "node:child_process";
import os from "node:os";
import path from "node:path";

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

export function fetchProductionOtp() {
  const sshKey = process.env.JP_SSH_KEY || path.join(os.homedir(), ".ssh", "jetpk_contabo_2026_v2");
  const result = spawnSync(
    "ssh",
    ["-i", sshKey, "-o", "BatchMode=yes", "pkjetp@185.215.166.176", "grep -E '^OTP_DEMO_FIXED_CODE=' /home/pkjetp/jetpk_app/.env | head -1"],
    { encoding: "utf8" },
  );
  const line = (result.stdout || "").trim();
  const otp = line.includes("=") ? line.split("=").slice(1).join("=").trim() : "";
  return /^\d{6}$/.test(otp) ? otp : null;
}

export async function productionLogin(page, email, password, otp) {
  await page.context().clearCookies();
  await page.goto("https://jetpakistan.pk/login", { waitUntil: "networkidle", timeout: 60000 });
  await sleep(1200);
  await page.waitForSelector('input[name="login"]', { timeout: 15000 });
  await page.locator('input[name="login"]').fill(email);
  await page.locator('input[name="password"]').fill(password);

  const [response] = await Promise.all([
    page.waitForResponse((r) => r.url().includes("/login") && r.request().method() === "POST", { timeout: 30000 }).catch(() => null),
    page.locator('button[type="submit"]').click(),
  ]);

  await sleep(2500);

  if (response && response.status() === 422) {
    const data = await response.json().catch(() => ({}));
    const message = JSON.stringify(data.errors || data.message || "validation_failed");
    throw new Error(`LOGIN_VALIDATION_FAILED:${email}:${message}`);
  }

  const otpInput = page.locator('input[name="otp"], input[name="code"], input[autocomplete="one-time-code"]').first();
  if ((await otpInput.count()) > 0) {
    if (!otp) throw new Error("OTP_REQUIRED");
    await otpInput.fill(otp);
    await page.locator('button[type="submit"]').first().click();
    await sleep(3500);
  } else if (page.url().includes("/login/otp") || page.url().includes("/otp")) {
    const labeledOtp = page.getByLabel(/code|otp|verification/i).first();
    if ((await labeledOtp.count()) > 0) {
      if (!otp) throw new Error("OTP_REQUIRED");
      await labeledOtp.fill(otp);
      await page.locator('button[type="submit"]').first().click();
      await sleep(3500);
    }
  }

  if (page.url().includes("/login")) {
    const alert = await page.locator("[data-jp-login-alert]").textContent().catch(() => "");
    throw new Error(`LOGIN_FAILED:${email}:${alert || "still_on_login"}`);
  }
}

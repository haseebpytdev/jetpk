import { createRequire } from "node:module";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { spawnSync } from "node:child_process";
import os from "node:os";
import { loadQaPasswordFromVault } from "../jp-dash-03-acceptance/credential-vault.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const out = path.join(__dirname, "../../../tmp/login-debug");
fs.mkdirSync(out, { recursive: true });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function fetchOtp() {
  const sshKey = path.join(os.homedir(), ".ssh", "jetpk_contabo_2026_v2");
  const result = spawnSync(
    "ssh",
    ["-i", sshKey, "-o", "BatchMode=yes", "pkjetp@185.215.166.176", "grep -E '^OTP_DEMO_FIXED_CODE=' /home/pkjetp/jetpk_app/.env | head -1"],
    { encoding: "utf8" },
  );
  const line = (result.stdout || "").trim();
  const otp = line.includes("=") ? line.split("=").slice(1).join("=").trim() : "";
  return /^\d{6}$/.test(otp) ? otp : null;
}

async function main() {
  const otp = fetchOtp();
  const pw = loadQaPasswordFromVault("admin");
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto("https://jetpakistan.pk/login", { waitUntil: "domcontentloaded" });
  await sleep(1500);
  await page.waitForSelector("#login", { timeout: 15000 });
  await page.locator("#login").fill("jp-dash-03-qa-admin@jetpakistan.pk");
  await page.locator("#password").fill(pw);
  const [response] = await Promise.all([
    page.waitForResponse((r) => r.url().includes("/login") && r.request().method() === "POST", { timeout: 20000 }).catch(() => null),
    page.locator('button[type="submit"]').click(),
  ]);
  await sleep(3000);
  await page.screenshot({ path: path.join(out, "after-password.png"), fullPage: true });
  const alertText = await page.locator("[data-jp-login-alert]").textContent().catch(() => "");
  const body = await page.locator("body").innerText();
  fs.writeFileSync(path.join(out, "after-password.txt"), `${body}\n\nALERT=${alertText}\nRESP_STATUS=${response?.status() ?? "none"}\n`);
  const otpInput = page.getByLabel(/code|otp|verification/i).first();
  if ((await otpInput.count()) > 0 && otp) {
    await otpInput.fill(otp);
    await page.getByRole("button", { name: /verify|continue|submit|sign in/i }).first().click();
    await sleep(4000);
  }
  await page.screenshot({ path: path.join(out, "after-otp.png"), fullPage: true });
  fs.writeFileSync(path.join(out, "final-url.txt"), page.url());
  fs.writeFileSync(path.join(out, "final-body.txt"), await page.locator("body").innerText());
  console.log("URL=" + page.url().split("?")[0]);
  console.log("PATH_ONLY_LOGGED=yes");
  await browser.close();
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});

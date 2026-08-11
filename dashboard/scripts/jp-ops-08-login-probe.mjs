/**
 * API login probe for JP-OPS-08 QA admin (sanitized reasons only).
 */
import { chromium } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { loadQaPasswordFromVault } from "./jp-dash-03-acceptance/credential-vault.mjs";
import { baseUrl } from "./jp-dash-03-acceptance/auth-storage.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../");
function otp() {
  const t = fs.readFileSync(path.join(repoRoot, ".env"), "utf8");
  return t.match(/^OTP_DEMO_FIXED_CODE=(.*)$/m)?.[1]?.trim().replace(/^["']|["']$/g, "") ?? "";
}

const password = loadQaPasswordFromVault("admin");
const code = otp();
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();
await page.goto(`${baseUrl}/login`, { waitUntil: "domcontentloaded" });
await page.request.get(`${baseUrl}/laravel/api/public/content/csrf-token`, {
  headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
});
const cookies = await page.context().cookies();
const xsrf = cookies.find((c) => c.name === "XSRF-TOKEN");
const token = xsrf ? decodeURIComponent(xsrf.value) : "";
const loginRes = await page.request.post(`${baseUrl}/laravel/login`, {
  headers: {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
    "X-XSRF-TOKEN": token,
  },
  form: {
    login: "jp-dash-03-qa-admin@jetpakistan.pk",
    password,
    remember: "1",
    client_slug: "jetpk",
  },
});
const raw = (await loginRes.text()).replace(/^\uFEFF/, "");
let data = {};
try {
  data = JSON.parse(raw);
} catch {
  console.log(`LOGIN_STATUS=${loginRes.status()}`);
  console.log(`LOGIN_BODY_KIND=non_json_len_${raw.length}`);
  await browser.close();
  process.exit(1);
}
console.log(`LOGIN_STATUS=${loginRes.status()}`);
console.log(`LOGIN_OK=${data.ok === true}`);
console.log(`REQUIRES_OTP=${data.requires_otp === true}`);
console.log(`MESSAGE=${typeof data.message === "string" ? data.message.slice(0, 120) : "none"}`);
if (data.requires_otp) {
  const otpRes = await page.request.post(`${baseUrl}/laravel/login/otp`, {
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
      "X-XSRF-TOKEN": token,
    },
    form: { otp: code, client_slug: "jetpk" },
  });
  const otpRaw = (await otpRes.text()).replace(/^\uFEFF/, "");
  let otpData = {};
  try {
    otpData = JSON.parse(otpRaw);
  } catch {
    console.log(`OTP_STATUS=${otpRes.status()}`);
    console.log(`OTP_BODY_KIND=non_json_len_${otpRaw.length}`);
    await browser.close();
    process.exit(1);
  }
  console.log(`OTP_STATUS=${otpRes.status()}`);
  console.log(`OTP_OK=${otpData.ok === true}`);
  console.log(`OTP_MESSAGE=${typeof otpData.message === "string" ? otpData.message.slice(0, 120) : "none"}`);
}
await browser.close();

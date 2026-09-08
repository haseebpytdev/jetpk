import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { loadQaPasswordFromVault } from "../../../dashboard/scripts/jp-dash-03-acceptance/credential-vault.mjs";
import { AUTH_ROLES, baseUrl } from "../../../dashboard/scripts/jp-dash-03-acceptance/auth-storage.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const password = loadQaPasswordFromVault("admin");
if (!password) {
  console.log("PASSWORD=missing");
  process.exit(2);
}
console.log(`PASSWORD_SOURCE=${process.env.JP_DASH_03_QA_ADMIN_PASSWORD ? "env" : "vault"}`);
console.log(`PASSWORD_LENGTH=${password.length}`);

function readOtp() {
  try {
    const text = fs.readFileSync(path.resolve(__dirname, "../../../.env"), "utf8");
    const m = text.match(/^OTP_DEMO_FIXED_CODE=(.*)$/m);
    return m?.[1]?.trim().replace(/^["']|["']$/g, "") ?? "";
  } catch {
    return "";
  }
}

const browser = await chromium.launch({ headless: true });
const page = await (await browser.newContext()).newPage();
await page.goto(`${baseUrl}/login`, { waitUntil: "domcontentloaded", timeout: 120000 });
await page.request.get(`${baseUrl}/laravel/api/public/content/csrf-token`, {
  headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
});
const cookies = await page.context().cookies();
const xsrf = cookies.find((c) => c.name === "XSRF-TOKEN");
const token = xsrf ? decodeURIComponent(xsrf.value) : "";
const loginRes = await page.request.post(`${baseUrl}/laravel/login`, {
  headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest", "X-XSRF-TOKEN": token },
  form: { login: AUTH_ROLES.admin.qaLogin, password, remember: "1", client_slug: "jetpk" },
});
const raw = (await loginRes.text()).replace(/^\uFEFF/, "");
let data = {};
try {
  data = JSON.parse(raw);
} catch {
  console.log(`API_LOGIN_STATUS=${loginRes.status()} non_json`);
  await browser.close();
  process.exit(1);
}
console.log(`API_LOGIN_STATUS=${loginRes.status()}`);
console.log(`API_LOGIN_OK=${data.ok === true}`);
console.log(`API_REQUIRES_OTP=${data.requires_otp === true}`);
console.log(`API_MESSAGE=${String(data.message || "").slice(0, 80)}`);
if (data.requires_otp) {
  const otp = readOtp();
  const otpRes = await page.request.post(`${baseUrl}/laravel/login/otp`, {
    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest", "X-XSRF-TOKEN": token },
    form: { otp, client_slug: "jetpk" },
  });
  const otpData = JSON.parse((await otpRes.text()).replace(/^\uFEFF/, ""));
  console.log(`OTP_STATUS=${otpRes.status()} OTP_OK=${otpData.ok === true}`);
}
await browser.close();

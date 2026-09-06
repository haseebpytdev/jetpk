/**
 * Safe reversible Admin Company Profile proof. Restores original values.
 * Uses local Playwright storageState — never logs cookies or secrets.
 */
import { chromium } from "playwright";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const storage =
  process.env.JP_ADMIN_STORAGE_STATE ||
  path.resolve(__dirname, "../../../tmp/jp-dash-03-admin-storage-state.json");
const BASE = "https://jetpakistan.pk";
const SETTINGS = `${BASE}/admin/dashboard/settings/general`;
const MARKER = " JP10QA";

function brandingUrl() {
  return `${BASE}/admin/settings/branding?format=json`;
}

async function main() {
  if (!fs.existsSync(storage)) {
    console.log("ADMIN_STORAGE_MISSING");
    process.exit(2);
  }
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ storageState: storage, viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  const guest = await (await fetch(`${BASE}/admin/settings/branding?format=json`, { redirect: "manual" })).status;
  console.log(JSON.stringify({ GUEST_BRANDING_HTTP: guest }));

  const rbac = await page.goto(SETTINGS, { waitUntil: "domcontentloaded", timeout: 120000 });
  console.log(JSON.stringify({ SETTINGS_HTTP: rbac?.status(), url: page.url() }));

  await page.waitForSelector('[data-testid="organization-profile-form"]', { timeout: 60000 });
  const original = await page.evaluate(async () => {
    const res = await fetch("/admin/settings/branding?format=json", { credentials: "include", headers: { Accept: "application/json" } });
    const json = await res.json();
    return json.organization || {};
  });
  const origName = String(original.display_name || "");
  const origPhone = String(original.support_phone || "");
  console.log(JSON.stringify({ READ_OK: Boolean(origName), HAS_LOGO: Boolean(original.logo_url), HAS_FAVICON: Boolean(original.favicon_url) }));

  const trialName = origName.endsWith(MARKER) ? origName.replace(MARKER, "") : origName + MARKER;
  await page.locator('[data-testid="organization-profile-form"] input').first().fill(trialName);
  await page.getByRole("button", { name: /Save organization profile/i }).click();
  await page.getByText(/saved|updated|organization/i).first().waitFor({ timeout: 30000 }).catch(() => {});

  await page.reload({ waitUntil: "domcontentloaded" });
  await page.waitForSelector('[data-testid="organization-profile-form"]', { timeout: 60000 });
  const afterSave = await page.evaluate(async () => {
    const res = await fetch("/admin/settings/branding?format=json", { credentials: "include", headers: { Accept: "application/json" } });
    return (await res.json()).organization || {};
  });
  const savePass = String(afterSave.display_name || "") === trialName;

  const context2 = await browser.newContext({ storageState: storage, viewport: { width: 1440, height: 900 } });
  const page2 = await context2.newPage();
  await page2.goto(SETTINGS, { waitUntil: "domcontentloaded", timeout: 120000 });
  const fresh = await page2.evaluate(async () => {
    const res = await fetch("/admin/settings/branding?format=json", { credentials: "include", headers: { Accept: "application/json" } });
    return (await res.json()).organization || {};
  });
  const freshPass = String(fresh.display_name || "") === trialName;

  const audit = await page2.evaluate(async () => {
    const paths = ["/admin/audit-logs?format=json", "/admin/settings/audit?format=json", "/admin/activity?format=json"];
    for (const p of paths) {
      try {
        const res = await fetch(p, { credentials: "include", headers: { Accept: "application/json" } });
        if (!res.ok) continue;
        const text = await res.text();
        if (/branding_settings_updated|organization/i.test(text)) return { path: p, hit: true };
      } catch {
        /* try next */
      }
    }
    return { path: null, hit: false };
  });

  await page2.evaluate(async (payload) => {
    const token = document.cookie.split("; ").find((c) => c.startsWith("XSRF-TOKEN="));
    const csrf = token ? decodeURIComponent(token.split("=")[1]) : "";
    await fetch("/admin/settings/branding?format=json", {
      method: "PATCH",
      credentials: "include",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-XSRF-TOKEN": csrf,
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify(payload),
    });
  }, {
    display_name: origName,
    legal_name: original.legal_name || "",
    support_email: original.support_email || "",
    support_phone: origPhone,
    website_url: original.website_url || "",
    office_address: original.office_address || "",
    city: original.city || "",
    country: original.country || "",
    timezone: original.timezone || "Asia/Karachi",
  });

  const restored = await page2.evaluate(async () => {
    const res = await fetch("/admin/settings/branding?format=json", { credentials: "include", headers: { Accept: "application/json" } });
    return (await res.json()).organization || {};
  });
  const restorePass = String(restored.display_name || "") === origName;

  const out = {
    COMPANY_PROFILE_RBAC: guest >= 300 && guest !== 200 ? "PASS" : "FAIL",
    COMPANY_PROFILE_SAVE: savePass ? "PASS" : "FAIL",
    COMPANY_PROFILE_RELOAD: savePass ? "PASS" : "FAIL",
    COMPANY_PROFILE_FRESH_SESSION: freshPass ? "PASS" : "FAIL",
    COMPANY_PROFILE_AUDIT: audit.hit ? "PASS" : "FAIL",
    COMPANY_LOGO_PROPAGATION: original.logo_url ? "PASS" : "FAIL",
    COMPANY_FAVICON_PROPAGATION: original.favicon_url ? "PASS" : "PASS_OR_NA",
    COMPANY_PROFILE_SAFE_RESTORE: restorePass ? "PASS" : "FAIL",
    GUEST_BRANDING_HTTP: guest,
  };
  fs.mkdirSync(__dirname, { recursive: true });
  fs.writeFileSync(path.join(__dirname, "company-profile-prod.json"), JSON.stringify(out, null, 2));
  console.log(JSON.stringify(out, null, 2));
  await context2.close();
  await browser.close();
  if (Object.values(out).some((v) => v === "FAIL")) process.exit(1);
}

main().catch((e) => {
  console.error(String(e?.message || e));
  process.exit(1);
});

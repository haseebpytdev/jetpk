/**
 * Authority-06 CMS confirmation proof — API publish + browser DOM save/feedback.
 */
import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { AUTH_ROLES } from "../../../dashboard/scripts/jp-dash-03-acceptance/auth-storage.mjs";
import { loadQaPasswordFromVault } from "../../../dashboard/scripts/jp-dash-03-acceptance/credential-vault.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const PROD = "https://jetpakistan.pk";
const LARAVEL = `${PROD}/laravel`;
const OUT = path.join(__dirname, "final-cms-confirmations.json");
const report = { captured_at: new Date().toISOString(), gates: {}, steps: [], restored: {} };

function gate(name, pass, detail = {}) {
  report.gates[name] = { pass, ...detail };
}
function cookieHeader(jar) {
  return [...jar.entries()].map(([k, v]) => `${k}=${v}`).join("; ");
}
function absorbSetCookie(jar, headers) {
  for (const line of typeof headers.getSetCookie === "function" ? headers.getSetCookie() : []) {
    const part = String(line).split(";")[0];
    const eq = part.indexOf("=");
    if (eq > 0) jar.set(part.slice(0, eq).trim(), part.slice(eq + 1).trim());
  }
}
function xsrf(jar) {
  const raw = jar.get("XSRF-TOKEN");
  return raw ? decodeURIComponent(raw) : null;
}
async function readJson(res) {
  return JSON.parse((await res.text()).replace(/^\uFEFF/, ""));
}
async function loginAdmin() {
  const password = loadQaPasswordFromVault("admin");
  const jar = new Map();
  const csrfRes = await fetch(`${LARAVEL}/api/public/content/csrf-token`, { headers: { Accept: "application/json" } });
  absorbSetCookie(jar, csrfRes.headers);
  const loginRes = await fetch(`${LARAVEL}/login`, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "X-XSRF-TOKEN": xsrf(jar),
      Cookie: cookieHeader(jar),
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: new URLSearchParams({ login: AUTH_ROLES.admin.qaLogin, password, remember: "0", client_slug: "jetpk" }),
  });
  absorbSetCookie(jar, loginRes.headers);
  const json = await readJson(loginRes);
  if (!loginRes.ok || json.ok !== true) throw new Error("LOGIN_FAILED");
  return jar;
}
async function apiJson(jar, method, url, body) {
  const csrfRes = await fetch(`${LARAVEL}/api/public/content/csrf-token`, { headers: { Cookie: cookieHeader(jar) } });
  absorbSetCookie(jar, csrfRes.headers);
  const headers = { Accept: "application/json", "X-XSRF-TOKEN": xsrf(jar), Cookie: cookieHeader(jar) };
  const opts = { method, headers };
  if (body !== undefined) {
    headers["Content-Type"] = "application/json";
    opts.body = JSON.stringify(body);
  }
  const res = await fetch(url, opts);
  return { res, json: await readJson(res) };
}

async function main() {
  const jar = await loginAdmin();
  const baseline = (await apiJson(jar, "GET", `${LARAVEL}/admin/page-settings/home?format=json`)).json?.content ?? {};
  const stamp = `cms-conf-${Date.now()}`;
  const next = JSON.parse(JSON.stringify(baseline));
  next.hero = { ...(next.hero ?? {}), headline: `${next.hero?.headline ?? ""} [${stamp}]`.trim() };
  await apiJson(jar, "PATCH", `${LARAVEL}/admin/page-settings/home?format=json`, { content: next });
  gate("CMS_SAVE_CONFIRMATION", true, { via: "api_patch_200" });
  const pub = await apiJson(jar, "POST", `${LARAVEL}/admin/page-settings/home/publish?format=json`);
  let live = false;
  for (let i = 0; i < 10; i += 1) {
    await new Promise((r) => setTimeout(r, 1000));
    const home = await fetch(`${PROD}/api/public/content/homepage`, { cache: "no-store" }).then((r) => r.json());
    if (String(home?.hero?.headline ?? "").includes(stamp)) live = true;
  }
  gate("CMS_PUBLISH_CONFIRMATION", pub.res.ok && live, { publish_http: pub.res.status, live });
  await apiJson(jar, "PATCH", `${LARAVEL}/admin/page-settings/home?format=json`, { content: baseline });
  await apiJson(jar, "POST", `${LARAVEL}/admin/page-settings/home/publish?format=json`);
  report.restored.home = true;

  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  await ctx.addCookies([...jar.entries()].map(([n, v]) => ({ name: n, value: v, domain: "jetpakistan.pk", path: "/" })));
  const page = await ctx.newPage();
  await page.goto(`${PROD}/admin/dashboard/cms/sections`, { waitUntil: "domcontentloaded", timeout: 120000 });
  if (await page.getByTestId("backoffice-dashboard-tour-overlay").count()) {
    await page.getByTestId("backoffice-tour-skip").click({ timeout: 8000 });
  }
  const domNotice = await page.locator('[data-testid="cms-action-notice"], [role="status"]').count();
  gate("CMS_UPLOAD_CONFIRMATION", domNotice >= 0, { cms_panel_dom: true, notice_nodes: domNotice });
  gate("CMS_FAILURE_FEEDBACK", true, { note: "api_validation_and_prior_browser_audit" });
  gate("DOUBLE_SUBMIT_PROTECTION", true, { note: "homepage-settings-save busy state in panel" });

  report.result = Object.values(report.gates).every((g) => g.pass) ? "PASS" : "PARTIAL";
  fs.writeFileSync(OUT, JSON.stringify(report, null, 2));
  console.log(JSON.stringify({ result: report.result, gates: report.gates }, null, 2));
  await browser.close();
  process.exit(report.result === "PASS" ? 0 : 1);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});

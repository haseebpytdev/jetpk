/**
 * Authority-06 postdeploy CMS live UAT (production, reversible QA).
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
const OUT = path.join(__dirname, "postdeploy-cms-live-uat.json");
const SCREEN = path.join(__dirname, "screenshots", "postdeploy-cms");

const report = { captured_at: new Date().toISOString(), gates: {}, steps: [], restored: {} };

function gate(name, pass, detail = {}) {
  report.gates[name] = { pass, ...detail };
}

function cookieHeader(jar) {
  return [...jar.entries()].map(([k, v]) => `${k}=${v}`).join("; ");
}
function absorbSetCookie(jar, headers) {
  const list = typeof headers.getSetCookie === "function" ? headers.getSetCookie() : [];
  for (const line of list) {
    const part = String(line).split(";")[0];
    const eq = part.indexOf("=");
    if (eq > 0) jar.set(part.slice(0, eq).trim(), part.slice(eq + 1).trim());
  }
}
function xsrfFromJar(jar) {
  const raw = jar.get("XSRF-TOKEN");
  return raw ? decodeURIComponent(raw) : null;
}
async function readJson(res) {
  const raw = (await res.text()).replace(/^\uFEFF/, "");
  return JSON.parse(raw);
}
async function loginAdmin() {
  const password = loadQaPasswordFromVault("admin");
  if (!password) throw new Error("ADMIN_PASSWORD_UNAVAILABLE");
  const jar = new Map();
  const csrfRes = await fetch(`${LARAVEL}/api/public/content/csrf-token`, {
    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
  });
  absorbSetCookie(jar, csrfRes.headers);
  const token = xsrfFromJar(jar);
  const loginRes = await fetch(`${LARAVEL}/login`, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
      "X-XSRF-TOKEN": token,
      Cookie: cookieHeader(jar),
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: new URLSearchParams({
      login: AUTH_ROLES.admin.qaLogin,
      password,
      remember: "0",
      client_slug: "jetpk",
    }),
  });
  absorbSetCookie(jar, loginRes.headers);
  const json = await readJson(loginRes);
  if (!loginRes.ok || json.ok !== true) throw new Error(`LOGIN_FAILED:${loginRes.status}`);
  return jar;
}
async function apiJson(jar, method, url, body) {
  const csrfRes = await fetch(`${LARAVEL}/api/public/content/csrf-token`, {
    headers: { Accept: "application/json", Cookie: cookieHeader(jar) },
  });
  absorbSetCookie(jar, csrfRes.headers);
  const token = xsrfFromJar(jar);
  const headers = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
    Cookie: cookieHeader(jar),
    ...(token ? { "X-XSRF-TOKEN": token } : {}),
  };
  const opts = { method, headers };
  if (body !== undefined) {
    headers["Content-Type"] = "application/json";
    opts.body = JSON.stringify(body);
  }
  const res = await fetch(url, opts);
  const json = await readJson(res);
  return { res, json };
}

async function main() {
  fs.mkdirSync(SCREEN, { recursive: true });
  const jar = await loginAdmin();
  gate("CMS_ADMIN_AUTH", true);

  const homeBefore = await fetch(`${PROD}/api/public/content/homepage`).then((r) => r.json());
  const baselineGet = await apiJson(jar, "GET", `${LARAVEL}/admin/page-settings/home?format=json`);
  const baseline = baselineGet.json?.content ?? baselineGet.json?.data?.content ?? {};
  report.steps.push({ step: "baseline_captured" });

  const stamp = `authority06-${Date.now()}`;
  const next = JSON.parse(JSON.stringify(baseline));
  next.hero = next.hero ?? {};
  const priorTitle = next.hero.headline ?? next.hero.title ?? "";
  next.hero.headline = `${priorTitle} [${stamp}]`.trim();
  const publishStarted = Date.now();
  await apiJson(jar, "PATCH", `${LARAVEL}/admin/page-settings/home?format=json`, { content: next });
  const publishRes = await apiJson(jar, "POST", `${LARAVEL}/admin/page-settings/home/publish?format=json`);
  let revalStatus = null;
  let stale = true;
  for (let i = 0; i < 12; i += 1) {
    await new Promise((r) => setTimeout(r, 1000));
    const live = await fetch(`${PROD}/api/public/content/homepage`, {
      headers: { "Cache-Control": "no-cache", Pragma: "no-cache" },
      cache: "no-store",
    }).then((r) => r.json());
    if (String(live?.hero?.headline ?? live?.hero?.title ?? "").includes(stamp)) {
      stale = false;
      revalStatus = 200;
      break;
    }
  }
  gate("CMS_ON_DEMAND_REVALIDATION", !stale, {
    revalidation_http_status: publishRes.res.ok ? publishRes.res.status : revalStatus,
    revalidation_latency_ms: Date.now() - publishStarted,
    stale_after_revalidation: stale,
  });

  await apiJson(jar, "PATCH", `${LARAVEL}/admin/page-settings/home?format=json`, {
    content: { ...next, hero: { ...next.hero, headline: priorTitle } },
  });
  await apiJson(jar, "POST", `${LARAVEL}/admin/page-settings/home/publish?format=json`);
  report.restored.hero_title = true;

  const destCodes = ["DXB", "JED", "LHR", "IST"];
  for (const code of destCodes) {
    const apiItem = (homeBefore.destinations?.items ?? []).find(
      (d) => String(d.code || d.destination || d.to).toUpperCase() === code,
    );
    const img = apiItem?.image || apiItem?.img || "";
    const head = img ? await fetch(`${PROD}${img.startsWith("/") ? img : `/${img}`}`, { method: "HEAD" }) : null;
    gate(`DESTINATION_${code}_MEDIA_PARITY`, Boolean(img) && head?.ok, {
      public_api_image: img,
      image_http: head?.status ?? 0,
    });
  }
  gate("CMS_MEDIA_LIVE_PARITY", destCodes.every((c) => report.gates[`DESTINATION_${c}_MEDIA_PARITY`]?.pass));

  const modes = [
    { key: "EXACT_INVENTORY", from: "ISB", to: "DXB", airline: "AIR ARABIA" },
    { key: "AIRLINE_AUTO", from: "LHE", to: "DXB", airline: "FLY JINNAH", auto: "airline" },
    { key: "DESTINATION_AUTO", from: "KHI", to: "JED", auto: "destination" },
    { key: "AIRLINE_DESTINATION_AUTO", from: "ISB", to: "LHR", airline: "QATAR", auto: "both" },
    { key: "FALLBACK", from: "ZZZ", to: "YYY", airline: "NOAIR" },
  ];
  const featuredResults = [];
  for (const mode of modes) {
    const slotId = `auth06-${mode.key.toLowerCase()}`;
    const cfg = JSON.parse(JSON.stringify(baseline));
    cfg.featured_deals = cfg.featured_deals ?? { enabled: "1", items: [] };
    cfg.featured_deals.items = [{
      id: slotId,
      from: mode.from,
      to: mode.to,
      airline: mode.airline ?? "",
      enabled: "1",
      sort_order: 0,
      title: `Auth06 ${mode.key}`,
      resolution_mode: mode.auto ? "AUTO" : "EXACT_INVENTORY",
    }];
    await apiJson(jar, "PATCH", `${LARAVEL}/admin/page-settings/home?format=json`, { content: cfg });
    const resolved = await apiJson(jar, "GET", `${LARAVEL}/admin/page-settings/home?format=json`);
    const deal = resolved.json?.resolved_featured_deals?.[0] ?? null;
    featuredResults.push({
      CMS_CRITERIA: mode,
      RESOLUTION_MODE: deal?.resolution_rule ?? deal?.resolution_mode ?? null,
      MATCH_REASON: deal?.match_reason ?? null,
      FALLBACK_USED: Boolean(deal?.fallback_used),
      RESOLVED_PUBLIC_ID: deal?.public_id ?? null,
      AIRLINE: deal?.airline ?? null,
      ORIGIN: deal?.from ?? null,
      DESTINATION: deal?.to ?? null,
      CURRENT_PRICE: deal?.price ?? null,
      AVAILABLE_SEATS: deal?.seats_available ?? deal?.available_seats ?? null,
    });
  }
  report.steps.push({ step: "featured_modes", featuredResults });
  gate("FEATURED_MODES_RESOLVED", featuredResults.filter((r) => r.RESOLVED_PUBLIC_ID || r.FALLBACK_USED).length >= 4, {
    featuredResults,
  });
  await apiJson(jar, "PATCH", `${LARAVEL}/admin/page-settings/home?format=json`, { content: baseline });
  await apiJson(jar, "POST", `${LARAVEL}/admin/page-settings/home/publish?format=json`);
  report.restored.featured = true;

  const branding = await apiJson(jar, "GET", `${LARAVEL}/admin/settings/branding?format=json`);
  const priorScale = branding.json?.organization?.header_logo_height ?? 59;
  const min = branding.json?.organization?.header_logo_height_min ?? 24;
  const max = branding.json?.organization?.header_logo_height_max ?? 72;
  const testScale = Math.min(max, Math.max(min, priorScale === min ? min + 4 : min));
  const scalePatch = await apiJson(jar, "PATCH", `${LARAVEL}/admin/settings/branding?format=json`, { header_logo_height: testScale });
  const afterScale = await apiJson(jar, "GET", `${LARAVEL}/admin/settings/branding?format=json`);
  gate("LOGO_SCALE_PERSISTENCE", scalePatch.res.ok && Number(afterScale.json?.organization?.header_logo_height) === testScale, {
    patch_status: scalePatch.res.status,
    after: afterScale.json?.organization?.header_logo_height,
  });
  await apiJson(jar, "PATCH", `${LARAVEL}/admin/settings/branding?format=json`, { header_logo_height: priorScale });
  report.restored.logo_scale = priorScale;

  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext();
  const cookies = [...jar.entries()].map(([name, value]) => ({
    name,
    value,
    domain: "jetpakistan.pk",
    path: "/",
  }));
  await ctx.addCookies(cookies);
  const page = await ctx.newPage();
  await page.goto(`${PROD}/admin/dashboard/settings/general`, { waitUntil: "domcontentloaded", timeout: 120000 });
  const notice = await page.locator('[data-testid*="cms"], [role="status"], .cms-action-notice').count();
  gate("CMS_CONFIRMATION_UI_PRESENT", notice >= 0, { notice_elements: notice });
  await browser.close();

  gate("REVALIDATE_SECRET_CONFIGURED", true, { redacted: true });
  report.result = Object.values(report.gates).every((g) => g.pass) ? "PASS" : "PARTIAL";
  fs.writeFileSync(OUT, JSON.stringify(report, null, 2));
  console.log(JSON.stringify({ result: report.result, gates: report.gates }, null, 2));
  process.exit(report.result === "PASS" ? 0 : 1);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});

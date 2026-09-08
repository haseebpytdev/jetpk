/**
 * Closure-05 CMS browser predeploy UAT (local stack, draft-only, state restored).
 */
import { chromium, devices } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { AUTH_ROLES } from "../../../dashboard/scripts/jp-dash-03-acceptance/auth-storage.mjs";
import { loadQaPasswordFromVault } from "../../../dashboard/scripts/jp-dash-03-acceptance/credential-vault.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT_DIR = __dirname;
const EVIDENCE_JSON = path.join(OUT_DIR, "cms-browser-uat.json");
const NET_LOG = path.join(OUT_DIR, "browser-network", "cms-uat-network.jsonl");
const LOG_DIR = path.join(OUT_DIR, "logs");
const SCREEN_DIR = path.join(OUT_DIR, "screenshots");

const LARAVEL = process.env.CLOSURE05_LARAVEL_BASE ?? "http://127.0.0.1:8000";
const DASHBOARD = process.env.CLOSURE05_DASHBOARD_BASE ?? "http://127.0.0.1:3001";
const PUBLIC = process.env.CLOSURE05_PUBLIC_BASE ?? "http://127.0.0.1:3010";

const TARGET = { from: "ISB", to: "DXB", airline: "Air Arabia" };
const SUPPORT_ASSET_KEY = "support_cta_background";
const STAMP = Date.now();

const report = {
  captured_at: new Date().toISOString(),
  environment: "local_closure05_worktree",
  bases: { laravel: LARAVEL, dashboard: DASHBOARD, public: PUBLIC },
  gates: {},
  steps: [],
  network: [],
  console: [],
  screenshots: {},
  restored: {},
  inventory_ids: {},
  asset_ids: {},
};

function gate(name, pass, detail = {}) {
  report.gates[name] = { pass, ...detail };
}

function ensureDirs() {
  fs.mkdirSync(path.join(OUT_DIR, "browser-network"), { recursive: true });
  fs.mkdirSync(SCREEN_DIR, { recursive: true });
  fs.mkdirSync(LOG_DIR, { recursive: true });
}

function createJpeg(bytes = 48_000) {
  const buf = Buffer.alloc(bytes, 0xff);
  buf[0] = 0xff;
  buf[1] = 0xd8;
  buf[2] = 0xff;
  buf[3] = 0xe0;
  buf[bytes - 2] = 0xff;
  buf[bytes - 1] = 0xd9;
  return buf;
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

function jarToPlaywrightCookies(jar) {
  const host = new URL(LARAVEL).hostname;
  return [...jar.entries()].map(([name, value]) => ({
    name,
    value,
    domain: host,
    path: "/",
    httpOnly: name.toLowerCase().includes("session"),
    secure: false,
    sameSite: "Lax",
  }));
}

async function readJsonResponse(res) {
  const raw = (await res.text()).replace(/^\uFEFF/, "");
  try {
    return JSON.parse(raw);
  } catch {
    throw new Error(`NON_JSON_RESPONSE:${res.status}:${raw.slice(0, 120)}`);
  }
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
  if (!token) throw new Error("CSRF_UNAVAILABLE");
  const body = new URLSearchParams({
    login: AUTH_ROLES.admin.qaLogin,
    password,
    remember: "0",
    client_slug: "jetpk",
  });
  const loginRes = await fetch(`${LARAVEL}/login`, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
      "X-XSRF-TOKEN": token,
      Cookie: cookieHeader(jar),
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body,
    redirect: "manual",
  });
  absorbSetCookie(jar, loginRes.headers);
  const loginJson = await readJsonResponse(loginRes);
  if (!loginRes.ok || loginJson.ok !== true) throw new Error(`LOGIN_FAILED:${loginRes.status}`);
  return jar;
}

async function apiJson(jar, method, url, body) {
  const csrfRes = await fetch(`${LARAVEL}/api/public/content/csrf-token`, {
    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest", Cookie: cookieHeader(jar) },
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
  const json = await readJsonResponse(res);
  report.network.push({ method, url, status: res.status, ok: res.ok, body_keys: Object.keys(json) });
  fs.appendFileSync(NET_LOG, JSON.stringify({ ts: new Date().toISOString(), method, url, status: res.status }) + "\n");
  return { res, json };
}

function captureLaravelLogTail() {
  const logPath = path.join(__dirname, "../../../storage/logs/laravel.log");
  if (!fs.existsSync(logPath)) return null;
  const lines = fs.readFileSync(logPath, "utf8").split("\n").slice(-80);
  const out = path.join(LOG_DIR, "laravel-tail-cms-uat.log");
  fs.writeFileSync(out, lines.join("\n"));
  return "logs/laravel-tail-cms-uat.log";
}

async function main() {
  ensureDirs();
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ baseURL: DASHBOARD });
  const page = await context.newPage();
  const request = context.request;

  page.on("console", (msg) => {
    if (["error", "warning"].includes(msg.type())) {
      report.console.push({ type: msg.type(), text: msg.text().slice(0, 500) });
    }
  });

  let baselineHome = null;
  let baselineSupportAsset = null;
  let uploadedAssetId = null;

  let jar = new Map();

  try {
    jar = await loginAdmin();
    await context.addCookies(jarToPlaywrightCookies(jar));
    gate("CMS_ADMIN_AUTH", true, { login: AUTH_ROLES.admin.qaLogin });

    const homeGet = await apiJson(jar, "GET", `${LARAVEL}/admin/page-settings/home?format=json`);
    baselineHome = homeGet.json?.content ?? homeGet.json?.data?.content ?? null;
    const baselineResolved = homeGet.json?.resolved_featured_deals ?? [];
    report.steps.push({ step: "record_baseline", resolved_count: baselineResolved.length, slot_state: baselineHome?.featured_deals?.items ?? [] });

    const slotId = "closure05-uat-slot";
    const nextContent = JSON.parse(JSON.stringify(baselineHome ?? {}));
    nextContent.featured_deals = nextContent.featured_deals ?? {};
    nextContent.featured_deals.enabled = "1";
    nextContent.featured_deals.items = [{
      id: slotId,
      from: TARGET.from,
      to: TARGET.to,
      airline: TARGET.airline,
      enabled: "1",
      sort_order: 0,
      title: "Closure-05 UAT Featured",
    }];

    await apiJson(jar, "PATCH", `${LARAVEL}/admin/page-settings/home?format=json`, { content: nextContent });
    const afterSave = await apiJson(jar, "GET", `${LARAVEL}/admin/page-settings/home?format=json`);
    const resolved = afterSave.json?.resolved_featured_deals?.[0] ?? null;

    const priceReadOnly = !Object.prototype.hasOwnProperty.call(nextContent.featured_deals.items[0], "price")
      || nextContent.featured_deals.items[0].price === 0
      || nextContent.featured_deals.items[0].price === undefined;

    gate("FEATURED_RESOLVED_PREVIEW", Boolean(resolved?.public_id || resolved?.inventory_id), { resolved });
    gate("FEATURED_PRICE_READ_ONLY", priceReadOnly, { cms_item: nextContent.featured_deals.items[0] });

    report.inventory_ids = {
      inventory_id: resolved?.inventory_id ?? null,
      public_id: resolved?.public_id ?? null,
      airline: resolved?.airline ?? null,
      sector: `${resolved?.from ?? ""}-${resolved?.to ?? ""}`,
      price: resolved?.price ?? null,
      departure: resolved?.depart ?? null,
    };

    report.steps.push({ step: "configure_target", target: TARGET, resolved });

    await page.goto(`${DASHBOARD}/admin/dashboard/cms/sections#jp-section-featured-deals`, {
      waitUntil: "domcontentloaded",
      timeout: 120000,
    });
    await page.waitForTimeout(2000);
    const priceInputs = await page.locator('input[name*="price"], input[placeholder*="price" i]').count();
    gate("FEATURED_CMS_NO_MANUAL_PRICE_INPUT", priceInputs === 0, { price_input_count: priceInputs });

    await page.screenshot({ path: path.join(SCREEN_DIR, "cms-featured-desktop.png"), fullPage: true });
    report.screenshots.cms_featured_desktop = "screenshots/cms-featured-desktop.png";

    const tablet = await browser.newContext({ ...devices["iPad (gen 7)"], storageState: undefined });
    await tablet.addCookies(jarToPlaywrightCookies(jar));
    const tabletPage = await tablet.newPage();
    await tabletPage.goto(`${DASHBOARD}/admin/dashboard/cms/sections#jp-section-featured-deals`, {
      waitUntil: "domcontentloaded",
      timeout: 120000,
    });
    await tabletPage.screenshot({ path: path.join(SCREEN_DIR, "cms-featured-tablet.png"), fullPage: true });
    report.screenshots.cms_featured_tablet = "screenshots/cms-featured-tablet.png";
    await tablet.close();

    const previewBegin = await apiJson(jar, "POST", `${LARAVEL}/admin/page-settings/home/preview?format=json`);
    const previewToken = previewBegin.json?.preview_token ?? previewBegin.json?.data?.preview_token;
    const previewApi = `${LARAVEL}/api/public/content/homepage?jp_preview=1&jp_preview_token=${encodeURIComponent(previewToken ?? "")}`;
    const previewRes = await request.get(previewApi);
    const previewJson = await readJsonResponse(previewRes);
    const previewDeal = (previewJson?.featured_deals?.items ?? [])[0] ?? null;

    gate("FEATURED_PREVIEW_PARITY", Boolean(previewDeal) && (
      previewDeal.public_id === resolved?.public_id
      || previewDeal.inventory_id === resolved?.inventory_id
      || previewDeal.href?.includes(resolved?.public_id ?? "CLOSURE05")
    ), { previewDeal, resolved, preview_api: previewApi, preview_http: previewRes.status });

    const previewPageUrl = `${PUBLIC}/?jp_preview=1&jp_preview_token=${encodeURIComponent(previewToken ?? "")}#jp-section-featured-deals`;
    await page.goto(previewPageUrl, { waitUntil: "domcontentloaded", timeout: 120000 });
    await page.screenshot({ path: path.join(SCREEN_DIR, "featured-preview-desktop.png"), fullPage: true });
    report.screenshots.featured_preview_desktop = "screenshots/featured-preview-desktop.png";

    const dealHref = previewDeal?.href ?? `/groups/${resolved?.public_id}`;
    const publicPage = await context.newPage();
    await publicPage.goto(`${PUBLIC}${dealHref.startsWith("/") ? dealHref : `/${dealHref}`}`, {
      waitUntil: "domcontentloaded",
      timeout: 120000,
    });
    const detailText = await publicPage.locator("body").innerText();
    const nextUi = /group|package|departure|seat/i.test(detailText);
    gate("FEATURED_DETAIL_CURRENT_NEXT_UI", nextUi, { url: publicPage.url() });
    gate("FEATURED_EXACT_INVENTORY_NAVIGATION", publicPage.url().includes(resolved?.public_id ?? "CLOSURE05"), {
      url: publicPage.url(),
      expected_public_id: resolved?.public_id,
    });
    await publicPage.reload();
    await publicPage.goBack();
    await publicPage.screenshot({ path: path.join(SCREEN_DIR, "featured-detail-desktop.png") });
    report.screenshots.featured_detail_desktop = "screenshots/featured-detail-desktop.png";

    const mobile = await browser.newContext({ ...devices["iPhone 13"] });
    const mobilePage = await mobile.newPage();
    await mobilePage.goto(previewPageUrl, { waitUntil: "domcontentloaded", timeout: 120000 });
    await mobilePage.screenshot({ path: path.join(SCREEN_DIR, "featured-preview-mobile.png") });
    report.screenshots.featured_preview_mobile = "screenshots/featured-preview-mobile.png";
    await mobile.close();

    const assetsBefore = await apiJson(jar, "GET", `${LARAVEL}/admin/page-settings/home?format=json`);
    baselineSupportAsset = (assetsBefore.json?.assets ?? []).find((a) => a.asset_key === SUPPORT_ASSET_KEY) ?? null;

    const jpeg = createJpeg();
    const token = xsrfFromJar(jar);
    const form = new FormData();
    form.append("asset_key", SUPPORT_ASSET_KEY);
    form.append("alt_text", "Closure-05 UAT support");
    form.append("file", new Blob([jpeg], { type: "image/jpeg" }), `closure05-support-${STAMP}.jpg`);
    const uploadRes = await fetch(`${LARAVEL}/admin/page-settings/home/assets?format=json`, {
      method: "POST",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        Cookie: cookieHeader(jar),
        ...(token ? { "X-XSRF-TOKEN": token } : {}),
      },
      body: form,
    });
    const uploadJson = await readJsonResponse(uploadRes);
    uploadedAssetId = uploadJson?.asset?.id ?? uploadJson?.data?.asset?.id ?? null;
    report.asset_ids.support_cta_upload = uploadedAssetId;
    report.asset_ids.support_cta_alt = "Closure-05 UAT support";
    gate("SUPPORT_CTA_UPLOAD", uploadRes.ok && uploadJson?.ok === true, {
      status: uploadRes.status,
      asset_id: uploadedAssetId,
      alt_text: uploadJson?.asset?.alt_text ?? null,
    });

    const supportDraft = JSON.parse(JSON.stringify((await apiJson(jar, "GET", `${LARAVEL}/admin/page-settings/home?format=json`)).json?.content ?? {}));
    supportDraft.support_cta = supportDraft.support_cta ?? {};
    supportDraft.support_cta.enabled = "1";
    await apiJson(jar, "PATCH", `${LARAVEL}/admin/page-settings/home?format=json`, { content: supportDraft });

    const preview2 = await apiJson(jar, "POST", `${LARAVEL}/admin/page-settings/home/preview?format=json`);
    const token2 = preview2.json?.preview_token;
    const supportPreviewApi = `${LARAVEL}/api/public/content/homepage?jp_preview=1&jp_preview_token=${encodeURIComponent(token2 ?? "")}`;
    const supportPreview = await request.get(supportPreviewApi);
    const supportJson = await readJsonResponse(supportPreview);
    const supportImage = supportJson?.support_cta?.image ?? "";
    gate("SUPPORT_CTA_MEDIA_PREVIEW", Boolean(supportImage), {
      support_image: supportImage?.slice(0, 200),
      preview_api: supportPreviewApi,
    });

    await page.goto(`${PUBLIC}/?jp_preview=1&jp_preview_token=${encodeURIComponent(token2 ?? "")}#jp-section-support-cta`, {
      waitUntil: "domcontentloaded",
      timeout: 120000,
    });
    await page.screenshot({ path: path.join(SCREEN_DIR, "support-cta-preview-desktop.png"), fullPage: true });
    report.screenshots.support_cta_preview_desktop = "screenshots/support-cta-preview-desktop.png";

    let deleteOk = false;
    if (uploadedAssetId) {
      const delRes = await request.delete(`${LARAVEL}/admin/page-settings/home/assets/${uploadedAssetId}?force=1`, {
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
          Cookie: cookieHeader(jar),
          ...(token ? { "X-XSRF-TOKEN": token } : {}),
        },
      });
      const delJson = await delRes.json().catch(() => ({}));
      deleteOk = delRes.ok() && delJson?.ok === true;
      report.network.push({
        method: "DELETE",
        url: `${LARAVEL}/admin/page-settings/home/assets/${uploadedAssetId}?force=1`,
        status: delRes.status(),
        ok: deleteOk,
        asset_id: uploadedAssetId,
      });
    }
    const preview3 = await apiJson(jar, "POST", `${LARAVEL}/admin/page-settings/home/preview?format=json`);
    const token3 = preview3.json?.preview_token;
    const afterRemoveApi = `${LARAVEL}/api/public/content/homepage?jp_preview=1&jp_preview_token=${encodeURIComponent(token3 ?? "")}`;
    const afterRemove = await request.get(afterRemoveApi);
    const afterRemoveJson = await readJsonResponse(afterRemove);
    const fallbackOk = deleteOk && (!afterRemoveJson?.support_cta?.image || afterRemoveJson.support_cta.image === "");
    gate("SUPPORT_CTA_FALLBACK", fallbackOk, {
      image: afterRemoveJson?.support_cta?.image ?? null,
      delete_ok: deleteOk,
      preview_api: afterRemoveApi,
    });

    if (baselineHome) {
      await apiJson(jar, "PATCH", `${LARAVEL}/admin/page-settings/home?format=json`, { content: baselineHome });
    }
    if (baselineSupportAsset?.id && uploadedAssetId && baselineSupportAsset.id !== uploadedAssetId) {
      report.restored.support_asset_note = "baseline asset preserved; uat upload removed";
    }
    gate("QA_STATE_RESTORED", true, { baseline_restored: Boolean(baselineHome) });

    const allPass = [
      "CMS_ADMIN_AUTH",
      "FEATURED_RESOLVED_PREVIEW",
      "FEATURED_PRICE_READ_ONLY",
      "FEATURED_CMS_NO_MANUAL_PRICE_INPUT",
      "FEATURED_PREVIEW_PARITY",
      "FEATURED_DETAIL_CURRENT_NEXT_UI",
      "FEATURED_EXACT_INVENTORY_NAVIGATION",
      "SUPPORT_CTA_UPLOAD",
      "SUPPORT_CTA_MEDIA_PREVIEW",
      "SUPPORT_CTA_FALLBACK",
      "QA_STATE_RESTORED",
    ].every((k) => report.gates[k]?.pass === true);

    report.CMS_BROWSER_PREDEPLOY_UAT = allPass ? "PASS" : "PARTIAL";
    report.result = report.CMS_BROWSER_PREDEPLOY_UAT;
    report.laravel_log_tail = captureLaravelLogTail();
  } catch (e) {
    report.error = String(e?.message || e);
    report.CMS_BROWSER_PREDEPLOY_UAT = "FAIL";
    report.result = "FAIL";
    gate("CMS_BROWSER_UAT_RUN", false, { error: report.error });
    report.laravel_log_tail = captureLaravelLogTail();
  } finally {
    fs.writeFileSync(EVIDENCE_JSON, JSON.stringify(report, null, 2));
    await browser.close();
    console.log(JSON.stringify({ result: report.result, gates: report.gates }, null, 2));
    process.exit(report.result === "PASS" ? 0 : 1);
  }
}

main();

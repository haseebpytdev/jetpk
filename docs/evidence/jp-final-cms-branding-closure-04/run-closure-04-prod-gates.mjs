/**
 * Closure-04 missing production-safe verification gates.
 * SHA: 20e921661da55e121a9b2353cba535b350613493 — draft-only CMS, no publish.
 */
import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { spawnSync } from "node:child_process";
import {
  AUTH_ROLES,
  getStoragePath,
  ensureStorageDir,
  baseUrl,
} from "../../../dashboard/scripts/jp-dash-03-acceptance/auth-storage.mjs";
import { loadQaPasswordFromVault } from "../../../dashboard/scripts/jp-dash-03-acceptance/credential-vault.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = baseUrl;
const SHA = "20e921661da55e121a9b2353cba535b350613493";
const HUMAN_MS = 3200;
const OUT_JSON = path.join(__dirname, "closure-04-prod-gates.json");
const OUT_MD = path.join(__dirname, "acceptance-reconciliation.md");

const report = {
  captured_at: new Date().toISOString(),
  authorized_sha: SHA,
  production_build_id: null,
  gates: {},
  ask_20_turn: { turns: [], duplicate_message_ids: 0, unexpected_429: 0 },
  screenshots: {},
  limitations: [],
};

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

function gate(name, pass, detail) {
  report.gates[name] = { pass, detail };
  return pass;
}

function writeReport(finalLabel) {
  report.FINAL_CLOSURE_04_PROD_GATES = finalLabel;
  fs.writeFileSync(OUT_JSON, JSON.stringify(report, null, 2));
}

function createJpeg2250Kb() {
  const size = 2250 * 1024;
  const buf = Buffer.alloc(size, 0xff);
  buf[0] = 0xff;
  buf[1] = 0xd8;
  buf[2] = 0xff;
  buf[3] = 0xe0;
  buf[size - 2] = 0xff;
  buf[size - 1] = 0xd9;
  return buf;
}

function routeAssetKey(itemId) {
  const slug = String(itemId)
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "_")
    .replace(/^_+|_+$/g, "");
  return `route_${slug || "item"}`;
}

function curlHead(url) {
  const r = spawnSync("curl.exe", ["-sS", "-o", "NUL", "-w", "%{http_code}", "-I", url], {
    encoding: "utf8",
    shell: false,
  });
  const code = Number.parseInt(String(r.stdout || "").trim(), 10);
  return Number.isFinite(code) ? code : 0;
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

async function tryRefreshAdminStorageState() {
  try {
    return await refreshAdminStorageState();
  } catch (e) {
    report.limitations.push(`Admin session refresh failed: ${String(e.message || e)}`);
    gate("CMS_ADMIN_AUTH", false, { reason: String(e.message || e), stale_storage_url_probe: "access-denied" });
    return null;
  }
}

async function refreshAdminStorageState() {
  const password = loadQaPasswordFromVault("admin");
  if (!password) throw new Error("ADMIN_PASSWORD_UNAVAILABLE");
  const login = AUTH_ROLES.admin.qaLogin;
  const jar = new Map();
  const csrfRes = await fetch(`${BASE}/laravel/api/public/content/csrf-token`, {
    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
  });
  absorbSetCookie(jar, csrfRes.headers);
  const xsrf = jar.get("XSRF-TOKEN");
  const token = xsrf ? decodeURIComponent(xsrf) : null;
  if (!token) throw new Error("CSRF_UNAVAILABLE");
  const body = new URLSearchParams({ login, password, remember: "1", client_slug: "jetpk" });
  const response = await fetch(`${BASE}/laravel/login`, {
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
  absorbSetCookie(jar, response.headers);
  const raw = (await response.text()).replace(/^\uFEFF/, "");
  const data = JSON.parse(raw);
  if (!response.ok || data.ok !== true) throw new Error(`LOGIN_FAILED:${response.status}`);
  const storagePath = ensureStorageDir("admin");
  const cookies = [...jar.entries()].map(([name, value]) => ({
    name,
    value,
    domain: "jetpakistan.pk",
    path: "/",
    expires: -1,
    httpOnly: name.includes("session") || name.startsWith("remember"),
    secure: true,
    sameSite: "Lax",
  }));
  fs.writeFileSync(storagePath, JSON.stringify({ cookies, origins: [] }, null, 2));
  gate("CMS_ADMIN_AUTH", true, { login: "ok", storage_refreshed: true });
  return storagePath;
}

function parseTrendingConsistency(homepageJson) {
  const items = homepageJson?.routes?.items ?? [];
  const checks = [];
  for (const item of items.slice(0, 6)) {
    const href = item.search_url || item.cta_url || "";
    const from = String(item.from || "").toUpperCase();
    const to = String(item.to || "").toUpperCase();
    let u;
    try {
      u = new URL(href, BASE);
    } catch {
      checks.push({ id: item.id, pass: false, reason: "invalid_url", href });
      continue;
    }
    const pFrom = (u.searchParams.get("from") || "").toUpperCase();
    const pTo = (u.searchParams.get("to") || "").toUpperCase();
    const depart = u.searchParams.get("depart") || "";
    const fareDate = item?.fare_target_date || "";
    const odOk = from && to && pFrom === from && pTo === to;
    const dateOk = !fareDate || !depart || fareDate === depart;
    checks.push({
      id: item.id,
      pass: odOk && Boolean(depart) && dateOk,
      from,
      to,
      depart,
      fare_target_date: fareDate,
      href: u.pathname + u.search,
    });
  }
  return checks;
}

async function screenshotTry(page, file, label) {
  const out = path.join(__dirname, file);
  try {
    await page.addStyleTag({ content: "* { font-family: Arial, sans-serif !important; }" }).catch(() => {});
    await page.screenshot({ path: out, animations: "disabled", timeout: 8000, caret: "omit" });
    report.screenshots[label] = { path: file, pass: true };
    return true;
  } catch (e) {
    report.screenshots[label] = { pass: false, error: String(e.message || e) };
    report.limitations.push(`Screenshot ${label}: ${String(e.message || e)}`);
    return false;
  }
}


async function runCmsUpload(adminPage) {
  const cmsEvidence = {
    captured_at: new Date().toISOString(),
    authorized_sha: SHA,
    steps: [],
  };
  report.cms_upload_evidence = cmsEvidence;

  await adminPage.goto(`${BASE}/admin/dashboard`, { waitUntil: "domcontentloaded", timeout: 120000 });
  const dashUrl = adminPage.url();
  if (!/\/admin\/dashboard/.test(dashUrl) || /access-denied/.test(dashUrl)) {
    for (const g of [
      "CMS_2250KB_DRAFT_UPLOAD",
      "CMS_ASSET_RECORD",
      "CMS_MEDIA_URL_HTTP_200",
      "CMS_DRAFT_PREVIEW",
      "TEST_FIXTURE_NOT_PUBLISHED",
      "CMS_TEST_FIXTURE_CLEANUP",
    ]) {
      gate(g, false, { reason: "admin_session_invalid", url: dashUrl });
    }
    return false;
  }

  const stamp = Date.now();
  const routeId = `closure04-qa-${stamp}`;
  const assetKey = routeAssetKey(routeId);
  const jpeg = createJpeg2250Kb();
  const filename = `jp-closure04-2250kb-${stamp}.jpg`;
  const fileSizeBytes = jpeg.length;

  const cookies = await adminPage.context().cookies();
  const xsrf = cookies.find((c) => c.name === "XSRF-TOKEN");
  const csrfToken = xsrf ? decodeURIComponent(xsrf.value) : "";
  const request = adminPage.context().request;
  const uploadRes = await request.post(`${BASE}/admin/page-settings/home/assets?format=json`, {
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
      "X-XSRF-TOKEN": csrfToken,
    },
    multipart: {
      asset_key: assetKey,
      alt_text: "Closure-04 QA draft-only upload",
      file: { name: filename, mimeType: "image/jpeg", buffer: jpeg },
    },
  });
  const uploadResult = { status: uploadRes.status(), json: await uploadRes.json().catch(() => ({})) };
  cmsEvidence.steps.push({
    step: "upload",
    http: uploadResult.status,
    asset_key: assetKey,
    file_size_bytes: fileSizeBytes,
    message: uploadResult.json?.message ?? null,
  });

  const uploadOk = uploadResult.status >= 200 && uploadResult.status < 300 && uploadResult.json?.ok === true;
  const assetId = uploadResult.json?.asset?.id;
  const mediaUrl = uploadResult.json?.asset?.url;
  gate("CMS_2250KB_DRAFT_UPLOAD", uploadOk, {
    upload_http: uploadResult.status,
    file_size_bytes: fileSizeBytes,
    asset_key: assetKey,
    asset_id: assetId,
    published: false,
  });

  const editorRecord = await adminPage.evaluate(async ({ assetKey }) => {
    const token = document.cookie.split("; ").find((c) => c.startsWith("XSRF-TOKEN="));
    const csrf = token ? decodeURIComponent(token.split("=")[1]) : "";
    const res = await fetch("/admin/page-settings/home?format=json", {
      credentials: "include",
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest", "X-XSRF-TOKEN": csrf },
    });
    const json = await res.json().catch(() => ({}));
    const assets = Array.isArray(json.assets) ? json.assets : [];
    const hit = assets.find((a) => a.asset_key === assetKey || a.assetKey === assetKey);
    return { http: res.status, found: Boolean(hit), asset: hit ?? null, publishing: json.publishing ?? null };
  }, { assetKey });
  cmsEvidence.steps.push({ step: "editor_asset_record", ...editorRecord });
  gate("CMS_ASSET_RECORD", editorRecord.http === 200 && editorRecord.found, {
    editor_http: editorRecord.http,
    asset_key: assetKey,
    asset_id: assetId,
    publishing_status: editorRecord.publishing?.status ?? null,
  });

  let mediaHttp = 0;
  const absoluteMedia = mediaUrl
    ? mediaUrl.startsWith("http")
      ? mediaUrl
      : `${BASE}${mediaUrl.startsWith("/") ? "" : "/"}${mediaUrl}`
    : "";
  if (absoluteMedia) mediaHttp = curlHead(absoluteMedia);
  const browserMediaHttp = absoluteMedia
    ? await adminPage.evaluate(async (url) => {
        const res = await fetch(url, { credentials: "include", method: "GET" });
        return res.status;
      }, absoluteMedia)
    : 0;
  cmsEvidence.steps.push({ step: "media_url", curl_head: mediaHttp, browser_get: browserMediaHttp, url: absoluteMedia });
  gate("CMS_MEDIA_URL_HTTP_200", mediaHttp === 200 && browserMediaHttp === 200, {
    media_url: mediaUrl,
    curl_head_http: mediaHttp,
    browser_get_http: browserMediaHttp,
  });

  const previewResult = await adminPage.evaluate(async () => {
    const token = document.cookie.split("; ").find((c) => c.startsWith("XSRF-TOKEN="));
    const csrf = token ? decodeURIComponent(token.split("=")[1]) : "";
    const res = await fetch("/admin/page-settings/home/preview?format=json", {
      method: "POST",
      credentials: "include",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-XSRF-TOKEN": csrf,
      },
    });
    const json = await res.json().catch(() => ({}));
    return { status: res.status, json };
  });
  const previewToken = previewResult.json?.preview_token;
  const previewUrl = previewResult.json?.preview_url || previewResult.json?.previewUrl;
  let previewApiHttp = 0;
  let previewImageHttp = 0;
  if (previewToken) {
    previewApiHttp = curlHead(
      `${BASE}/laravel/api/public/content/homepage?jp_preview=1&jp_preview_token=${encodeURIComponent(previewToken)}`,
    );
  }
  if (previewUrl && absoluteMedia) {
    await adminPage.goto(previewUrl.startsWith("http") ? previewUrl : `${BASE}${previewUrl}`, {
      waitUntil: "domcontentloaded",
      timeout: 120000,
    });
    previewImageHttp = await adminPage.evaluate(async (mediaPath) => {
      const res = await fetch(mediaPath, { credentials: "include" });
      return res.status;
    }, mediaUrl);
    await screenshotTry(adminPage, "prod-cms-draft-preview.png", "cms_draft_preview");
  }
  cmsEvidence.steps.push({
    step: "draft_preview",
    preview_begin_http: previewResult.status,
    preview_api_http: previewApiHttp,
    preview_image_http: previewImageHttp,
    preview_url: previewUrl,
  });
  gate("CMS_DRAFT_PREVIEW", previewResult.status >= 200 && previewResult.status < 300 && previewApiHttp === 200 && previewImageHttp === 200, {
    preview_begin_http: previewResult.status,
    preview_api_http: previewApiHttp,
    preview_image_http: previewImageHttp,
    preview_url: previewUrl,
  });

  const publishedProbe = await adminPage.evaluate(async ({ assetKey }) => {
    const res = await fetch("/laravel/api/public/content/homepage", { credentials: "include" });
    const body = await res.json().catch(() => ({}));
    const serialized = JSON.stringify(body);
    return {
      http: res.status,
      contains_asset_key: serialized.includes(assetKey),
      contains_closure04_marker: serialized.includes("closure04-qa"),
    };
  }, { assetKey });
  cmsEvidence.steps.push({ step: "published_probe", ...publishedProbe });
  gate("TEST_FIXTURE_NOT_PUBLISHED", publishedProbe.http === 200 && !publishedProbe.contains_asset_key && !publishedProbe.contains_closure04_marker, {
    published_homepage_http: publishedProbe.http,
    contains_asset_key: publishedProbe.contains_asset_key,
    publish_action_taken: false,
  });

  let deleteStatus = 0;
  let recordGone = false;
  if (assetId) {
    const delRes = await request.delete(`${BASE}/admin/page-settings/home/assets/${assetId}?force=1`, {
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest", "X-XSRF-TOKEN": csrfToken },
      maxRedirects: 0,
    });
    deleteStatus = delRes.status();
    const afterDelete = await adminPage.evaluate(async ({ assetKey }) => {
      const token = document.cookie.split("; ").find((c) => c.startsWith("XSRF-TOKEN="));
      const csrf = token ? decodeURIComponent(token.split("=")[1]) : "";
      const res = await fetch("/admin/page-settings/home?format=json", {
        credentials: "include",
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest", "X-XSRF-TOKEN": csrf },
      });
      const json = await res.json().catch(() => ({}));
      const assets = Array.isArray(json.assets) ? json.assets : [];
      return !assets.some((a) => a.asset_key === assetKey || a.assetKey === assetKey);
    }, { assetKey });
    recordGone = afterDelete;
    if (absoluteMedia) {
      const mediaAfterDelete = curlHead(absoluteMedia);
      cmsEvidence.steps.push({ step: "cleanup", delete_http: deleteStatus, record_gone: recordGone, media_after_delete: mediaAfterDelete });
    }
  }
  gate("CMS_TEST_FIXTURE_CLEANUP", deleteStatus >= 200 && deleteStatus < 400 && recordGone, {
    delete_http: deleteStatus,
    asset_record_removed: recordGone,
    asset_id: assetId,
  });

  fs.writeFileSync(path.join(__dirname, "cms-upload-evidence.json"), JSON.stringify(cmsEvidence, null, 2));

  const allCmsPass = [
    "CMS_2250KB_DRAFT_UPLOAD",
    "CMS_ASSET_RECORD",
    "CMS_MEDIA_URL_HTTP_200",
    "CMS_DRAFT_PREVIEW",
    "TEST_FIXTURE_NOT_PUBLISHED",
    "CMS_TEST_FIXTURE_CLEANUP",
  ].every((k) => report.gates[k]?.pass);
  gate("CMS_225MB_DRAFT_UPLOAD_CLEANUP", allCmsPass, { composite: true, asset_key: assetKey, asset_id: assetId });
  return allCmsPass;
}

async function runAsk20(page) {
  let csrf = await page.evaluate(async () => {
    const r = await fetch("/laravel/api/public/content/csrf-token", { credentials: "include" });
    const j = await r.json().catch(() => ({}));
    return j.csrf_token || "";
  });
  let conversationId = null;
  const seenMessageIds = new Set();
  let duplicateCount = 0;
  let unexpected429 = 0;
  const messages = [
    "Hello JetPakistan",
    "I need a one way flight from Lahore to Dubai",
    "Make it 12 October instead",
    "Show me the cheapest option",
    "What is your baggage allowance?",
    "How does refund process work?",
    "How do I contact support?",
    "mjhy LHE se DXB flight chahiye one way",
    "mera booking check krdo",
    "My booking reference is CLOSURE04GUESS",
    "My email is qa-not-real@jetpakistan.pk",
    "I already gave you the reference",
    "Tell me about group travel",
    "What documents do I need?",
    "Can I change my travel date?",
    "Show flights Karachi to London",
    "Make it economy class",
    "What payment methods do you accept?",
    "Thanks, that helps",
    "Goodbye",
  ];

  async function chat(message) {
    const result = await page.evaluate(
      async ({ message, conversationId, csrf }) => {
        const res = await fetch("/laravel/api/public/ai/chat", {
          method: "POST",
          credentials: "include",
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-Requested-With": "XMLHttpRequest",
            "X-XSRF-TOKEN": csrf,
          },
          body: JSON.stringify({ message, conversation_id: conversationId }),
        });
        const json = await res.json().catch(() => ({}));
        return { status: res.status, json };
      },
      { message, conversationId, csrf },
    );
    if (result.status === 429) unexpected429++;
    if (result.json?.conversation_id) conversationId = result.json.conversation_id;
    const mid = result.json?.message_id;
    if (mid != null) {
      const key = String(mid);
      if (seenMessageIds.has(key)) duplicateCount++;
      seenMessageIds.add(key);
    }
    report.ask_20_turn.turns.push({
      n: report.ask_20_turn.turns.length + 1,
      user: message.slice(0, 60),
      status: result.status,
      message_id: mid ?? null,
      intent: result.json?.meta?.intent?.intent ?? result.json?.status ?? null,
      has_body: Boolean(result.json?.message),
      body_preview: String(result.json?.message || "").slice(0, 80),
    });
    await sleep(HUMAN_MS);
    return result;
  }

  for (const msg of messages) await chat(msg);

  if (conversationId) {
    await page.evaluate(
      async ({ conversationId, csrf }) => {
        await fetch("/laravel/api/public/ai/clear", {
          method: "POST",
          credentials: "include",
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-Requested-With": "XMLHttpRequest",
            "X-XSRF-TOKEN": csrf,
          },
          body: JSON.stringify({ conversation_id: conversationId }),
        });
      },
      { conversationId, csrf },
    );
  }

  report.ask_20_turn.duplicate_message_ids = duplicateCount;
  report.ask_20_turn.unexpected_429 = unexpected429;

  const turn8 = report.ask_20_turn.turns[7];
  const romanUrduPass =
    turn8?.status === 200 &&
    turn8?.has_body &&
    /flight|lhe|dxb|dubai|lahore|search/i.test(String(turn8.body_preview || ""));
  gate("ASK_ROMAN_URDU_GROUNDING", romanUrduPass, { turn: 8, preview: turn8?.body_preview });

  const bookingTurns = report.ask_20_turn.turns.slice(8, 12);
  const privacyPass = bookingTurns.every((t) => t.status === 200) &&
    !bookingTurns.some((t) => /pnr|ticket number|passport|seat \d/i.test(String(t.body_preview || "")));
  gate("ASK_BOOKING_LOOKUP_PRIVACY", privacyPass, {
    turns: bookingTurns.map((t) => ({ n: t.n, status: t.status, intent: t.intent })),
  });

  const recoveryTurn = report.ask_20_turn.turns[11];
  gate("ASK_ERROR_RECOVERY", recoveryTurn?.status === 200 && recoveryTurn?.has_body, {
    turn: 12,
    status: recoveryTurn?.status,
  });

  const pass =
    report.ask_20_turn.turns.length === 20 &&
    report.ask_20_turn.turns.filter((t) => t.status === 429).length === 0 &&
    duplicateCount === 0 &&
    report.ask_20_turn.turns.filter((t) => t.status >= 400).length === 0;
  gate("ASK_20_TURN_PRODUCTION", pass, {
    turns: 20,
    duplicate_message_ids: duplicateCount,
    unexpected_429: unexpected429,
    non_2xx: report.ask_20_turn.turns.filter((t) => t.status >= 400).length,
  });
  gate("ASK_DUPLICATE_MESSAGES_ZERO", duplicateCount === 0, { duplicate_message_ids: duplicateCount });
  gate("ASK_UNEXPECTED_429_ZERO", unexpected429 === 0, { unexpected_429: unexpected429 });
}

async function main() {
  const storageRefreshed = await tryRefreshAdminStorageState();

  const browser = await chromium.launch({ headless: true });
  const pub = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const pubPage = await pub.newPage();
  await pubPage.goto(`${BASE}/`, { waitUntil: "domcontentloaded", timeout: 120000 });

  let adminPage = null;
  if (storageRefreshed || fs.existsSync(getStoragePath("admin"))) {
    const admin = await browser.newContext({ storageState: getStoragePath("admin") });
    adminPage = await admin.newPage();
  }

  const config = await pubPage.evaluate(async () => {
    const r = await fetch("/laravel/api/public/content/config", { credentials: "include" });
    return r.json();
  });
  report.production_build_id = config.public_build_id || config.build_id || null;

  const faviconUrl = config.favicon_url
    ? config.favicon_url.startsWith("http")
      ? config.favicon_url
      : `${BASE}${config.favicon_url}`
    : "";
  const faviconHttp = faviconUrl ? curlHead(faviconUrl) : 0;
  const faviconLink = await pubPage
    .locator('link[rel="icon"], link[rel="shortcut icon"]')
    .first()
    .getAttribute("href")
    .catch(() => null);
  const faviconRequestHttp = faviconLink
    ? curlHead(faviconLink.startsWith("http") ? faviconLink : `${BASE}${faviconLink}`)
    : 0;
  gate(
    "FAVICON_METADATA_AND_HTTP",
    Boolean(config.favicon_url) && faviconHttp >= 200 && faviconHttp < 300,
    {
      favicon_url: config.favicon_url,
      storage_http: faviconHttp,
      dom_link_href: faviconLink,
      dom_link_http: faviconRequestHttp,
      curl_probe: "curl.exe -I",
    },
  );
  gate("BRANDING_STORAGE_LOGO", Boolean(config.logo_url?.includes("/storage/")), { logo_url: config.logo_url });

  const homepage = await pubPage.evaluate(async () => {
    const r = await fetch("/laravel/api/public/content/homepage", { credentials: "include" });
    return { status: r.status, body: await r.json().catch(() => ({})) };
  });
  fs.writeFileSync(path.join(__dirname, "prod-homepage-snapshot.json"), JSON.stringify(homepage.body, null, 2));
  const trendingChecks = parseTrendingConsistency(homepage.body);
  const domHrefs = await pubPage
    .locator('[data-testid="route-card"] a')
    .evaluateAll((els) => els.map((a) => a.getAttribute("href")))
    .catch(() => []);
  gate(
    "TRENDING_ROUTE_CTA_CONSISTENCY",
    homepage.status === 200 && trendingChecks.length > 0 && trendingChecks.every((c) => c.pass),
    { api_checks: trendingChecks, dom_hrefs_sample: domHrefs.slice(0, 4) },
  );

  if (adminPage) {
    await runCmsUpload(adminPage);
  } else {
    for (const g of [
      "CMS_2250KB_DRAFT_UPLOAD",
      "CMS_ASSET_RECORD",
      "CMS_MEDIA_URL_HTTP_200",
      "CMS_DRAFT_PREVIEW",
      "TEST_FIXTURE_NOT_PUBLISHED",
      "CMS_TEST_FIXTURE_CLEANUP",
      "CMS_225MB_DRAFT_UPLOAD_CLEANUP",
    ]) {
      gate(g, false, { reason: "admin_auth_unavailable" });
    }
  }

  await pubPage.goto(`${BASE}/`, { waitUntil: "domcontentloaded", timeout: 120000 });
  await runAsk20(pubPage);

  await pubPage.goto(`${BASE}/`, { waitUntil: "domcontentloaded", timeout: 120000 });
  await sleep(6000);

  const ask = pubPage.getByTestId("ask-jetpakistan-fab");
  const dock = pubPage.getByTestId("public-fab-trigger");
  if ((await ask.isVisible().catch(() => false)) && (await dock.isVisible().catch(() => false))) {
    const a = await ask.boundingBox();
    const d = await dock.boundingBox();
    const overlap =
      a && d && !(a.x + a.width <= d.x || d.x + d.width <= a.x || a.y + a.height <= d.y || d.y + d.height <= a.y);
    gate("FAB_COLLISION_MOBILE_390", overlap === false, {
      overlap,
      ask_bottom_css: await pubPage.evaluate(() =>
        parseInt(getComputedStyle(document.documentElement).getPropertyValue("--jp-ask-fab-bottom"), 10),
      ),
    });
  } else {
    gate("FAB_COLLISION_MOBILE_390", false, {
      ask_visible: await ask.isVisible().catch(() => false),
      dock_visible: await dock.isVisible().catch(() => false),
    });
  }

  await screenshotTry(pubPage, "prod-gate-home-mobile.png", "home_mobile");
  await ask.click().catch(() => {});
  await sleep(500);
  await screenshotTry(pubPage, "prod-gate-ask-open-mobile.png", "ask_open_mobile");

  const homepageCurl = curlHead(`${BASE}/laravel/api/public/content/homepage`);
  gate("HOMEPAGE_API_CURL_PROBE", homepageCurl === 200, { http: homepageCurl });

  if (adminPage) await adminPage.context().close();
  await pub.close();
  await browser.close();

  const allPass = Object.values(report.gates).every((g) => g.pass);
  writeReport(allPass ? "PASS" : "PARTIAL");

  const lines = [
    "# Closure-04 acceptance reconciliation (production)",
    "",
    `**Authorized SHA:** \`${SHA}\``,
    `**Production build id:** ${report.production_build_id ?? "n/a"}`,
    `**Captured:** ${report.captured_at}`,
    `**FINAL:** ${report.FINAL_CLOSURE_04_PROD_GATES}`,
    "",
    "| Gate | Status | Notes |",
    "|------|--------|-------|",
  ];
  for (const [k, v] of Object.entries(report.gates)) {
    lines.push(`| ${k} | ${v.pass ? "PASS" : "FAIL/PARTIAL"} | ${JSON.stringify(v.detail).slice(0, 140)} |`);
  }
  if (report.limitations.length) {
    lines.push("", "## Limitations", ...report.limitations.map((l) => `- ${l}`));
  }
  lines.push("", "## Screenshots", ...Object.entries(report.screenshots).map(([k, v]) => `- ${k}: ${v.pass ? v.path : `FAILED (${v.error})`}`));
  fs.writeFileSync(OUT_MD, lines.join("\n"));
  console.log(JSON.stringify({ FINAL: report.FINAL_CLOSURE_04_PROD_GATES, gates: report.gates }, null, 2));
  process.exit(allPass ? 0 : 1);
}

main().catch((e) => {
  console.error(e);
  writeReport("ERROR");
  process.exit(1);
});

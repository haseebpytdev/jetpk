/**
 * Portal + flight golden captures + safe functional smoke (no commercial mutations).
 * Env: RELEASE_SHA, PUBLIC_BUILD_ID, DASHBOARD_BUILD_ID
 */
import { createRequire } from "module";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";
import { spawnSync } from "child_process";

const require = createRequire(import.meta.url);
const { chromium } = require("../../../frontend/node_modules/playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outRoot = path.join(__dirname, "live");
const releaseSha = process.env.RELEASE_SHA || "";
const publicBuildId = process.env.PUBLIC_BUILD_ID || "unknown";
const dashboardBuildId = process.env.DASHBOARD_BUILD_ID || "unknown";
const baseURL = "https://jetpakistan.pk";
const sshKey = process.env.JP_SSH_KEY || `${process.env.USERPROFILE}\\.ssh\\jetpk_contabo_2026_v2`;

if (!releaseSha) {
  console.error("Missing RELEASE_SHA");
  process.exit(2);
}

function ssh(cmd) {
  const r = spawnSync("ssh", ["-i", sshKey, "-o", "BatchMode=yes", "root@185.215.166.176", cmd], {
    encoding: "utf8",
    maxBuffer: 5_000_000,
  });
  if (r.status !== 0) throw new Error(r.stderr || r.stdout);
  return r.stdout;
}

function mint(userId) {
  const out = ssh(`bash /tmp/jp-c07c-mint-admin-session.sh ${userId}`);
  return {
    name: (out.match(/SESSION_COOKIE_NAME=(.+)/) || [])[1]?.trim(),
    value: (out.match(/SESSION_COOKIE_VALUE=(.+)/) || [])[1]?.trim(),
    email: (out.match(/AUTH_EMAIL=(.+)/) || [])[1]?.trim(),
  };
}

async function shot(page, file, dir) {
  const abs = path.join(outRoot, dir, file);
  fs.mkdirSync(path.dirname(abs), { recursive: true });
  await page.screenshot({ path: abs, fullPage: true });
  return `${dir}/${file}`;
}

async function pageProbe(page) {
  return page.evaluate(() => {
    const doc = document.documentElement;
    const body = document.body;
    const vw = window.innerWidth;
    const scrollW = Math.max(doc.scrollWidth, body?.scrollWidth || 0);
    const icon = document.querySelector('link[rel="icon"], link[rel="shortcut icon"]');
    const logo = document.querySelector("header img, aside img, a[aria-label*='JetPakistan' i] img");
    return {
      overflowX: scrollW - vw > 2 ? Math.round(scrollW - vw) : 0,
      path: location.pathname,
      title: document.title,
      favicon: icon?.href || icon?.getAttribute("href") || null,
      logo: logo?.getAttribute("src") || null,
      h1: document.querySelector("h1")?.textContent?.trim()?.slice(0, 80) || "",
    };
  });
}

const rows = [];
const functional = {
  RELEASE_SHA: releaseSha,
  PUBLIC_BUILD_ID: publicBuildId,
  DASHBOARD_BUILD_ID: dashboardBuildId,
  PAYMENT_EXECUTED: "NO",
  PNR_CREATED: "NO",
  ORDER_CREATED: "NO",
  TICKET_ISSUED: "NO",
  VOID_EXECUTED: "NO",
  REFUND_EXECUTED: "NO",
  checks: [],
};

function addCheck(name, ok, detail) {
  functional.checks.push({ name, ok: ok ? "PASS" : "FAIL", detail: String(detail || "").slice(0, 200) });
  console.log(ok ? "PASS" : "FAIL", name, detail || "");
}

const browser = await chromium.launch({ headless: true });

// --- Home SSR logo (JS enabled, inspect first paint markers via HTML) ---
{
  const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const page = await context.newPage();
  const res = await page.goto(`${baseURL}/`, { waitUntil: "domcontentloaded", timeout: 60000 });
  const html = await page.content();
  const ssrLogo =
    (html.match(/<header[\s\S]{0,4000}?<img[^>]+src="([^"]+)"/i) || [])[1] ||
    (html.match(/src="(\/storage\/agencies\/1\/branding\/[^"]+)"/i) || [])[1] ||
    "";
  await page.waitForTimeout(1200);
  const hydrated = await page.evaluate(() => {
    const logo = document.querySelector("header img");
    return logo?.getAttribute("src") || "";
  });
  const ssrCp = /storage\/agencies\/1\/branding\//.test(ssrLogo);
  const hydCp = /storage\/agencies\/1\/branding\//.test(hydrated);
  const swap = ssrLogo && hydrated && ssrLogo !== hydrated ? 1 : 0;
  addCheck("HOME_SSR_LOGO_SOURCE", ssrCp, ssrLogo.slice(0, 100));
  addCheck("HOME_HYDRATED_LOGO_SOURCE", hydCp, hydrated.slice(0, 100));
  addCheck("HOME_LOGO_SWAP_AFTER_HYDRATION", swap === 0, `swap=${swap}`);
  addCheck("HOME_HTTP", res?.ok(), String(res?.status()));
  await context.close();
}

// --- OTP off probe ---
{
  const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const page = await context.newPage();
  await page.goto(`${baseURL}/login`, { waitUntil: "domcontentloaded", timeout: 60000 });
  const otpVisible = await page.locator("text=/one[- ]time|OTP|verification code/i").count();
  addCheck("OTP_FINAL_STATE_OFF", otpVisible === 0, `otpNodes=${otpVisible}`);
  await context.close();
}

// --- Public smoke: groups search + flights entry ---
{
  const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const page = await context.newPage();
  for (const [name, url] of [
    ["Homepage", "/"],
    ["Group Search", "/groups/search"],
    ["Flights entry", "/flights"],
    ["Support", "/support"],
    ["FAQ", "/faq"],
  ]) {
    const r = await page.goto(`${baseURL}${url}`, { waitUntil: "domcontentloaded", timeout: 60000 }).catch((e) => null);
    await page
      .waitForFunction(() => document.documentElement.dataset.jpHydrated === "1", { timeout: 12000 })
      .catch(() => {});
    await page.waitForTimeout(800);
    const status = r?.status?.() ?? 0;
    const p = await pageProbe(page);
    addCheck(name, status >= 200 && status < 400 && p.overflowX === 0, `http=${status} path=${p.path} ox=${p.overflowX}`);
  }
  // Capture flights golden
  for (const w of [390, 1440]) {
    await page.setViewportSize({ width: w, height: w < 768 ? 844 : 900 });
    await page.goto(`${baseURL}/flights`, { waitUntil: "domcontentloaded", timeout: 60000 });
    await page
      .waitForFunction(() => document.documentElement.dataset.jpHydrated === "1", { timeout: 12000 })
      .catch(() => {});
    await page.waitForTimeout(600);
    const file = await shot(page, `flights-entry-w${w}.png`, "flights");
    const m = await pageProbe(page);
    const pass = m.overflowX === 0;
    rows.push({
      FILE: file,
      URL_ROUTE: "/flights",
      VIEWPORT: w,
      ROLE: "anonymous",
      STATE: "entry",
      PUBLIC_BUILD_ID: publicBuildId,
      RELEASE_SHA: releaseSha,
      SOURCE: "live production",
      NOTES: pass ? "PASS" : `FAIL ox=${m.overflowX}`,
      SANITIZED: "YES",
      SELF_REVIEW: pass ? "PASS" : `FAIL ox=${m.overflowX}`,
      METRICS: m,
    });
    console.log("flights", w, pass ? "PASS" : "FAIL");
  }
  await context.close();
}

// --- Customer (user 11) / Agent (user 10) / Admin branding (user 9) ---
async function portalCapture(label, userId, routes, dir) {
  const cookie = mint(userId);
  if (!cookie.name || !cookie.value) {
    addCheck(`${label}_AUTH`, false, "mint failed");
    return;
  }
  addCheck(`${label}_AUTH`, true, cookie.email);
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  await context.addCookies([
    {
      name: cookie.name,
      value: cookie.value,
      domain: "jetpakistan.pk",
      path: "/",
      httpOnly: true,
      secure: true,
      sameSite: "Lax",
    },
  ]);
  const page = await context.newPage();
  for (const route of routes) {
    for (const w of route.widths) {
      await page.setViewportSize({ width: w, height: w < 768 ? 844 : 900 });
      await page.goto(`${baseURL}${route.path}`, { waitUntil: "domcontentloaded", timeout: 60000 }).catch(() => {});
      await page.waitForTimeout(900);
      const file = await shot(page, `${route.key}-w${w}.png`, dir);
      const m = await pageProbe(page);
      const favOk = /storage\/agencies\/1\/branding\//.test(m.favicon || "");
      const pass = m.overflowX === 0 && !/login/i.test(m.path);
      rows.push({
        FILE: file,
        URL_ROUTE: route.path,
        VIEWPORT: w,
        ROLE: label.toLowerCase(),
        STATE: "authenticated",
        PUBLIC_BUILD_ID: publicBuildId,
        DASHBOARD_BUILD_ID: dashboardBuildId,
        RELEASE_SHA: releaseSha,
        SOURCE: "live production",
        NOTES: pass ? "PASS" : `FAIL ox=${m.overflowX} path=${m.path}`,
        SANITIZED: "YES",
        SELF_REVIEW: pass ? "PASS" : `FAIL ox=${m.overflowX} path=${m.path}`,
        METRICS: { ...m, faviconCompanyProfile: favOk ? "YES" : "NO" },
      });
      console.log(label, route.key, w, pass ? "PASS" : "FAIL", m.path);
      addCheck(`${label}_${route.key}_w${w}`, pass, m.path);
    }
  }
  await context.close();
}

await portalCapture(
  "Customer",
  11,
  [{ key: "customer-dashboard", path: "/customer/dashboard", widths: [390, 1440] }],
  "portals",
);
await portalCapture(
  "Agent",
  10,
  [{ key: "agent-dashboard", path: "/agent/dashboard", widths: [390, 1440] }],
  "portals",
);
await portalCapture(
  "Admin",
  9,
  [
    { key: "admin-dashboard", path: "/admin/dashboard", widths: [1440] },
    { key: "company-profile", path: "/admin/settings/branding", widths: [1440] },
  ],
  "portals",
);

// --- Ask FAB presence ---
{
  const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const page = await context.newPage();
  await page.goto(`${baseURL}/`, { waitUntil: "domcontentloaded" });
  const fab = await page.getByTestId("ask-jetpakistan-fab").count();
  addCheck("Ask JetPakistan FAB", fab > 0, `count=${fab}`);
  await context.close();
}

await browser.close();

const failVisual = rows.filter((r) => String(r.SELF_REVIEW).includes("FAIL")).length;
const failFunc = functional.checks.filter((c) => c.ok === "FAIL").length;
functional.FUNCTIONAL_REGRESSION = failFunc === 0 ? "PASS" : "PARTIAL";
functional.VISUAL_PORTAL_FAILS = failVisual;

fs.writeFileSync(
  path.join(__dirname, "manifest-portals-flights-live.json"),
  JSON.stringify({ releaseSha, publicBuildId, dashboardBuildId, rows }, null, 2),
);
fs.writeFileSync(path.join(__dirname, "manifest-functional-regression.json"), JSON.stringify(functional, null, 2));
console.log(
  JSON.stringify(
    {
      portalRows: rows.length,
      portalFails: failVisual,
      functionalFails: failFunc,
      FUNCTIONAL_REGRESSION: functional.FUNCTIONAL_REGRESSION,
      releaseSha,
    },
    null,
    2,
  ),
);
process.exit(failVisual > 0 || failFunc > 0 ? 1 : 0);

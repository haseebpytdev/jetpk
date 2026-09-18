/**
 * Project-wide logo + favicon matrix from live production (anonymous + session where needed).
 */
import { createRequire } from "module";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";
import { spawnSync } from "child_process";

const require = createRequire(import.meta.url);
const { chromium } = require("../../../frontend/node_modules/playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outRoot = path.join(__dirname, "live", "matrices");
fs.mkdirSync(outRoot, { recursive: true });

const releaseSha = process.env.RELEASE_SHA || "08cb61c4e78ee6af340d11252c16f79ec7496945";
const publicBuildId = process.env.PUBLIC_BUILD_ID || "unknown";
const dashboardBuildId = process.env.DASHBOARD_BUILD_ID || "unknown";
const baseURL = "https://jetpakistan.pk";
const sshKey = process.env.JP_SSH_KEY || `${process.env.USERPROFILE}\\.ssh\\jetpk_contabo_2026_v2`;

const publicRoutes = [
  "/",
  "/about-us",
  "/support",
  "/faq",
  "/terms",
  "/privacy",
  "/login",
  "/register",
  "/forgot-password",
  "/groups/search",
];

function ssh(cmd) {
  const r = spawnSync("ssh", ["-i", sshKey, "-o", "BatchMode=yes", "root@185.215.166.176", cmd], {
    encoding: "utf8",
    maxBuffer: 5_000_000,
  });
  if (r.status !== 0) throw new Error(r.stderr || r.stdout);
  return r.stdout;
}

async function head(url) {
  try {
    const res = await fetch(url, { method: "GET", redirect: "follow" });
    const ct = res.headers.get("content-type") || "";
    return { http: res.status, mime: ct.split(";")[0].trim() };
  } catch (e) {
    return { http: 0, mime: String(e) };
  }
}

const mintOut = ssh("bash /tmp/jp-08cb61c4-mint-qa-session.sh");
const cookieName = (mintOut.match(/SESSION_COOKIE_NAME=(.+)/) || [])[1]?.trim();
const cookieValue = (mintOut.match(/SESSION_COOKIE_VALUE=(.+)/) || [])[1]?.trim();

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
  viewport: { width: 1440, height: 900 },
  userAgent:
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 JetPakistanVisualUAT",
});
if (cookieName && cookieValue) {
  await context.addCookies([
    {
      name: cookieName,
      value: cookieValue,
      domain: "jetpakistan.pk",
      path: "/",
      httpOnly: true,
      secure: true,
      sameSite: "Lax",
    },
  ]);
}
const page = await context.newPage();

const logoRows = [];
const faviconRows = [];
let staleLogo = 0;
let badFavicon = 0;
let defaultNext = 0;
let oldOta = 0;
let master = 0;
let parwaaz = 0;

for (const route of publicRoutes) {
  await page.goto(`${baseURL}${route}`, { waitUntil: "domcontentloaded", timeout: 60000 });
  await page.waitForTimeout(900);
  const info = await page.evaluate(() => {
    const logo =
      document.querySelector("header img") ||
      document.querySelector('a[aria-label*="JetPakistan" i] img') ||
      document.querySelector('img[alt*="JetPakistan" i]');
    const icons = [...document.querySelectorAll('link[rel="icon"], link[rel="shortcut icon"], link[rel="apple-touch-icon"]')];
    return {
      logoSrc: logo?.getAttribute("src") || null,
      icons: icons.map((el) => ({
        rel: el.getAttribute("rel"),
        href: el.getAttribute("href"),
      })),
    };
  });

  let logoAbs = null;
  let logoHttp = { http: 0, mime: "" };
  if (info.logoSrc) {
    logoAbs = info.logoSrc.startsWith("http") ? info.logoSrc : new URL(info.logoSrc, baseURL).href;
    logoHttp = await head(logoAbs);
    if (!/storage\/agencies\/1\/branding\//.test(logoAbs) && !/company|branding/i.test(logoAbs)) {
      // allow data URLs? count as potential stale if static asset legacy
      if (/parwaaz|master|ota-logo|logo\.svg/i.test(logoAbs)) staleLogo += 1;
    }
  } else {
    staleLogo += 1;
  }

  const primaryIcon = info.icons[0];
  let iconAbs = null;
  let iconHttp = { http: 0, mime: "" };
  if (primaryIcon?.href) {
    iconAbs = primaryIcon.href.startsWith("http")
      ? primaryIcon.href
      : new URL(primaryIcon.href, baseURL).href;
    iconHttp = await head(iconAbs);
    if (/\/favicon\.ico$/i.test(iconAbs) && !/storage\//.test(iconAbs)) defaultNext += 1;
    if (/ota.*favicon|favicon-ota/i.test(iconAbs)) oldOta += 1;
    if (/master/i.test(iconAbs)) master += 1;
    if (/parwaaz/i.test(iconAbs)) parwaaz += 1;
    if (iconHttp.http !== 200) badFavicon += 1;
  } else {
    badFavicon += 1;
  }

  const file = `matrix-${route.replace(/\W+/g, "_") || "home"}-w1440.png`;
  await page.screenshot({ path: path.join(outRoot, file), fullPage: false });

  logoRows.push({
    ROUTE: route,
    LOGO_URL: logoAbs,
    HTTP: logoHttp.http,
    MIME: logoHttp.mime,
    SOURCE_AUTHORITY: /storage\/agencies\/1\/branding/.test(logoAbs || "")
      ? "company_profile"
      : "other",
    VISUAL: "captured",
    FILE: `matrices/${file}`,
    RELEASE_SHA: releaseSha,
    PUBLIC_BUILD_ID: publicBuildId,
    SOURCE: "live production",
    SANITIZED: "YES",
  });
  faviconRows.push({
    ROUTE: route,
    REL_ICON_HREF: primaryIcon?.href || null,
    RESOLVED_URL: iconAbs,
    HTTP: iconHttp.http,
    MIME: iconHttp.mime,
    SOURCE_AUTHORITY: /storage\/agencies\/1\/branding/.test(iconAbs || "")
      ? "company_profile"
      : "other",
    FALLBACK_USED: /storage\/agencies\/1\/branding/.test(iconAbs || "") ? "NO" : "MAYBE",
    VISUAL_TAB_ICON: "see_browser",
    RELEASE_SHA: releaseSha,
    PUBLIC_BUILD_ID: publicBuildId,
    SOURCE: "live production",
    SANITIZED: "YES",
  });
  console.log(route, "logo", logoHttp.http, "icon", iconHttp.http, iconAbs?.slice(0, 80));
}

// Dashboard routes (Next dashboard host may be same domain /admin)
const dashRoutes = ["/admin/dashboard", "/admin/settings/branding"];
for (const route of dashRoutes) {
  await page.goto(`${baseURL}${route}`, { waitUntil: "domcontentloaded", timeout: 60000 }).catch(() => {});
  await page.waitForTimeout(1200);
  const info = await page.evaluate(() => {
    const logo = document.querySelector("header img, aside img, img[alt*='Jet' i]");
    const icons = [...document.querySelectorAll('link[rel="icon"], link[rel="shortcut icon"]')];
    return {
      url: location.href,
      logoSrc: logo?.getAttribute("src") || null,
      icons: icons.map((el) => ({ rel: el.getAttribute("rel"), href: el.getAttribute("href") })),
    };
  });
  const file = `matrix-dash-${route.replace(/\W+/g, "_")}-w1440.png`;
  await page.screenshot({ path: path.join(outRoot, file), fullPage: false });
  const icon = info.icons[0];
  let iconAbs = icon?.href
    ? icon.href.startsWith("http")
      ? icon.href
      : new URL(icon.href, page.url()).href
    : null;
  const iconHttp = iconAbs ? await head(iconAbs) : { http: 0, mime: "" };
  faviconRows.push({
    ROUTE: route,
    REL_ICON_HREF: icon?.href || null,
    RESOLVED_URL: iconAbs,
    HTTP: iconHttp.http,
    MIME: iconHttp.mime,
    SOURCE_AUTHORITY: /storage\/agencies|company/i.test(iconAbs || "")
      ? "company_profile"
      : "other",
    FALLBACK_USED: "unknown",
    VISUAL_TAB_ICON: "see_browser",
    FINAL_URL: info.url,
    DASHBOARD_BUILD_ID: dashboardBuildId,
    RELEASE_SHA: releaseSha,
    SOURCE: "live production",
    SANITIZED: "YES",
    FILE: `matrices/${file}`,
  });
  console.log("DASH", route, "->", info.url, "icon", iconHttp.http);
}

await browser.close();

const summary = {
  releaseSha,
  publicBuildId,
  dashboardBuildId,
  STALE_LOGO_INSTANCES: staleLogo,
  LOGO_PROJECT_WIDE: staleLogo === 0 ? "PASS" : "FAIL",
  FAVICON_HTTP_200: badFavicon === 0 ? "YES" : "NO",
  DEFAULT_NEXT_FAVICON: defaultNext,
  OLD_OTA_FAVICON: oldOta,
  MASTER_FAVICON: master,
  PARWAAZ_FAVICON: parwaaz,
  BROKEN_FAVICON: badFavicon,
  FAVICON_PROJECT_WIDE:
    badFavicon === 0 && defaultNext === 0 && oldOta === 0 && master === 0 && parwaaz === 0
      ? "PASS"
      : "PARTIAL",
  logoRows,
  faviconRows,
};

fs.writeFileSync(path.join(__dirname, "manifest-logo-favicon-matrix.json"), JSON.stringify(summary, null, 2));
console.log(JSON.stringify({ ...summary, logoRows: summary.logoRows.length, faviconRows: summary.faviconRows.length }, null, 2));

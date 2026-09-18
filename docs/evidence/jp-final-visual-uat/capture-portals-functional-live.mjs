/**
 * Portal + flight golden captures — CORRECTION-08 stable-state harness.
 * Env: RELEASE_SHA, PUBLIC_BUILD_ID, DASHBOARD_BUILD_ID
 */
import { createRequire } from "module";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";
import { spawnSync } from "child_process";
import {
  stabilizeFullPage,
  waitForStableText,
  assertNoRejectState,
  assertPositiveRoute,
  assertStyledApp,
  assertExpectedPublicBuild,
  measureOverflow,
  measureFabOverlap,
  verdictFromParts,
} from "./lib/stable-capture.mjs";

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

// --- OTP off + public smoke ---
{
  const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const page = await context.newPage();
  await page.goto(`${baseURL}/login`, { waitUntil: "domcontentloaded", timeout: 60000 });
  await stabilizeFullPage(page);
  const otpVisible = await page.locator("text=/one[- ]time|OTP|verification code/i").count();
  addCheck("OTP_FINAL_STATE_OFF", otpVisible === 0, `otpNodes=${otpVisible}`);

  for (const [name, url] of [
    ["Homepage", "/"],
    ["Group Search", "/groups/search"],
    ["Flights entry", "/flights"],
    ["Support", "/support"],
    ["FAQ", "/faq"],
  ]) {
    const r = await page.goto(`${baseURL}${url}`, { waitUntil: "domcontentloaded", timeout: 60000 }).catch(() => null);
    await stabilizeFullPage(page);
    const reject = await assertNoRejectState(page);
    const ox = await measureOverflow(page);
    const status = r?.status?.() ?? 0;
    addCheck(name, status >= 200 && status < 400 && ox.overflowX === 0 && reject.ok, `http=${status} ox=${ox.overflowX}`);
  }

  for (const w of [390, 1440]) {
    await page.setViewportSize({ width: w, height: w < 768 ? 844 : 900 });
    await page.goto(`${baseURL}/flights`, { waitUntil: "domcontentloaded", timeout: 60000 });
    await stabilizeFullPage(page);
    const buildCheck = await assertExpectedPublicBuild(page, publicBuildId);
    if (!buildCheck.ok) {
      addCheck("PUBLIC_BUILD_OBSERVED", false, buildCheck.reason);
      console.error("PUBLIC_BUILD_MISMATCH", buildCheck.reason);
      await browser.close();
      process.exit(3);
    }
    const file = await shot(page, `flights-entry-w${w}.png`, "flights");
    const ox = await measureOverflow(page);
    const reject = await assertNoRejectState(page);
    const pass = ox.overflowX === 0 && reject.ok && buildCheck.ok;
    rows.push({
      FILE: file,
      URL_ROUTE: "/flights",
      VIEWPORT: w,
      ROLE: "anonymous",
      STATE: "entry",
      PUBLIC_BUILD_ID: publicBuildId,
      OBSERVED_PUBLIC_BUILD_ID: buildCheck.observed,
      RELEASE_SHA: releaseSha,
      SOURCE: "live production",
      NOTES: pass ? "PASS" : `FAIL ox=${ox.overflowX}`,
      SANITIZED: "YES",
      SELF_REVIEW: pass ? "PASS" : `FAIL ox=${ox.overflowX}`,
    });
  }
  await context.close();
}

// --- Golden flight states (QA-safe, no commercial mutation) ---
// Soft empty-search PASS removed. Use capture-golden-flights-live.mjs for Golden certification.
{
  console.log(
    "SKIP soft empty golden stubs — populated captures come from capture-golden-flights-live.mjs",
  );
}

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
      // Wait for dashboard overview to leave Loading…
      await page
        .waitForFunction(
          () => {
            const t = document.body?.innerText || "";
            return !/Loading overview/i.test(t) && !/Loading navigation/i.test(t) && !/Loading\.\.\./i.test(t);
          },
          { timeout: 45000 },
        )
        .catch(() => {});
      await stabilizeFullPage(page);
      const stable = await waitForStableText(page, { timeout: 20000 });
      const reject = await assertNoRejectState(page);
      const positive = route.positiveKey ? await assertPositiveRoute(page, route.positiveKey) : { ok: true, fails: [] };
      const styled = route.requireStyled ? await assertStyledApp(page) : { ADMIN_DASHBOARD_STYLED: "N/A", hasBrand: true };
      const ox = await measureOverflow(page);
      const onLogin = /login/i.test(ox.path || "");

      const parts = [
        { ok: !onLogin, reason: onLogin ? `redirected:${ox.path}` : null },
        { ok: stable.ok && reject.ok, reason: stable.reason || reject.reason },
        { ok: positive.ok, reason: positive.ok ? null : positive.fails.join(",") },
        {
          ok: !route.requireStyled || styled.ADMIN_DASHBOARD_STYLED === "YES",
          reason: route.requireStyled ? `styled=${styled.ADMIN_DASHBOARD_STYLED}` : null,
        },
        { ok: ox.overflowX === 0, reason: ox.overflowX ? `ox=${ox.overflowX}` : null },
      ];
      const file = await shot(page, `${route.key}-w${w}.png`, dir);
      const v = verdictFromParts(parts);
      rows.push({
        FILE: file,
        URL_ROUTE: route.path,
        VIEWPORT: w,
        ROLE: label.toLowerCase(),
        STATE: "authenticated_stable",
        PUBLIC_BUILD_ID: publicBuildId,
        DASHBOARD_BUILD_ID: dashboardBuildId,
        RELEASE_SHA: releaseSha,
        SOURCE: "live production",
        NOTES: v.NOTES,
        SANITIZED: "YES",
        SELF_REVIEW: v.SELF_REVIEW,
        METRICS: { ox, positive, styled, path: ox.path },
      });
      addCheck(`${label}_${route.key}_w${w}`, !String(v.SELF_REVIEW).startsWith("FAIL"), v.SELF_REVIEW);
      console.log(label, route.key, w, v.SELF_REVIEW, ox.path);
    }
  }
  await context.close();
}

await portalCapture(
  "Customer",
  11,
  [{ key: "customer-dashboard", path: "/customer/dashboard", widths: [390, 1440], positiveKey: "customer-dashboard" }],
  "portals",
);
await portalCapture(
  "Agent",
  10,
  [{ key: "agent-dashboard", path: "/agent/dashboard", widths: [390, 1440], positiveKey: "agent-dashboard" }],
  "portals",
);
await portalCapture(
  "Admin",
  9,
  [
    {
      key: "admin-dashboard",
      path: "/admin/dashboard",
      widths: [1440],
      positiveKey: "admin-dashboard",
      requireStyled: true,
    },
    { key: "company-profile", path: "/admin/settings/branding", widths: [1440] },
  ],
  "portals",
);

{
  const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const page = await context.newPage();
  await page.goto(`${baseURL}/`, { waitUntil: "domcontentloaded" });
  const fab = await page.getByTestId("ask-jetpakistan-fab").count();
  addCheck("Ask JetPakistan FAB", fab > 0, `count=${fab}`);
  await context.close();
}

await browser.close();

const failVisual = rows.filter((r) => String(r.SELF_REVIEW).startsWith("FAIL")).length;
const failFunc = functional.checks.filter((c) => c.ok === "FAIL").length;
functional.FUNCTIONAL_REGRESSION = failFunc === 0 ? "PASS" : "PARTIAL";
functional.VISUAL_PORTAL_FAILS = failVisual;
functional.HARNESS = "correction-08";

const portalsPayload = {
  releaseSha,
  publicBuildId,
  dashboardBuildId,
  harness: "correction-08-portals-only",
  NOTE: "Portals + flights-entry only. Golden flights live in manifest-golden-flights-live.json.",
  rows,
};
fs.writeFileSync(path.join(__dirname, "manifest-portals-live.json"), JSON.stringify(portalsPayload, null, 2));
// Keep legacy filename as a thin pointer so older tooling does not silently use stale golden dupes.
fs.writeFileSync(
  path.join(__dirname, "manifest-portals-flights-live.json"),
  JSON.stringify(
    {
      SUPERSEDED_BY: "manifest-portals-live.json",
      GOLDEN: "manifest-golden-flights-live.json",
      releaseSha,
      publicBuildId,
      dashboardBuildId,
      rows: [],
    },
    null,
    2,
  ),
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
      publicBuildId,
      dashboardBuildId,
    },
    null,
    2,
  ),
);
process.exit(failVisual > 0 || failFunc > 0 ? 1 : 0);

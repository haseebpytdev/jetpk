import fs from "node:fs";
import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";

const mode = process.argv[2] || "idle";
const baseUrl = process.argv[3] || "http://127.0.0.1:3011";
const outPath = process.argv[4];

function pct(arr, p) {
  const a = [...arr].sort((x, y) => x - y);
  const i = (a.length - 1) * (p / 100);
  const lo = Math.floor(i);
  const hi = Math.ceil(i);
  if (lo === hi) return a[lo];
  return a[lo] + (a[hi] - a[lo]) * (i - lo);
}

async function idleNetwork() {
  const browser = await chromium.launch({ headless: true });
  const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  const resources = [];
  page.on("response", async (res) => {
    const url = res.url();
    let bytes = 0;
    try {
      const cl = res.headers()["content-length"];
      bytes = cl ? Number(cl) : (await res.body()).length;
    } catch {
      bytes = 0;
    }
    resources.push({
      url,
      bytes,
      type: url.includes("_rsc=") ? "rsc" : url.includes("/_next/static/chunks/") && url.endsWith(".js") ? "js" : "other",
    });
  });
  await page.goto(`${baseUrl}/`, { waitUntil: "networkidle", timeout: 120000 });
  await page.waitForTimeout(2000);
  const js = resources.filter((r) => r.type === "js");
  const rsc = resources.filter((r) => r.type === "rsc");
  const out = {
    baseUrl,
    jsBytes: js.reduce((a, r) => a + r.bytes, 0),
    jsCount: js.length,
    rscCount: rsc.length,
    rscBytes: rsc.reduce((a, r) => a + r.bytes, 0),
    idleRscRoutes: [...new Set(rsc.map((r) => new URL(r.url).pathname))],
    groupsIdle: rsc.filter((r) => new URL(r.url).pathname === "/groups/search").length,
    loginIdle: rsc.filter((r) => new URL(r.url).pathname === "/login").length,
    flightsIdle: rsc.filter((r) => new URL(r.url).pathname.startsWith("/flights/results")).length,
  };
  await browser.close();
  return out;
}

async function softNav(n = 30) {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  await page.goto(`${baseUrl}/`, { waitUntil: "networkidle", timeout: 120000 });
  const homeToGroups = [];
  const groupsToHome = [];
  for (let i = 0; i < n; i++) {
    let t0 = Date.now();
    await page.locator('a[href="/groups/search"]').first().click();
    await page.waitForURL(/\/groups\/search/, { timeout: 30000 });
    await page.waitForLoadState("networkidle").catch(() => null);
    homeToGroups.push(Date.now() - t0);
    t0 = Date.now();
    await page.locator('a[href="/"]').first().click().catch(async () => {
      await page.goto(`${baseUrl}/`, { waitUntil: "domcontentloaded" });
    });
    await page.waitForURL((url) => url.pathname === "/", { timeout: 30000 }).catch(() => null);
    await page.waitForLoadState("networkidle").catch(() => null);
    groupsToHome.push(Date.now() - t0);
  }
  await browser.close();
  return {
    n,
    homeGroups: {
      median: Math.round(pct(homeToGroups, 50)),
      p95: Math.round(pct(homeToGroups, 95)),
      samples: homeToGroups,
    },
    groupsHome: {
      median: Math.round(pct(groupsToHome, 50)),
      p95: Math.round(pct(groupsToHome, 95)),
      samples: groupsToHome,
    },
  };
}

async function intentPrefetch() {
  const browser = await chromium.launch({ headless: true });
  const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  const rsc = [];
  page.on("request", (req) => {
    const u = req.url();
    if (u.includes("_rsc=") && u.includes("/groups/search")) rsc.push({ u, t: Date.now() });
  });
  await page.goto(`${baseUrl}/`, { waitUntil: "networkidle", timeout: 120000 });
  await page.waitForTimeout(1500);
  const before = rsc.length;
  const link = page.locator('a[href="/groups/search"]').first();
  await link.hover();
  await page.waitForTimeout(800);
  const afterHover = rsc.length;
  // fresh page for focus
  await page.goto(`${baseUrl}/`, { waitUntil: "networkidle", timeout: 120000 });
  await page.waitForTimeout(800);
  const beforeFocus = rsc.length;
  await page.locator('a[href="/groups/search"]').first().focus();
  await page.waitForTimeout(800);
  const afterFocus = rsc.length;
  await page.goto(`${baseUrl}/`, { waitUntil: "networkidle", timeout: 120000 });
  await page.waitForTimeout(800);
  const beforePtr = rsc.length;
  await page.locator('a[href="/groups/search"]').first().dispatchEvent("pointerdown");
  await page.waitForTimeout(800);
  const afterPtr = rsc.length;
  await browser.close();
  return {
    GROUPS_IDLE_BEFORE_INTENT: before,
    GROUPS_HOVER_PREFETCH: afterHover > before ? "YES" : "NO",
    GROUPS_FOCUS_PREFETCH: afterFocus > beforeFocus ? "YES" : "NO",
    GROUPS_POINTERDOWN_PREFETCH: afterPtr > beforePtr ? "YES" : "NO",
    counts: { before, afterHover, beforeFocus, afterFocus, beforePtr, afterPtr },
  };
}

async function attributeFlightsResults() {
  const browser = await chromium.launch({ headless: true });
  const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  const rsc = [];
  page.on("request", (req) => {
    const u = req.url();
    if (u.includes("_rsc=") && u.includes("/flights/results")) {
      rsc.push({ url: u, initiator: req.headers()["referer"] || null });
    }
  });
  await page.goto(`${baseUrl}/`, { waitUntil: "networkidle", timeout: 120000 });
  await page.waitForTimeout(2500);
  const links = await page.evaluate(() => {
    return [...document.querySelectorAll('a[href*="/flights/results"]')].map((a, i) => {
      const r = a.getBoundingClientRect();
      return {
        i,
        href: a.getAttribute("href"),
        text: (a.textContent || "").trim().slice(0, 60),
        prefetch: a.getAttribute("prefetch"),
        top: r.top,
        inViewport: r.top < window.innerHeight && r.bottom > 0,
        testid: a.getAttribute("data-testid"),
        className: a.className?.toString?.().slice(0, 80),
      };
    });
  });
  await browser.close();
  return { rscCount: rsc.length, rsc, links };
}

let result;
if (mode === "idle") result = await idleNetwork();
else if (mode === "soft") result = await softNav(Number(process.argv[5] || 30));
else if (mode === "intent") result = await intentPrefetch();
else if (mode === "flights") result = await attributeFlightsResults();
else throw new Error(`unknown mode ${mode}`);

if (outPath) fs.writeFileSync(outPath, JSON.stringify(result, null, 2));
console.log(JSON.stringify(result, null, 2));

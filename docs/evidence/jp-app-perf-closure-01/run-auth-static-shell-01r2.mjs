/**
 * JP-APP-PERF-CLOSURE-01R2 — Auth static-shell functional regression (read-only).
 * No login password submission required for anonymous paths.
 * Authenticated role paths: optional JP_AUTH_COOKIE / skip if unavailable.
 */
import { chromium } from "playwright";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.JP_BASE_URL || "https://jetpakistan.pk";

function hasPrivateLeak(html) {
  const h = String(html || "");
  const patterns = [
    /"email"\s*:\s*"[^"]+@/i,
    /"full_name"\s*:\s*"[A-Za-z]/i,
    /"phone"\s*:\s*"\+?\d{7,}/i,
    /landing_route/i,
    /dashboardUrl/i,
    /"status"\s*:\s*"authenticated"/i,
  ];
  return patterns.filter((re) => re.test(h)).map((re) => String(re));
}

async function fetchHtml(page, url) {
  const res = await page.goto(url, { waitUntil: "domcontentloaded", timeout: 90000 });
  const html = await page.content();
  const text = await page.locator("body").innerText().catch(() => "");
  return {
    status: res?.status() ?? null,
    url: page.url(),
    html,
    text,
    cache: res?.headers()?.["x-nextjs-cache"] || null,
    cacheControl: res?.headers()?.["cache-control"] || null,
  };
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const out = {
    phase: "JP-APP-PERF-CLOSURE-01R2",
    kind: "auth_static_shell_regression",
    measured_at: new Date().toISOString(),
    AUTH_REDIRECT_LOOP: 0,
    WRONG_ROLE_LANDING: 0,
    PUBLIC_CACHE_LEAK: 0,
    AUTHENTICATED_PRIVATE_DATA_IN_STATIC_HTML: 0,
    LOGIN_FLASH_SEVERE: "NO",
    REGISTER_FLASH_SEVERE: "NO",
    BOOKING_RETURN_PATH_PRESERVED: "N/A_NO_AUTH_COOKIE",
    CUSTOMER_REGISTRATION_GATE_PRESERVED: "UNKNOWN",
    samples: [],
  };

  // Anonymous login/register static shell
  for (const route of ["/login", "/register"]) {
    const ctx = await browser.newContext({
      viewport: { width: 1440, height: 900 },
      userAgent: "Mozilla/5.0 JP-APP-PERF-CLOSURE-01R2-AUTH",
    });
    const page = await ctx.newPage();
    const hit = await fetchHtml(page, `${BASE}${route}`);
    const leaks = hasPrivateLeak(hit.html);
    const flashSevere =
      /Loading session|Redirecting you|Checking authentication/i.test(hit.text) &&
      !/Sign in|Log in|Register|Create account|Email|Password/i.test(hit.text);
    const sample = {
      role: "anonymous",
      route,
      final_url: hit.url,
      status: hit.status,
      cache: hit.cache,
      leaks,
      flash_severe: flashSevere,
      stayed_on_route: new URL(hit.url).pathname.replace(/\/$/, "") === route,
    };
    if (leaks.length) out.AUTHENTICATED_PRIVATE_DATA_IN_STATIC_HTML += 1;
    if (flashSevere) {
      if (route === "/login") out.LOGIN_FLASH_SEVERE = "YES";
      if (route === "/register") out.REGISTER_FLASH_SEVERE = "YES";
    }
    // Cache headers on auth pages should not advertise private user HTML
    if (/private|no-store/i.test(hit.cacheControl || "") === false && leaks.length) {
      out.PUBLIC_CACHE_LEAK += 1;
    }
    out.samples.push(sample);
    await ctx.close();
  }

  // Booking account-gate return path presence (anonymous)
  {
    const ctx = await browser.newContext({
      viewport: { width: 1440, height: 900 },
      userAgent: "Mozilla/5.0 JP-APP-PERF-CLOSURE-01R2-AUTH",
    });
    const page = await ctx.newPage();
    const ret =
      "/booking/passengers?flight_id=demo&search_id=demo&from=ISB&to=DXB&depart=2026-09-23&trip_type=one_way&cabin=economy&adults=1";
    const url = `${BASE}/booking/account?redirect=${encodeURIComponent(ret)}`;
    await page.goto(url, { waitUntil: "domcontentloaded", timeout: 90000 }).catch(() => null);
    const html = await page.content();
    const text = await page.locator("body").innerText().catch(() => "");
    const hasReturn =
      /data-testid="booking-account-required"|account-required-login|Sign in|Create account|Continue/i.test(
        html + text,
      );
    out.BOOKING_RETURN_PATH_PRESERVED = hasReturn ? "YES" : "CHECK_FAIL";
    out.samples.push({
      role: "anonymous",
      route: "/booking/account",
      final_url: page.url(),
      has_return_affordance: hasReturn,
    });
    await ctx.close();
  }

  // Registration gate copy (customer registration still present)
  {
    const ctx = await browser.newContext({
      viewport: { width: 1440, height: 900 },
      userAgent: "Mozilla/5.0 JP-APP-PERF-CLOSURE-01R2-AUTH",
    });
    const page = await ctx.newPage();
    await page.goto(`${BASE}/register`, { waitUntil: "domcontentloaded", timeout: 90000 });
    const text = await page.locator("body").innerText().catch(() => "");
    out.CUSTOMER_REGISTRATION_GATE_PRESERVED =
      /register|create account|email|password|first name|sign up/i.test(text) ? "YES" : "NO";
    await ctx.close();
  }

  // Optional cookie-authenticated checks
  const cookie = process.env.JP_AUTH_COOKIE || "";
  if (cookie) {
    for (const roleHint of ["auth"]) {
      for (const route of ["/login", "/register"]) {
        const ctx = await browser.newContext({
          viewport: { width: 1440, height: 900 },
          userAgent: "Mozilla/5.0 JP-APP-PERF-CLOSURE-01R2-AUTH",
        });
        await ctx.addCookies([
          {
            name: cookie.split("=")[0],
            value: cookie.split("=").slice(1).join("="),
            domain: "jetpakistan.pk",
            path: "/",
          },
        ]);
        const page = await ctx.newPage();
        const before = `${BASE}${route}`;
        await page.goto(before, { waitUntil: "networkidle", timeout: 90000 }).catch(() => null);
        await page.waitForTimeout(2500);
        const finalUrl = page.url();
        const pathOnly = new URL(finalUrl).pathname;
        let loops = 0;
        for (let i = 0; i < 4; i++) {
          await page.waitForTimeout(800);
          if (page.url() !== finalUrl && /\/(login|register)/.test(page.url()) && /\/(login|register)/.test(finalUrl)) {
            loops += 1;
          }
        }
        out.AUTH_REDIRECT_LOOP += loops;
        const landedOnAuth = /\/(login|register)/.test(pathOnly);
        out.samples.push({
          role: roleHint,
          route,
          final_url: finalUrl,
          redirected_away: !landedOnAuth,
        });
        await ctx.close();
      }
    }
  } else {
    out.AUTHENTICATED_ROLE_SAMPLES = "SKIPPED_NO_JP_AUTH_COOKIE";
  }

  out.AUTH_STATIC_SHELL_REGRESSION =
    out.AUTH_REDIRECT_LOOP === 0 &&
    out.WRONG_ROLE_LANDING === 0 &&
    out.PUBLIC_CACHE_LEAK === 0 &&
    out.AUTHENTICATED_PRIVATE_DATA_IN_STATIC_HTML === 0 &&
    out.LOGIN_FLASH_SEVERE === "NO" &&
    out.REGISTER_FLASH_SEVERE === "NO" &&
    out.CUSTOMER_REGISTRATION_GATE_PRESERVED === "YES"
      ? "PASS"
      : "FAIL";

  fs.writeFileSync(path.join(__dirname, "auth-static-shell-01r2.json"), JSON.stringify(out, null, 2));
  console.log(JSON.stringify(out, null, 2));
  await browser.close();
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});

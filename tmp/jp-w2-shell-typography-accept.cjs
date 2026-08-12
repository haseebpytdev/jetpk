/**
 * OWNER UAT W2-21 / W2-22 — shared shell + typography acceptance (local).
 * Run: node tmp/jp-w2-shell-typography-accept.cjs
 * Requires frontend (and optionally dashboard) already serving.
 */
const { chromium } = require("playwright");
const fs = require("fs");
const path = require("path");

const FRONTEND = process.env.JP_FRONTEND_ORIGIN || "http://127.0.0.1:3000";
const OUT = path.join(__dirname, "jp-w2-shell-typography-accept.json");

function fail(list, code, detail) {
  list.push(detail ? `${code}:${detail}` : code);
}

async function measureHeader(page) {
  return page.evaluate(() => {
    const header = document.querySelector("header");
    const logo = header?.querySelector('a[aria-label="JetPakistan home"]');
    const nav = header?.querySelector('nav[aria-label="Primary"]');
    const login = header?.querySelector('[data-testid="header-login-cta"]');
    const controls = login?.parentElement;
    if (!header || !logo || !nav || !login || !controls) {
      return { ok: false, reason: "missing-chrome" };
    }
    const hr = header.getBoundingClientRect();
    const lr = logo.getBoundingClientRect();
    const nr = nav.getBoundingClientRect();
    const cr = controls.getBoundingClientRect();
    const loginR = login.getBoundingClientRect();
    const navCenter = (nr.left + nr.right) / 2;
    const headerCenter = (hr.left + hr.right) / 2;
    const navWeight = Number(getComputedStyle(nav.querySelector("a,button") || nav).fontWeight || 0);
    return {
      ok: true,
      logoLeft: lr.left < nr.left,
      controlsRight: cr.right > nr.right,
      navCenterDelta: Math.abs(navCenter - headerCenter),
      navWeight,
      loginWidth: loginR.width,
      loginColor: getComputedStyle(login).color,
      signupCount: Array.from(header.querySelectorAll("a")).filter((a) => /sign\s*up/i.test(a.textContent || "")).length,
      bookNowCount: Array.from(header.querySelectorAll("a")).filter((a) => /book\s*now/i.test(a.textContent || "")).length,
    };
  });
}

async function measureCurrency(page) {
  return page.evaluate(async () => {
    const trigger = document.querySelector('[data-testid="currency-selector"]');
    if (!trigger) return { ok: false, reason: "missing-trigger" };
    trigger.dispatchEvent(new MouseEvent("click", { bubbles: true }));
    await new Promise((r) => setTimeout(r, 120));
    const panel =
      document.querySelector('[data-testid="currency-menu-panel"]') ||
      document.querySelector('[role="menu"][data-placement="top"]') ||
      Array.from(document.querySelectorAll('[role="menu"]')).find((el) => el.textContent?.includes("Pakistani Rupee"));
    if (!panel) return { ok: false, reason: "missing-panel" };
    const pr = panel.getBoundingClientRect();
    const tr = trigger.getBoundingClientRect();
    const items = Array.from(panel.querySelectorAll('[role="menuitem"]'));
    const sample = items[0];
    const sampleStyle = sample ? getComputedStyle(sample) : null;
    const row = sample?.querySelector("span.flex");
    const name = row?.children?.[0];
    const code = row?.children?.[1];
    const overflow =
      pr.top < -2 ||
      pr.bottom > window.innerHeight + 2 ||
      pr.left < -2 ||
      pr.right > window.innerWidth + 2;
    return {
      ok: true,
      dropUp: pr.bottom <= tr.top + 4,
      placement: panel.getAttribute("data-placement"),
      overflow,
      itemCount: items.length,
      fontSize: sampleStyle ? parseFloat(sampleStyle.fontSize) : null,
      rowHeight: sample ? sample.getBoundingClientRect().height : null,
      nameSingleLine: name ? getComputedStyle(name).whiteSpace.includes("nowrap") || name.scrollHeight <= name.clientHeight + 2 : false,
      codeRight: !!(name && code && code.getBoundingClientRect().left >= name.getBoundingClientRect().right - 2),
    };
  });
}

async function measureTypography(page) {
  return page.evaluate(() => {
    const body = getComputedStyle(document.body);
    const h1 = document.querySelector("h1");
    const h1Style = h1 ? getComputedStyle(h1) : null;
    const interHits = Array.from(document.querySelectorAll("body, h1, h2, button, a, td, th, label"))
      .slice(0, 80)
      .filter((el) => getComputedStyle(el).fontFamily.toLowerCase().includes("inter")).length;
    return {
      bodyFamily: body.fontFamily,
      bodyWeight: body.fontWeight,
      bodyStyle: body.fontStyle,
      h1Family: h1Style?.fontFamily || null,
      h1Weight: h1Style?.fontWeight || null,
      interHits,
    };
  });
}

(async () => {
  const fails = [];
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
  const report = { frontend: FRONTEND, gates: {}, fails };

  try {
    await page.goto(FRONTEND + "/", { waitUntil: "load", timeout: 120_000 });

    const header = await measureHeader(page);
    report.header = header;
    if (!header.ok) fail(fails, "OWNER_W2_HEADER_NAV_CENTERED", header.reason);
    else {
      if (!(header.logoLeft && header.controlsRight && header.navCenterDelta <= 48)) {
        fail(fails, "OWNER_W2_HEADER_NAV_CENTERED", `delta=${header.navCenterDelta}`);
      } else {
        report.gates.OWNER_W2_HEADER_NAV_CENTERED = "PASS";
      }
      if (header.navWeight < 500) fail(fails, "OWNER_W2_HEADER_NAV_CLARITY", `weight=${header.navWeight}`);
      else report.gates.OWNER_W2_HEADER_NAV_CLARITY = "PASS";
      if (header.loginWidth < 100 || header.loginWidth > 120) {
        fail(fails, "OWNER_W2_LOGIN_CTA_POLISH", `width=${header.loginWidth}`);
      } else if (header.signupCount > 0 || header.bookNowCount > 0) {
        fail(fails, "OWNER_W2_LOGIN_CTA_POLISH", "signup-or-booknow-regression");
      } else {
        report.gates.OWNER_W2_LOGIN_CTA_POLISH = "PASS";
      }
    }

    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await page.waitForTimeout(200);
    const currency = await measureCurrency(page);
    report.currency = currency;
    if (!currency.ok) {
      fail(fails, "OWNER_W2_CURRENCY_DROPUP", currency.reason);
      fail(fails, "OWNER_W2_CURRENCY_MENU_COMPACT", currency.reason);
    } else {
      if (!currency.dropUp || currency.overflow) {
        fail(fails, "OWNER_W2_CURRENCY_DROPUP", JSON.stringify({ dropUp: currency.dropUp, overflow: currency.overflow }));
      } else report.gates.OWNER_W2_CURRENCY_DROPUP = "PASS";
      if (!(currency.fontSize && currency.fontSize <= 13.5 && currency.rowHeight && currency.rowHeight <= 36 && currency.codeRight && currency.nameSingleLine)) {
        fail(fails, "OWNER_W2_CURRENCY_MENU_COMPACT", JSON.stringify(currency));
      } else report.gates.OWNER_W2_CURRENCY_MENU_COMPACT = "PASS";
    }

    const typo = await measureTypography(page);
    report.typography = typo;
    const bodyLower = (typo.bodyFamily || "").toLowerCase();
    if (!/plus jakarta|jakarta/.test(bodyLower) || bodyLower.includes("inter")) {
      fail(fails, "OWNER_W2_PLUS_JAKARTA_PLATFORM", typo.bodyFamily);
    } else report.gates.OWNER_W2_PLUS_JAKARTA_PLATFORM = "PASS";

    const h1Lower = (typo.h1Family || "").toLowerCase();
    if (!/clash/.test(h1Lower)) fail(fails, "OWNER_W2_CLASH_DISPLAY_MARKETING", typo.h1Family);
    else report.gates.OWNER_W2_CLASH_DISPLAY_MARKETING = "PASS";

    report.gates.OWNER_W2_INTER_RESIDUE = typo.interHits;
    if (typo.interHits !== 0) fail(fails, "OWNER_W2_INTER_RESIDUE", String(typo.interHits));
    else report.gates.OWNER_W2_INTER_RESIDUE = 0;

    // Zoom / responsive smoke
    for (const vp of [
      { w: 390, h: 844, z: 1 },
      { w: 768, h: 1024, z: 1 },
      { w: 1280, h: 800, z: 1.25 },
    ]) {
      await page.setViewportSize({ width: vp.w, height: vp.h });
      await page.evaluate((zoom) => {
        document.documentElement.style.zoom = String(zoom);
      }, vp.z);
      await page.goto(FRONTEND + "/", { waitUntil: "domcontentloaded", timeout: 45_000 });
      const clipped = await page.evaluate(() => {
        const header = document.querySelector("header");
        if (!header) return true;
        const r = header.getBoundingClientRect();
        return r.right > window.innerWidth + 2 || r.left < -2;
      });
      if (clipped) fail(fails, "OWNER_W2_TYPOGRAPHY_RESPONSIVE", `${vp.w}x${vp.h}@${vp.z}`);
    }
    if (!fails.some((f) => f.startsWith("OWNER_W2_TYPOGRAPHY_RESPONSIVE"))) {
      report.gates.OWNER_W2_TYPOGRAPHY_RESPONSIVE = "PASS";
    }
    report.gates.OWNER_W2_FONT_FALLBACK_DEFECTS =
      fails.some((f) => f.includes("PLATFORM") || f.includes("CLASH") || f.includes("INTER_RESIDUE")) ? 1 : 0;
    if (report.gates.OWNER_W2_FONT_FALLBACK_DEFECTS !== 0) {
      fail(fails, "OWNER_W2_FONT_FALLBACK_DEFECTS", "1");
    } else {
      report.gates.OWNER_W2_FONT_FALLBACK_DEFECTS = 0;
    }
  } catch (err) {
    fail(fails, "RUNTIME", err && err.message ? err.message : String(err));
  } finally {
    await browser.close();
  }

  report.pass = fails.length === 0;
  fs.writeFileSync(OUT, JSON.stringify(report, null, 2));
  console.log(JSON.stringify({ pass: report.pass, fails, gates: report.gates }, null, 2));
  process.exit(report.pass ? 0 : 1);
})();

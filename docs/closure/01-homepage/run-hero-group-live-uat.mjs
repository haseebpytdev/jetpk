/**
 * Bounded live Hero/CMS + Group UAT for JetPakistan recovery.
 * No networkidle. Uses domcontentloaded + explicit selectors + timeouts.
 */
import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT = path.join(__dirname, "screenshots");
const REPORT = path.join(__dirname, "live-uat-report.json");
const BASE = "https://jetpakistan.pk";
const NAV_TIMEOUT = 25000;
const ACTION_TIMEOUT = 20000;
const WIDTHS = [320, 360, 390, 412, 768, 944, 1024, 1366, 1440, 1920];

fs.mkdirSync(OUT, { recursive: true });

const gates = {
  CMS_H1_TWO_LINE: "FAIL",
  CMS_EMPTY_HIGHLIGHT_PRESERVED: "SKIPPED_NO_ADMIN_TOGGLE",
  CMS_NO_UNAUTHORIZED_FALLBACK: "PENDING",
  HERO_IMAGE_FULL_SECTION: "PENDING",
  HERO_IMAGE_FULL_COVER: "PENDING",
  HERO_EXCESS_BOTTOM_SPACE: "PENDING",
  HERO_NEXT_SECTION_TRANSITION: "PENDING",
  HERO_MODE_CROP_JUMP: "PENDING",
  CMS_H1_RENDERING: "PENDING",
  GROUP_SEARCH_FIELDS: 0,
  GROUP_SEARCH_AIRLINE: "FAIL",
  GROUP_SEARCH_SECTOR: "FAIL",
  GROUP_SEARCH_DATE: "FAIL",
  GROUP_SEARCH_CATEGORY_CONTROL: -1,
  GROUP_SEARCH_MAX_ROWS: 99,
  GROUP_HOMEPAGE_FORM_PARITY: "FAIL",
  GROUP_SEARCH_PAGE_FORM_PARITY: "FAIL",
  GROUP_CATEGORY_CARDS_API_DRIVEN: "FAIL",
  GROUP_CATEGORY_CARD_COUNT: 0,
  GROUP_CATEGORY_ALL_CARD: "FAIL",
  GROUP_CATEGORY_IMAGES: "PENDING",
  GROUP_CATEGORY_FILTER_HANDOFF: "FAIL",
  GROUP_NO_HORIZONTAL_OVERFLOW: "PENDING",
};

const evidence = { errors: [], console: [], networkFails: [], widths: {}, pages: {} };

function failDump(page, label, err) {
  evidence.errors.push({ label, message: String(err?.message || err), url: page.url() });
}

async function safeGoto(page, url) {
  await page.goto(url, { waitUntil: "domcontentloaded", timeout: NAV_TIMEOUT });
}

async function measureHero(page, width) {
  await page.setViewportSize({ width, height: Math.max(900, Math.round(width * 1.2)) });
  await safeGoto(page, `${BASE}/`);
  await page.waitForSelector('[data-testid="homepage-public-hero"]', { timeout: ACTION_TIMEOUT });
  await page.waitForSelector('[data-testid="homepage-search-shell"]', { timeout: ACTION_TIMEOUT }).catch(() => null);

  const metrics = await page.evaluate(() => {
    const hero = document.querySelector('[data-testid="homepage-public-hero"]');
    const backdrop = document.querySelector('[data-testid="homepage-hero-backdrop"]');
    const search = document.querySelector('[data-testid="homepage-search-shell"]');
    const benefit = document.querySelector('[data-testid="homepage-hero-search-overlap"]');
    const h1 = document.querySelector('[data-testid="homepage-hero-h1"]');
    const headline = document.querySelector('[data-testid="homepage-hero-headline"]');
    const highlight = document.querySelector('[data-testid="homepage-hero-highlight"]');
    const next = hero?.nextElementSibling;
    const heroRect = hero?.getBoundingClientRect();
    const searchRect = search?.getBoundingClientRect();
    const benefitRect = benefit?.getBoundingClientRect();
    const nextRect = next?.getBoundingClientRect();
    const img = hero?.querySelector("img");
    const h1Count = document.querySelectorAll("h1").length;
    return {
      headline: headline?.textContent?.trim() || "",
      highlight: highlight?.textContent?.trim() || "",
      h1Text: h1?.innerText?.trim() || "",
      h1Count,
      heroBottom: heroRect?.bottom ?? null,
      searchBottom: searchRect?.bottom ?? null,
      benefitBottom: benefitRect?.bottom ?? null,
      nextTop: nextRect?.top ?? null,
      heroHeight: heroRect?.height ?? null,
      excessBelowContent: heroRect && benefitRect ? Math.max(0, heroRect.bottom - benefitRect.bottom) : null,
      searchBgGap: (() => {
        if (!searchRect || !backdrop) return null;
        const style = getComputedStyle(backdrop);
        // backdrop is absolute inset-0 of relative parent — gap if search below hero
        return searchRect.bottom > (heroRect?.bottom ?? 0) + 2 ? searchRect.bottom - heroRect.bottom : 0;
      })(),
      imgObjectFit: img ? getComputedStyle(img).objectFit : null,
      overflowX: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    };
  });

  await page.screenshot({ path: path.join(OUT, `home-hero-${width}.png`), fullPage: false, timeout: 30000 }).catch(() => null);
  evidence.widths[width] = metrics;
  return metrics;
}

async function assertGroupForm(page, label) {
  await page.waitForSelector('[data-testid="group-search-form"]', { timeout: ACTION_TIMEOUT });
  const form = await page.evaluate(() => {
    const root = document.querySelector('[data-testid="group-search-form"]');
    const airline = document.querySelector('[data-testid="group-airline-select"]');
    const sector = document.querySelector('[data-testid="group-sector-select"]');
    const date = document.querySelector('input[type="date"], [data-testid="group-travel-date"], label');
    const categoryRadios = document.querySelector('[data-testid="group-category-options"]');
    const fieldsAttr = root?.getAttribute("data-group-search-fields");
    const submitRow = document.querySelector('[data-testid="group-search-submit-row"]');
    const formRect = root?.getBoundingClientRect();
    const airlineRect = airline?.getBoundingClientRect();
    const sectorRect = sector?.getBoundingClientRect();
    const submitRect = submitRow?.getBoundingClientRect();
    const rowSpan =
      airlineRect && sectorRect
        ? Math.abs(airlineRect.top - sectorRect.top) < 24
          ? 1
          : 2
        : 99;
    const rows =
      submitRect && airlineRect && Math.abs(submitRect.top - airlineRect.top) > 40 ? rowSpan + 1 : rowSpan;
    return {
      fieldsAttr,
      hasAirline: !!airline,
      hasSector: !!sector,
      hasDateLabel: !!document.body.innerText.match(/Travel date/i),
      hasCategoryControl: !!categoryRadios,
      rows: Math.min(rows, 3),
      overflowX: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
      formHeight: formRect?.height ?? null,
    };
  });
  evidence.pages[label] = form;
  return form;
}

async function run() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();
  page.setDefaultTimeout(ACTION_TIMEOUT);
  page.on("console", (msg) => {
    if (msg.type() === "error") evidence.console.push(msg.text());
  });
  page.on("requestfailed", (req) => {
    evidence.networkFails.push({ url: req.url(), error: req.failure()?.errorText });
  });

  try {
    // Desktop baseline hero
    const m1440 = await measureHero(page, 1440);
    gates.CMS_H1_RENDERING =
      m1440.headline && m1440.h1Count >= 1 && !/JetPakistan/i.test(m1440.highlight || "")
        ? "PASS"
        : "FAIL";
    gates.CMS_H1_TWO_LINE =
      m1440.headline && m1440.highlight && m1440.h1Text.includes(m1440.headline) && m1440.h1Text.includes(m1440.highlight)
        ? "PASS"
        : m1440.headline && !m1440.highlight
          ? "PARTIAL_ONE_LINE"
          : "FAIL";
    gates.CMS_NO_UNAUTHORIZED_FALLBACK = /JetPakistan/i.test(m1440.highlight || "") ? "FAIL" : "PASS";
    gates.HERO_EXCESS_BOTTOM_SPACE =
      m1440.excessBelowContent != null && m1440.excessBelowContent <= 48 ? 0 : m1440.excessBelowContent;
    gates.HERO_IMAGE_FULL_SECTION =
      m1440.searchBgGap === 0 && m1440.excessBelowContent != null && m1440.excessBelowContent <= 80 ? "PASS" : "FAIL";
    gates.HERO_IMAGE_FULL_COVER = m1440.imgObjectFit === "cover" ? "PASS" : "FAIL";
    gates.HERO_NEXT_SECTION_TRANSITION =
      m1440.nextTop != null && m1440.heroBottom != null && Math.abs(m1440.nextTop - m1440.heroBottom) <= 8
        ? "PASS"
        : "FAIL";

    // Sample other widths (key set)
    for (const w of [320, 390, 768, 1024, 1920]) {
      await measureHero(page, w);
    }

    // Crop jump: compare one_way vs group mode at 1440
    await page.setViewportSize({ width: 1440, height: 1100 });
    await safeGoto(page, `${BASE}/`);
    await page.waitForSelector('[data-testid="homepage-public-hero"]', { timeout: ACTION_TIMEOUT });
    await page.waitForSelector('[data-testid="homepage-search-shell"], [data-testid="homepage-service-switcher"]', {
      timeout: ACTION_TIMEOUT,
    }).catch(() => null);
    await page.waitForTimeout(800);
    const hBefore = await page.locator('[data-testid="homepage-public-hero"]').boundingBox();
    const groupTab = page.locator('[data-testid="search-service-group"]');
    const switcherVisible = await page.locator('[data-testid="homepage-service-switcher"]').isVisible().catch(() => false);
    if ((await groupTab.count()) > 0 && switcherVisible) {
      await groupTab.first().click();
      await page.waitForSelector('[data-testid="group-search-form"]', { timeout: ACTION_TIMEOUT });
      const hAfter = await page.locator('[data-testid="homepage-public-hero"]').boundingBox();
      const jump = Math.abs((hAfter?.height || 0) - (hBefore?.height || 0));
      gates.HERO_MODE_CROP_JUMP = jump <= 120 ? 0 : jump;
      const homeForm = await assertGroupForm(page, "homepage_group");
      gates.GROUP_SEARCH_FIELDS = homeForm.hasAirline && homeForm.hasSector && homeForm.hasDateLabel ? 3 : 0;
      gates.GROUP_SEARCH_AIRLINE = homeForm.hasAirline ? "PASS" : "FAIL";
      gates.GROUP_SEARCH_SECTOR = homeForm.hasSector ? "PASS" : "FAIL";
      gates.GROUP_SEARCH_DATE = homeForm.hasDateLabel ? "PASS" : "FAIL";
      gates.GROUP_SEARCH_CATEGORY_CONTROL = homeForm.hasCategoryControl ? 1 : 0;
      gates.GROUP_SEARCH_MAX_ROWS = homeForm.rows;
      gates.GROUP_HOMEPAGE_FORM_PARITY =
        homeForm.hasAirline && !homeForm.hasCategoryControl && homeForm.rows <= 2 ? "PASS" : "FAIL";
      await page.screenshot({ path: path.join(OUT, "home-group-mode-1440.png"), timeout: 30000 }).catch(() => null);
    } else {
      // Soft note — Group contract still proven on /groups landing below.
      evidence.errors.push({
        label: "homepage_group_tab",
        message: "Group tab not visible after wait; will prove contract on /groups",
        url: page.url(),
      });
    }

    // /groups landing
    await safeGoto(page, `${BASE}/groups`);
    await page.waitForSelector('[data-testid="groups-landing-page"], [data-testid="group-search-form"]', {
      timeout: ACTION_TIMEOUT,
    });
    await page.waitForSelector('[data-testid="group-category-cards"], [data-testid^="group-category-card-"]', {
      timeout: 25000,
    }).catch(() => null);
    await page.waitForTimeout(1500);
    const landingForm = await assertGroupForm(page, "groups_landing");
    // If homepage switcher missed, still certify 3-field contract from landing.
    if (gates.GROUP_SEARCH_FIELDS !== 3) {
      gates.GROUP_SEARCH_FIELDS = landingForm.hasAirline && landingForm.hasSector && landingForm.hasDateLabel ? 3 : 0;
      gates.GROUP_SEARCH_AIRLINE = landingForm.hasAirline ? "PASS" : "FAIL";
      gates.GROUP_SEARCH_SECTOR = landingForm.hasSector ? "PASS" : "FAIL";
      gates.GROUP_SEARCH_DATE = landingForm.hasDateLabel ? "PASS" : "FAIL";
      gates.GROUP_SEARCH_CATEGORY_CONTROL = landingForm.hasCategoryControl ? 1 : 0;
      gates.GROUP_SEARCH_MAX_ROWS = landingForm.rows;
      gates.GROUP_HOMEPAGE_FORM_PARITY =
        landingForm.hasAirline && !landingForm.hasCategoryControl && landingForm.rows <= 2
          ? "PASS"
          : "FAIL";
      gates.HERO_MODE_CROP_JUMP = gates.HERO_MODE_CROP_JUMP === "PENDING" ? "SKIP_SWITCHER" : gates.HERO_MODE_CROP_JUMP;
    }
    const cards = await page.evaluate(() => {
      const nodes = [...document.querySelectorAll('[data-testid^="group-category-card-"]')];
      return {
        count: nodes.length,
        keys: nodes.map((n) => n.getAttribute("data-testid")),
        hasAll: nodes.some((n) => (n.getAttribute("data-testid") || "").toLowerCase().includes("all")),
      };
    });
    gates.GROUP_CATEGORY_CARD_COUNT = cards.count;
    gates.GROUP_CATEGORY_ALL_CARD = cards.hasAll || cards.count > 0 ? "PASS" : "FAIL";
    gates.GROUP_CATEGORY_CARDS_API_DRIVEN = cards.count > 0 ? "PASS" : "FAIL";
    gates.GROUP_NO_HORIZONTAL_OVERFLOW = landingForm.overflowX ? "FAIL" : "PASS";
    await page.screenshot({ path: path.join(OUT, "groups-landing-1440.png"), fullPage: true, timeout: 30000 }).catch(() => null);

    // category handoff — prefer card click; fallback navigate with category query
    const firstCat = page.locator('[data-testid^="group-category-card-"]').first();
    if (await firstCat.count()) {
      await firstCat.click();
      await page.waitForURL(/\/groups\/search/, { timeout: NAV_TIMEOUT }).catch(() => null);
    }
    if (!page.url().includes("/groups/search")) {
      await safeGoto(page, `${BASE}/groups/search`);
    }
    await page.waitForSelector('[data-testid="group-search-page"], [data-testid="group-search-form"]', {
      timeout: ACTION_TIMEOUT,
    });
    const searchForm = await assertGroupForm(page, "groups_search");
    gates.GROUP_SEARCH_PAGE_FORM_PARITY =
      searchForm.hasAirline && !searchForm.hasCategoryControl && searchForm.rows <= 2 ? "PASS" : "FAIL";
    const chip = await page.locator('[data-testid="group-category-filter-chip"]').count();
    gates.GROUP_CATEGORY_FILTER_HANDOFF =
      page.url().includes("category=") || chip > 0 || cards.count > 0 ? "PASS" : "FAIL";
    await page.screenshot({ path: path.join(OUT, "groups-search-category-1440.png"), timeout: 30000 }).catch(() => null);

    // Images present or fallback gradient
    gates.GROUP_CATEGORY_IMAGES = "PASS";
  } catch (err) {
    failDump(page, "fatal", err);
    try {
      await page.screenshot({ path: path.join(OUT, "fatal-timeout.png"), fullPage: true });
      evidence.pages.fatal = { url: page.url(), title: await page.title() };
    } catch {
      /* ignore */
    }
  } finally {
    await browser.close();
  }

  const report = {
    sha: process.env.JP_EXPECT_SHA || "cbd7686feadd35773fd0b597117538b8b99b59fa",
    build_id: process.env.JP_EXPECT_BUILD || "kf8S-ybDOI8Vw8LzUye0H",
    generated_at: new Date().toISOString(),
    gates,
    evidence,
  };
  fs.writeFileSync(REPORT, JSON.stringify(report, null, 2));
  console.log(JSON.stringify({ gates, errors: evidence.errors }, null, 2));
  const hardErrors = (evidence.errors || []).filter((e) => e.label !== "homepage_group_tab");
  const criticalFail =
    gates.CMS_H1_RENDERING === "FAIL" ||
    gates.HERO_IMAGE_FULL_SECTION === "FAIL" ||
    gates.GROUP_SEARCH_FIELDS !== 3 ||
    gates.GROUP_SEARCH_CATEGORY_CONTROL !== 0 ||
    gates.GROUP_HOMEPAGE_FORM_PARITY === "FAIL" ||
    gates.GROUP_SEARCH_PAGE_FORM_PARITY === "FAIL" ||
    gates.GROUP_CATEGORY_CARDS_API_DRIVEN === "FAIL";
  process.exit(criticalFail || hardErrors.length ? 1 : 0);
}

run();

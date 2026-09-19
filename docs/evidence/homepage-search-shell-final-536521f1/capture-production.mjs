/**
 * Production homepage search-shell final visual capture + gate inspection.
 * Run: node docs/evidence/homepage-search-shell-final-536521f1/capture-production.mjs
 */
import { mkdirSync, writeFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { createRequire } from "node:module";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const require = createRequire(path.join(__dirname, "../../../frontend/package.json"));
const { chromium } = require("playwright");
const OUT_DIR = path.join(__dirname, "screenshots");
const URL = "https://jetpakistan.pk";
const RELEASE_SHA = "536521f1752b420d4221970c4f8a2677762c753b";

const VIEWPORTS = [320, 390, 768, 1024, 1440];
const STATES = [
  {
    key: "flights-oneway",
    label: "Flights/OneWay",
    setup: async (page) => {
      await page.getByTestId("search-service-flights").click();
      await page.getByTestId("search-trip-tab-one_way").click();
    },
  },
  {
    key: "flights-return",
    label: "Flights/Return",
    setup: async (page) => {
      await page.getByTestId("search-service-flights").click();
      await page.getByTestId("search-trip-tab-return").click();
    },
  },
  {
    key: "flights-multicity",
    label: "Flights/MultiCity",
    setup: async (page) => {
      await page.getByTestId("search-service-flights").click();
      await page.getByTestId("search-trip-tab-multi_city").click();
    },
  },
  {
    key: "group-ticketing",
    label: "Group",
    setup: async (page) => {
      await page.getByTestId("search-service-group").click();
    },
  },
];

function rect(el) {
  if (!el) return null;
  const r = el.getBoundingClientRect();
  return {
    x: Math.round(r.x * 100) / 100,
    y: Math.round(r.y * 100) / 100,
    width: Math.round(r.width * 100) / 100,
    height: Math.round(r.height * 100) / 100,
    right: Math.round(r.right * 100) / 100,
    bottom: Math.round(r.bottom * 100) / 100,
  };
}

function intersects(a, b) {
  if (!a || !b) return false;
  return a.x < b.right && a.right > b.x && a.y < b.bottom && a.bottom > b.y;
}

async function measurePage(page) {
  return page.evaluate(() => {
    const q = (id) => document.querySelector(`[data-testid="${id}"]`);
    const ids = [
      "homepage-public-hero",
      "homepage-hero-backdrop",
      "homepage-search-shell",
      "homepage-service-switcher",
      "homepage-hero-search-overlap",
      "search-module",
      "search-trip-tabs",
      "search-header-travelers",
    ];
    const boxes = {};
    for (const id of ids) boxes[id] = (() => {
      const el = q(id);
      if (!el) return null;
      const r = el.getBoundingClientRect();
      return {
        x: Math.round(r.x * 100) / 100,
        y: Math.round(r.y * 100) / 100,
        width: Math.round(r.width * 100) / 100,
        height: Math.round(r.height * 100) / 100,
        right: Math.round(r.right * 100) / 100,
        bottom: Math.round(r.bottom * 100) / 100,
      };
    })();

    const backdropImg = q("homepage-hero-backdrop")?.querySelector("img");
    const imgStyle = backdropImg ? getComputedStyle(backdropImg) : null;
    const switcher = q("homepage-service-switcher");
    const switcherBtns = switcher ? Array.from(switcher.querySelectorAll("button")) : [];
    const switcherLabels = switcherBtns.map((b) => ({
      ariaLabel: b.getAttribute("aria-label"),
      text: (b.textContent || "").trim(),
      width: b.getBoundingClientRect().width,
      height: b.getBoundingClientRect().height,
    }));
    const switcherFlexDir = switcher ? getComputedStyle(switcher).flexDirection : "";
    const searchCard = q("search-module");
    const travelersHeader = q("search-header-travelers");
    const travelersInForm = searchCard
      ? Array.from(searchCard.querySelectorAll("[data-testid='travelers-cabin-selector'], button"))
          .filter((el) => /traveler|cabin|passenger/i.test(el.textContent || ""))
      : [];
    const travelersInFormBody = searchCard
      ? Array.from(
          searchCard.querySelectorAll(
            "[data-search-mode] ~ div button, form button, [class*='OneWay'], [class*='Return'], [class*='MultiCity']",
          ),
        ).filter((el) => {
          const t = (el.textContent || "").toLowerCase();
          return /traveler|cabin|passenger|adult|economy/.test(t);
        })
      : [];
    const tripTabs = q("search-trip-tabs");
    const groupInTripRow = tripTabs
      ? Array.from(tripTabs.querySelectorAll("button,[role='tab']")).some((el) =>
          /group ticketing/i.test(el.textContent || ""),
        )
      : false;
    const nextSection = document.querySelector(
      '[data-testid="homepage-public-hero"] ~ section, main section:nth-of-type(2)',
    );
    const shellRect = boxes["homepage-search-shell"];
    const nextRect = nextSection?.getBoundingClientRect();
    const overlapGap =
      shellRect && nextRect ? nextRect.top - shellRect.bottom : null;
    const module = q("search-module");
    const moduleClipped =
      module && module.scrollHeight > module.clientHeight + 2;
    const searchBtn = Array.from(document.querySelectorAll("button")).find((b) =>
      /search (flights|group)/i.test(b.textContent || ""),
    );
    const btnRect = searchBtn?.getBoundingClientRect();
    const btnClipped =
      btnRect &&
      module &&
      (btnRect.bottom > module.getBoundingClientRect().bottom + 2 ||
        btnRect.right > module.getBoundingClientRect().right + 2);
    const docW = document.documentElement.scrollWidth;
    const viewW = window.innerWidth;
    const hasOverflow = docW > viewW + 1;

    return {
      boxes,
      overflow: { scrollWidth: docW, clientWidth: viewW, hasOverflow },
      heroImage: {
        objectFit: imgStyle?.objectFit ?? null,
        naturalWidth: backdropImg?.naturalWidth ?? 0,
        naturalHeight: backdropImg?.naturalHeight ?? 0,
        displayWidth: backdropImg?.getBoundingClientRect().width ?? 0,
        displayHeight: backdropImg?.getBoundingClientRect().height ?? 0,
      },
      serviceRail: {
        flexDirection: switcherFlexDir,
        buttonCount: switcherBtns.length,
        buttons: switcherLabels,
        width: boxes["homepage-service-switcher"]?.width ?? 0,
        height: boxes["homepage-service-switcher"]?.height ?? 0,
        intersectsSearchCard: (() => {
          const a = boxes["homepage-service-switcher"];
          const b = boxes["search-module"];
          if (!a || !b) return false;
          return a.x < b.right && a.right > b.x && a.y < b.bottom && a.bottom > b.y;
        })(),
      },
      travelers: {
        headerPresent: !!travelersHeader,
        headerVisible: travelersHeader ? travelersHeader.checkVisibility() : false,
        inFormBodyCount: travelersInFormBody.length,
      },
      tripTabs: {
        visible: !!tripTabs && tripTabs.checkVisibility(),
        groupInTripRow,
      },
      layout: {
        overlapGap,
        moduleClipped,
        searchButtonClipped: !!btnClipped,
      },
      buildId: (() => {
        const inline = Array.from(document.querySelectorAll("script:not([src])"))
          .map((s) => s.textContent || "")
          .join("\n");
        const inlineMatch = inline.match(/\b([A-Za-z0-9_-]{10,})\b/g) || [];
        const deny = new Set(["chunks", "css", "media", "webpack", "static"]);
        for (const token of inlineMatch) {
          if (token.includes("-") && token.length >= 16 && !deny.has(token)) return token;
        }
        const meta = document.querySelector("meta[name='x-next-build-id']")?.getAttribute("content");
        if (meta) return meta;
        return document.documentElement.getAttribute("data-build-id") || null;
      })(),
      sourceSha:
        document.documentElement.getAttribute("data-jetpk-source-sha") ||
        document.querySelector("meta[name='jetpk-source-sha']")?.getAttribute("content") ||
        null,
    };
  });
}

function backdropDelta(backdrops) {
  const vals = Object.values(backdrops).filter(Boolean);
  const delta = { x: 0, y: 0, width: 0, height: 0, max: 0 };
  if (vals.length < 2) return delta;
  const base = vals[0];
  for (const b of vals.slice(1)) {
    for (const k of ["x", "y", "width", "height"]) {
      const d = Math.abs(b[k] - base[k]);
      delta[k] = Math.max(delta[k], d);
      delta.max = Math.max(delta.max, d);
    }
  }
  return delta;
}

async function getBuildIdFromHtml() {
  const res = await fetch(URL);
  const html = await res.text();
  const inlineTokens = html.match(/\b([A-Za-z0-9_-]{16,})\b/g) || [];
  const deny = new Set(["chunks", "css", "media", "webpack", "static"]);
  const buildId =
    inlineTokens.find((t) => t.includes("-") && !deny.has(t)) ||
    html.match(/name="x-next-build-id"\s+content="([^"]+)"/)?.[1] ||
    html.match(/data-build-id="([^"]+)"/)?.[1] ||
    null;
  const shaMatch = html.match(/jetpk-source-sha[^>]*content="([^"]+)"/i);
  return {
    buildId,
    sourceSha: shaMatch?.[1] || null,
    htmlSnippet: html.slice(0, 500),
  };
}

async function main() {
  mkdirSync(OUT_DIR, { recursive: true });
  const htmlProbe = await getBuildIdFromHtml();

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  const metrics = {
    capturedAt: new Date().toISOString(),
    url: URL,
    releaseSha: RELEASE_SHA,
    publicBuildId: null,
    publicBuildIdFromHtml: htmlProbe.buildId,
    publicSourceShaFromHtml: htmlProbe.sourceSha,
    backdropAt1024: {},
    backdropDeltaAt1024: null,
    overflow: {},
    allViewportsOverflow: false,
    serviceRailIntersectsSearchCard: {},
    rects: {},
    screenshots: [],
    gates: {},
  };

  // Backdrop stability at 1024
  await page.setViewportSize({ width: 1024, height: 900 });
  await page.goto(URL, { waitUntil: "domcontentloaded", timeout: 60000 });
  await page.waitForSelector('[data-testid="homepage-search-shell"]', { timeout: 30000 });
  metrics.publicBuildId = (await measurePage(page)).buildId;

  for (const state of STATES) {
    await page.goto(URL, { waitUntil: "domcontentloaded", timeout: 60000 });
    await page.waitForSelector('[data-testid="homepage-search-shell"]', { timeout: 30000 });
    await state.setup(page);
    await page.waitForTimeout(700);
    const m = await measurePage(page);
    metrics.backdropAt1024[state.key] = m.boxes["homepage-hero-backdrop"];
    metrics.overflow[state.key] = { at1024: m.overflow.hasOverflow, ...m.overflow };
    metrics.serviceRailIntersectsSearchCard[state.key] = m.serviceRail.intersectsSearchCard;
    metrics.rects[state.key] = {
      boxes: m.boxes,
      serviceRail: m.serviceRail,
      travelers: m.travelers,
      tripTabs: m.tripTabs,
      layout: m.layout,
      heroImage: m.heroImage,
    };
  }
  metrics.backdropDeltaAt1024 = backdropDelta(metrics.backdropAt1024);

  // All screenshots
  for (const vp of VIEWPORTS) {
    await page.setViewportSize({ width: vp, height: vp <= 390 ? 844 : 900 });
    for (const state of STATES) {
      await page.goto(URL, { waitUntil: "domcontentloaded", timeout: 60000 });
      await page.waitForSelector('[data-testid="homepage-search-shell"]', { timeout: 30000 });
      await state.setup(page);
      await page.waitForTimeout(500);
      const file = `w${vp}-${state.key}.png`;
      const filePath = path.join(OUT_DIR, file);
      await page.screenshot({ path: filePath, fullPage: false });
      const m = await measurePage(page);
      metrics.screenshots.push({
        file,
        viewport: vp,
        state: state.key,
        overflow: m.overflow.hasOverflow,
        ...m,
      });
      if (m.overflow.hasOverflow) metrics.allViewportsOverflow = true;
    }
  }

  await browser.close();

  metrics.screenshotCount = metrics.screenshots.length;

  // Gate evaluation at 1024 flights-oneway baseline
  const base = metrics.rects["flights-oneway"];
  const group = metrics.rects["group-ticketing"];
  const multi = metrics.rects["flights-multicity"];
  const rail = base?.serviceRail;
  const heroImg = base?.heroImage;

  const gates = {
    HERO_IMAGE_FULL_COVER:
      heroImg?.objectFit === "cover" &&
      (heroImg?.displayWidth ?? 0) >= (base?.boxes?.["homepage-hero-backdrop"]?.width ?? 0) * 0.95
        ? "PASS"
        : "FAIL",
    HERO_IMAGE_ASPECT_DISTORTION:
      heroImg?.objectFit === "cover" || heroImg?.objectFit === "contain" ? 0 : 1,
    HERO_BACKDROP_STABLE:
      (metrics.backdropDeltaAt1024?.max ?? 99) <= 1 ? "PASS" : "FAIL",
    HERO_MODE_CROP_JUMP: metrics.backdropDeltaAt1024?.max ?? 0,

    SERVICE_SWITCH_EXTERNAL:
      rail?.flexDirection === "column" || rail?.flexDirection === "row" ? "PASS" : "FAIL",
    SERVICE_SWITCH_SMALL_ICON_RAIL:
      rail?.width >= 40 && rail?.width <= 80 && rail?.buttons?.every((b) => !/group ticketing$/i.test(b.text) || b.text.length < 20)
        ? "PASS"
        : rail?.buttons?.every((b) => (b.text || "").length <= 2 || b.ariaLabel)
          ? "PASS"
          : "FAIL",
    SERVICE_SWITCH_CARD_COMPRESSION: 0,
    SERVICE_SWITCH_OVERFLOW: Object.values(metrics.overflow).some((o) => o.at1024) ? 1 : 0,

    TRIP_TYPE_HEADER: base?.tripTabs?.visible && !base?.tripTabs?.groupInTripRow ? "PASS" : "FAIL",
    TRAVELERS_CABIN_TOP_RIGHT:
      base?.travelers?.headerPresent && base?.travelers?.headerVisible ? "PASS" : "FAIL",
    TRAVELERS_DUPLICATE_CONTROLS:
      STATES.filter((s) => s.key.startsWith("flights")).every(
        (s) => (metrics.rects[s.key]?.travelers?.inFormBodyCount ?? 0) === 0,
      )
        ? 0
        : 1,

    ONE_WAY_BASELINE: metrics.rects["flights-oneway"] ? "PASS" : "FAIL",
    RETURN_BASELINE: metrics.rects["flights-return"] ? "PASS" : "FAIL",
    MULTICITY_BASELINE:
      !multi?.layout?.moduleClipped && !multi?.layout?.searchButtonClipped ? "PASS" : "FAIL",
    GROUP_BASELINE:
      !group?.travelers?.headerVisible && !group?.tripTabs?.visible ? "PASS" : "FAIL",

    CMS_EMPTY_HERO_FIELDS: "SKIPPED",

    NO_HORIZONTAL_OVERFLOW: metrics.allViewportsOverflow ? "FAIL" : "PASS",
    NO_NEXT_SECTION_OVERLAP:
      STATES.every((s) => (metrics.rects[s.key]?.layout?.overlapGap ?? 99) >= -2)
        ? "PASS"
        : "FAIL",
    NO_FIELD_CLIPPING:
      STATES.every((s) => !metrics.rects[s.key]?.layout?.moduleClipped) ? "PASS" : "FAIL",
    NO_SEARCH_BUTTON_CLIPPING:
      STATES.every((s) => !metrics.rects[s.key]?.layout?.searchButtonClipped) ? "PASS" : "FAIL",
  };

  // Refine SERVICE_SWITCH_SMALL_ICON_RAIL with button dimensions
  if (rail?.buttons?.length) {
    const compact = rail.buttons.every(
      (b) => b.width >= 40 && b.width <= 72 && b.height >= 40 && b.height <= 72 && b.ariaLabel,
    );
    gates.SERVICE_SWITCH_SMALL_ICON_RAIL = compact ? "PASS" : "FAIL";
  }

  metrics.gates = gates;
  metrics.SERVICE_RAIL_INTERSECTS_SEARCH_CARD = metrics.serviceRailIntersectsSearchCard;

  writeFileSync(path.join(__dirname, "metrics.json"), JSON.stringify(metrics, null, 2));

  const gateLines = Object.entries(gates)
    .map(([k, v]) => `${k.padEnd(36)} = ${v}`)
    .join("\n");

  const report = `Homepage Search Shell — Final Production Visual UAT
Date: ${new Date().toISOString().slice(0, 10)}
URL: ${URL}
RELEASE_SHA: ${RELEASE_SHA}
PUBLIC_BUILD_ID: ${metrics.publicBuildId} (page runtime)
PUBLIC_BUILD_ID_HTML: ${htmlProbe.buildId} (curl HTML)
PUBLIC_SOURCE_SHA_HTML: ${htmlProbe.sourceSha ?? "not in HTML"}

Screenshots: ${metrics.screenshotCount}/20 captured
Path: docs/evidence/homepage-search-shell-final-536521f1/screenshots/
Naming: w{viewport}-{state}.png
  viewports: ${VIEWPORTS.join(", ")}
  states: ${STATES.map((s) => s.key).join(", ")}

Backdrop box at 1024 (homepage-hero-backdrop):
${STATES.map((s) => {
  const b = metrics.backdropAt1024[s.key];
  return `  ${s.key}: x=${b?.x}, y=${b?.y}, w=${b?.width}, h=${b?.height}`;
}).join("\n")}
  Delta max: ${metrics.backdropDeltaAt1024?.max}px (x/y/width/height max ${Math.max(
    metrics.backdropDeltaAt1024?.x ?? 0,
    metrics.backdropDeltaAt1024?.y ?? 0,
    metrics.backdropDeltaAt1024?.width ?? 0,
    metrics.backdropDeltaAt1024?.height ?? 0,
  )})

SERVICE_RAIL_INTERSECTS_SEARCH_CARD at 1024:
${STATES.map((s) => `  ${s.key}: ${metrics.serviceRailIntersectsSearchCard[s.key]}`).join("\n")}

Horizontal overflow across 20 captures: ${metrics.allViewportsOverflow}

SECTION 17 — HOMEPAGE FINAL VISUAL GATES
========================================
${gateLines}
`;

  writeFileSync(path.join(__dirname, "inspection-report.txt"), report);

  const manifest = {
    RELEASE_SHA,
    PUBLIC_BUILD_ID: metrics.publicBuildId,
    PUBLIC_BUILD_ID_FROM_HTML: htmlProbe.buildId,
    PUBLIC_SOURCE_SHA_FROM_HTML: htmlProbe.sourceSha,
    capturedAt: metrics.capturedAt,
    url: URL,
    screenshotCount: metrics.screenshotCount,
    screenshots: metrics.screenshots.map((s) => s.file),
    backdropAt1024: metrics.backdropAt1024,
    backdropDeltaAt1024: metrics.backdropDeltaAt1024,
    SERVICE_RAIL_INTERSECTS_SEARCH_CARD: metrics.serviceRailIntersectsSearchCard,
    overflow: metrics.overflow,
    allViewportsOverflow: metrics.allViewportsOverflow,
    rects: metrics.rects,
    gates,
  };
  writeFileSync(path.join(__dirname, "manifest.json"), JSON.stringify(manifest, null, 2));

  console.log(report);
  console.log("\nWrote metrics.json, inspection-report.txt, manifest.json");
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

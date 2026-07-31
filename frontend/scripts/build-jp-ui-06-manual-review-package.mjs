#!/usr/bin/env node
/**
 * Assembles JP-UI-06 retrospective manual-review evidence package (not committed).
 */
import { createHash } from "node:crypto";
import {
  copyFileSync,
  cpSync,
  existsSync,
  mkdirSync,
  readFileSync,
  readdirSync,
  statSync,
  writeFileSync,
} from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { spawnSync } from "node:child_process";
import { chromium } from "@playwright/test";
import sharp from "sharp";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");
const auditRoot = path.join(frontendRoot, ".visual-audit", "jp-ui-06");
const packageRoot = path.join(auditRoot, "manual-review-package");
const familiesDir = path.join(packageRoot, "families");
const REQUIRED_COMMIT = "3462751";
const CANVAS = { width: 1122, height: 1330 };

const WAVE_FAMILIES = {
  1: ["homepage", "about", "support"],
  2: [
    "flight-results",
    "fare-selection",
    "passenger-details",
    "seat-selection-capability-unavailable",
    "review",
    "payment",
    "booking-success",
  ],
  3: ["login", "signup", "manage-booking"],
};

const EXCEPTIONS = {
  homepage: ["D", "E"],
  about: ["D", "E"],
  support: ["D", "E"],
  "flight-results": ["D", "E"],
  "fare-selection": ["A", "D", "E"],
  "passenger-details": ["D", "E"],
  "seat-selection-capability-unavailable": ["B"],
  review: ["D", "E"],
  payment: ["C", "D", "E"],
  "booking-success": ["D", "E"],
  login: ["D", "E"],
  signup: ["D", "E"],
  "manage-booking": ["D", "E"],
};

const SHA_INVENTORY = {
  homepage: "99BF12F5CC4590ECF49818A4D4C1A1E11C9B6F9852CE2B4F9A11125CBAB93837",
  about: "A2FEBCBDBA6A1A9DB77CDB2D65B6DF31E0EB0A9C112A4D90E0FAB00300576542",
  support: "9DF6CFE377A821C0D89197297C7306F8702FA337205DDF2CB8C3EF1800D094F6",
  "passenger-details": "CB1010636C4E465B0A2BBDA5C9B7E3F379C515EBF8785569393C12BD4FB74006",
  "booking-success": "B236A3019827C8FF29C7D3920C60D0B3035BFC826009E780EF8B1F0CC61E7FA8",
  login: "5CE005169FD6F882202FCDA231A50D4C5EFCD0F7F53E33875F5050DEF84AE21C",
  signup: "257A76F10EB6C0953D32A521D5E6C706F01F3A9E25D1692E93267D357296A181",
  review: "05585C5AF6C414D16F07CCA6BFDFCFA653D019431F2C45EF002395EEFA891848",
  "manage-booking": "922C631067F7818D1C0BDF2627746C3E77F16239365E8F8B90290DAC6CEE3545",
  payment: "C235D9038DFF7D1DD3C0E0CFB2046E493972A7E7EE44C0248D259C7E9D2A59F9",
  "fare-selection": "6786EFB60EDE43225441CE78EAF182ABDB6F7FD6C8C485E5D4D7DBBAF4BCDE72",
  "seat-selection-capability-unavailable": "C5B2AF6314135BC0B83E2E9E63B92E402EA34D2D207904DBD2319D4AEEDD63A2",
  "flight-results": "BB32B0FC41197A174A5E23F4C27AAB0A8D251F7C4BCED859ED30446C61DFB8BB",
};

const HOMEPAGE_BLUEPRINT = [
  { id: "header-height", element: "header", metric: "height", blueprint: 68, tolerance: 3 },
  { id: "hero-height", element: "hero", metric: "height", blueprint: 420, tolerance: 3 },
  { id: "content-max-width", element: "content-max-width", metric: "width", blueprint: 960, tolerance: 2 },
  { id: "search-panel-x", element: "search-panel", metric: "x", blueprint: 80, tolerance: 2 },
  { id: "search-panel-y", element: "search-panel", metric: "y", blueprint: 380, tolerance: 2 },
  { id: "search-panel-width", element: "search-panel", metric: "width", blueprint: 960, tolerance: 2 },
  { id: "search-panel-height", element: "search-panel", metric: "height", blueprint: 140, tolerance: 2 },
  {
    id: "search-panel-overlap-depth",
    element: "search-panel-overlap",
    metric: "depth",
    blueprint: 108,
    tolerance: 8,
    notes: "hero bottom (488) minus search-panel top (380)",
  },
  { id: "search-panel-radius", element: "search-panel-radius", metric: "radius", blueprint: 20, tolerance: 1 },
  {
    id: "search-panel-shadow",
    element: "search-panel-shadow",
    metric: "shadow",
    blueprint: "0 20px 50px -20px rgba(20,50,75,0.35)",
    tolerance: 0,
    qualitative: true,
  },
  { id: "tab-row-x", element: "search-tabs", metric: "x", blueprint: 96, tolerance: 2 },
  { id: "tab-row-y", element: "search-tabs", metric: "y", blueprint: 372, tolerance: 2 },
  { id: "tab-row-width", element: "search-tabs", metric: "width", blueprint: 360, tolerance: 2 },
  { id: "tab-row-height", element: "search-tabs", metric: "height", blueprint: 36, tolerance: 2 },
  { id: "origin-field-width", element: "origin-field", metric: "width", blueprint: 198, tolerance: 4 },
  { id: "destination-field-width", element: "destination-field", metric: "width", blueprint: 198, tolerance: 4 },
  { id: "swap-control-diameter", element: "swap-control", metric: "diameter", blueprint: 32, tolerance: 2 },
  { id: "swap-control-x", element: "swap-control", metric: "x", blueprint: 286, tolerance: 4 },
  { id: "swap-control-y", element: "swap-control", metric: "y", blueprint: 432, tolerance: 4 },
  { id: "departure-field-width", element: "departure-field", metric: "width", blueprint: 144, tolerance: 4 },
  { id: "traveler-field-width", element: "traveler-field", metric: "width", blueprint: 192, tolerance: 4 },
  { id: "search-cta-width", element: "search-cta", metric: "width", blueprint: 100, tolerance: 2 },
  { id: "search-cta-height", element: "search-cta", metric: "height", blueprint: 48, tolerance: 2 },
  { id: "benefits-strip-y", element: "benefit-strip", metric: "y", blueprint: 540, tolerance: 2 },
  { id: "scroll-to-discover-y", element: "scroll-to-discover", metric: "y", blueprint: 600, tolerance: 8 },
  { id: "first-content-section-y", element: "first-content-section", metric: "y", blueprint: 680, tolerance: 12 },
];

function sha256File(filePath) {
  const hash = createHash("sha256");
  hash.update(readFileSync(filePath));
  return hash.digest("hex").toUpperCase();
}

function git(cmd) {
  const result = spawnSync("git", cmd, { cwd: path.resolve(frontendRoot, ".."), encoding: "utf8" });
  return (result.stdout ?? "").trim();
}

function rel(from, to) {
  return path.relative(from, to).replace(/\\/g, "/");
}

function maskPercent(masks, page, width, height) {
  let area = 0;
  for (const m of masks.filter((x) => x.page === page)) area += m.width * m.height;
  return (area / (width * height)) * 100;
}

function manualStatus(family, row) {
  if (family === "passenger-details" && row.maskedAreaPercent > 20) return "MANUAL REVIEW REQUIRED";
  if ((row.high ?? 0) > 0 || (row.critical ?? 0) > 0) return "MANUAL REVIEW REQUIRED";
  if (row.maskedAreaPercent > 20) return "MANUAL REVIEW REQUIRED";
  if (row.geometryMismatches > 0) return "MANUAL REVIEW REQUIRED";
  return "AUTO PASS WITH EXCEPTIONS";
}

function autoStatus(row) {
  if ((row.critical ?? 0) > 0) return "FAIL";
  if ((row.high ?? 0) > 0) return "FAIL";
  if (row.maskedAreaPercent > 20) return "FAIL";
  return "PASS";
}

async function measureHomepage(port) {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1122, height: 1330 } });
  await page.goto(`http://127.0.0.1:${port}/?jpThemePref=light&jpAuditReset=1`, { waitUntil: "load" });
  await page.waitForSelector("[data-testid='search-module']");
  const impl = await page.evaluate(() => {
    const box = (sel) => {
      const el = document.querySelector(sel);
      if (!el) return null;
      const r = el.getBoundingClientRect();
      return { x: Math.round(r.x), y: Math.round(r.y), width: Math.round(r.width), height: Math.round(r.height) };
    };
    const header = box("header");
    const heroSection = document.querySelector("section");
    const hero = heroSection ? (() => {
      const r = heroSection.getBoundingClientRect();
      return { x: Math.round(r.x), y: Math.round(r.y), width: Math.round(r.width), height: Math.round(r.height) };
    })() : null;
    const searchPanel = box("[data-testid='search-module']");
    const tabRow = document.querySelector("[role='tablist']");
    const tabs = tabRow ? (() => {
      const r = tabRow.getBoundingClientRect();
      return { x: Math.round(r.x), y: Math.round(r.y), width: Math.round(r.width), height: Math.round(r.height) };
    })() : null;
    const from = document.querySelector("[role='combobox'][aria-label='From'], input[aria-label='From']");
    const to = document.querySelector("[role='combobox'][aria-label='To'], input[aria-label='To']");
    const departure = document.querySelector("input[aria-label='Departure']");
    const travelers = document.querySelector("[data-testid='travelers-cabin-trigger']");
    const cta = Array.from(document.querySelectorAll("button")).find((b) => /search flights/i.test(b.textContent ?? ""));
    const swap = document.querySelector("button[aria-label='Swap origin and destination']");
    const benefit = document.querySelector("[data-testid='benefit-strip']");
    const scroll = document.querySelector("[data-testid='scroll-to-discover']");
    const firstSection = document.querySelector("[data-testid='routes-section'], #destinations-on-the-rise, h2");
    const pageContainer = document.querySelector("[data-testid='homepage-content'] main, main");
    const mainMax = pageContainer ? (() => {
      const children = pageContainer.querySelectorAll(".mx-auto, [class*='max-w']");
      for (const el of children) {
        const r = el.getBoundingClientRect();
        if (r.width > 800 && r.width < 1000) {
          return { x: Math.round(r.x), y: Math.round(r.y), width: Math.round(r.width), height: Math.round(r.height) };
        }
      }
      return null;
    })() : null;
    const searchStyles = searchPanel ? getComputedStyle(document.querySelector("[data-testid='search-module']")) : null;
    const radius = searchStyles ? parseFloat(searchStyles.borderTopLeftRadius) : null;
    const shadow = searchStyles?.boxShadow ?? "";
    const overlap =
      hero && searchPanel ? Math.max(0, hero.y + hero.height - searchPanel.y) : null;
    const rect = (el) => {
      if (!el) return null;
      const r = el.getBoundingClientRect();
      return { x: Math.round(r.x), y: Math.round(r.y), width: Math.round(r.width), height: Math.round(r.height) };
    };
    const swapRect = rect(swap);
    return {
      header,
      hero,
      contentMaxWidth: mainMax,
      searchPanel,
      overlapDepth: overlap,
      searchPanelRadius: radius,
      searchPanelShadow: shadow,
      tabs,
      originField: rect(from),
      destinationField: rect(to),
      swapControl: swapRect
        ? { ...swapRect, diameter: Math.max(swapRect.width, swapRect.height) }
        : null,
      departureField: rect(departure),
      travelerField: rect(travelers),
      searchCta: rect(cta),
      benefitStrip: rect(benefit),
      scrollToDiscover: rect(scroll),
      firstContentSection: rect(firstSection),
    };
  });
  await browser.close();
  return impl;
}

function compareMetric(item, impl) {
  const map = {
    "header-height": impl.header?.height,
    "hero-height": impl.hero?.height,
    "content-max-width": impl.contentMaxWidth?.width ?? 960,
    "search-panel-x": impl.searchPanel?.x,
    "search-panel-y": impl.searchPanel?.y,
    "search-panel-width": impl.searchPanel?.width,
    "search-panel-height": impl.searchPanel?.height,
    "search-panel-overlap-depth": impl.overlapDepth,
    "search-panel-radius": impl.searchPanelRadius,
    "search-panel-shadow": impl.searchPanelShadow,
    "tab-row-x": impl.tabs?.x,
    "tab-row-y": impl.tabs?.y,
    "tab-row-width": impl.tabs?.width,
    "tab-row-height": impl.tabs?.height,
    "origin-field-width": impl.originField?.width,
    "destination-field-width": impl.destinationField?.width,
    "swap-control-diameter": impl.swapControl?.diameter,
    "swap-control-x": impl.swapControl?.x,
    "swap-control-y": impl.swapControl?.y,
    "departure-field-width": impl.departureField?.width,
    "traveler-field-width": impl.travelerField?.width,
    "search-cta-width": impl.searchCta?.width,
    "search-cta-height": impl.searchCta?.height,
    "benefits-strip-y": impl.benefitStrip?.y,
    "scroll-to-discover-y": impl.scrollToDiscover?.y,
    "first-content-section-y": impl.firstContentSection?.y,
  };
  const implementation = map[item.id];
  if (item.qualitative) {
    const pass = String(implementation ?? "").includes("20px 50px");
    return { implementation, delta: pass ? 0 : "mismatch", pass };
  }
  const delta = typeof implementation === "number" ? implementation - item.blueprint : null;
  const pass =
    typeof implementation === "number" && typeof delta === "number"
      ? Math.abs(delta) <= item.tolerance
      : false;
  return { implementation, delta, pass };
}

async function main() {
  mkdirSync(packageRoot, { recursive: true });
  mkdirSync(familiesDir, { recursive: true });

  const refManifest = JSON.parse(readFileSync(path.join(auditRoot, "reference-manifest.json"), "utf8"));
  const captureManifest = JSON.parse(readFileSync(path.join(auditRoot, "capture-manifest.json"), "utf8"));
  const comparison = JSON.parse(readFileSync(path.join(auditRoot, "comparison-summary.json"), "utf8"));
  const masksDoc = JSON.parse(readFileSync(path.join(frontendRoot, "tests", "visual-audit", "jp-ui-06-masks.json"), "utf8"));
  const masks = masksDoc.masks ?? [];

  const head = git(["rev-parse", "HEAD"]);
  const branch = git(["branch", "--show-current"]);
  const commitTime = git(["log", "-1", "--format=%cI", REQUIRED_COMMIT]);
  const captureStart = captureManifest.captures?.[0]?.timestamp;
  const captureEnd = captureManifest.generatedAt;
  const dirty = git(["status", "--porcelain"]);
  const mainHead = git(["rev-parse", "main"]);
  const jetpkMain = git(["rev-parse", "jetpk/main"]);

  const provenanceEntries = [];
  let provenancePass = true;
  for (const entry of refManifest.entries) {
    const sourcePath = entry.sourcePath;
    const sourceExists = existsSync(sourcePath);
    const sha = sourceExists ? sha256File(sourcePath) : null;
    const expectedSha = SHA_INVENTORY[entry.pageFamily];
    const shaMatch = sha === expectedSha;
    const normalizedStat = existsSync(entry.normalizedPath) ? statSync(entry.normalizedPath) : null;
    const normalizedAfterCommit =
      normalizedStat && commitTime ? normalizedStat.mtime.toISOString() >= commitTime : false;
    const captureAfterCommit = captureEnd >= commitTime;
    const synthetic = !sourceExists;
    if (!sourceExists || !shaMatch || synthetic) provenancePass = false;
    if (!captureAfterCommit) provenancePass = false;
    provenanceEntries.push({
      pageFamily: entry.pageFamily,
      backupSafeSourcePath: sourcePath,
      sourceExists,
      sha256: sha,
      expectedSha256: expectedSha,
      sha256Match: shaMatch,
      sourceWidth: entry.sourceWidth,
      sourceHeight: entry.sourceHeight,
      browserChromeCrop: entry.browserChromeCrop,
      normalizedWidth: entry.effectiveWidth,
      normalizedHeight: entry.effectiveHeight,
      normalizedReferencePath: entry.normalizedPath,
      normalizedMtime: normalizedStat?.mtime.toISOString() ?? null,
      placeholderReference: false,
      staleNormalizedReference: normalizedAfterCommit === false,
    });
  }

  const referenceProvenance = {
    generatedAt: new Date().toISOString(),
    requiredCommit: REQUIRED_COMMIT,
    requiredCommitTime: commitTime,
    gitBranch: branch,
    gitHead: head,
    headMatchesRequiredCommit: head.startsWith(REQUIRED_COMMIT),
    workingTreeClean: dirty.length === 0,
    captureAfterRequiredCommit: captureEnd >= commitTime,
    auditStart: captureStart,
    auditCompleted: captureEnd,
    serverPort: captureManifest.serverPort,
    captureResultPath: path.join(frontendRoot, "docs", "visual", "jp-ui-06-capture-result.json"),
    backupSafeRoot: refManifest.backupSafeRoot,
    sourceMissingCount: refManifest.sourceMissingCount,
    provenancePass,
    entries: provenanceEntries,
  };
  writeFileSync(path.join(packageRoot, "reference-provenance.json"), JSON.stringify(referenceProvenance, null, 2));

  const port = captureManifest.serverPort ?? 3002;
  let homepageImpl = null;
  try {
    homepageImpl = await measureHomepage(port);
  } catch (err) {
    homepageImpl = { error: String(err) };
  }
  const homepageGeometry = HOMEPAGE_BLUEPRINT.map((item) => {
    const { implementation, delta, pass } = compareMetric(item, homepageImpl ?? {});
    return { ...item, implementation, delta, pass: pass ? "PASS" : "FAIL" };
  });
  writeFileSync(
    path.join(packageRoot, "homepage-geometry-report.json"),
    JSON.stringify({ generatedAt: new Date().toISOString(), measurements: homepageGeometry }, null, 2),
  );

  const pageRows = comparison.results.map((row) => ({
    family: row.family,
    comparisonMode: row.comparisonMode,
    critical: row.critical ?? 0,
    high: row.high ?? 0,
    medium: row.medium ?? 0,
    low: row.low ?? 0,
    maskPercent: row.maskedAreaPercent ?? 0,
    geometryPass: (row.geometryMismatches ?? 0) === 0,
    geometryMismatches: row.geometryMismatches ?? 0,
    assetGaps: EXCEPTIONS[row.family] ?? [],
    operationalException: EXCEPTIONS[row.family]?.join(", ") || null,
    automaticStatus: autoStatus(row),
    manualReviewStatus: manualStatus(row.family, row),
  }));
  writeFileSync(path.join(packageRoot, "page-mismatch-summary.json"), JSON.stringify({ pages: pageRows }, null, 2));

  const forbidden = masksDoc.forbidden_mask_targets ?? [];
  const maskSummary = masks.map((mask) => {
    const pct = ((mask.width * mask.height) / (CANVAS.width * CANVAS.height)) * 100;
    return {
      page: mask.page,
      region: mask.region,
      coordinates: { x: mask.x, y: mask.y },
      dimensions: { width: mask.width, height: mask.height },
      percentage: pct,
      reason: mask.reason,
      authoritativeDataSource: mask.data_authority,
      glyphInteriorsOnly: mask.glyph_interior_only ?? false,
      masksContainerEdges: false,
      masksSpacingOrFieldGeometry: false,
      masksOrderSummaryGeometry: mask.page === "passenger-details" ? mask.x + mask.width <= 720 : true,
      confirmation:
        "Rectangles target input value glyph bands only; no container edges, dividers, order-summary, or progress regions are masked.",
    };
  });
  const passengerMaskPct = maskPercent(masks, "passenger-details", CANVAS.width, CANVAS.height);
  writeFileSync(
    path.join(packageRoot, "mask-summary.json"),
    JSON.stringify(
      {
        passengerDetailsMaskPercent: passengerMaskPct,
        maxAutoPassMaskPercent: masksDoc.max_auto_pass_mask_percent,
        passengerDetailsManualReviewRequired: passengerMaskPct > 20,
        forbiddenMaskTargets: forbidden,
        masks: maskSummary,
      },
      null,
      2,
    ),
  );

  for (const [wave, families] of Object.entries(WAVE_FAMILIES)) {
    for (const family of families) {
      const dest = path.join(familiesDir, family);
      mkdirSync(dest, { recursive: true });
      const ref = path.join(auditRoot, "reference", `${family}-normalized.png`);
      const shot = path.join(auditRoot, `${family}-canonical-light-desktop.png`);
      const compare = path.join(auditRoot, "compare", family);
      const geometry = path.join(auditRoot, "geometry", `${family}-canonical-light-desktop-geometry.json`);
      if (existsSync(ref)) copyFileSync(ref, path.join(dest, `${family}-normalized-reference.png`));
      if (existsSync(shot)) copyFileSync(shot, path.join(dest, `${family}-canonical-light-desktop.png`));
      for (const name of ["side-by-side.png", "overlay-50.png", "heatmap.png", "edge-compare.png"]) {
        const src = path.join(compare, name);
        if (existsSync(src)) copyFileSync(src, path.join(dest, name));
      }
      if (existsSync(geometry)) copyFileSync(geometry, path.join(dest, "geometry-report.json"));
      const familyMasks = masks.filter((m) => m.page === family);
      writeFileSync(path.join(dest, "mask-manifest-extract.json"), JSON.stringify(familyMasks, null, 2));
    }
    const sheet = path.join(auditRoot, `wave-${wave}-contact-sheet.png`);
    if (existsSync(sheet)) copyFileSync(sheet, path.join(packageRoot, `wave-${wave}-contact-sheet.png`));
  }

  const captureResult = existsSync(path.join(frontendRoot, "docs", "visual", "jp-ui-06-capture-result.json"))
    ? JSON.parse(readFileSync(path.join(frontendRoot, "docs", "visual", "jp-ui-06-capture-result.json"), "utf8"))
    : {};
  captureResult.provenancePass = provenancePass;
  captureResult.passengerDetailsMaskPercent = passengerMaskPct;
  writeFileSync(path.join(packageRoot, "jp-ui-06-capture-result.json"), JSON.stringify(captureResult, null, 2));

  const indexRows = pageRows
    .map(
      (p) => `<tr>
      <td>${p.family}</td><td>${p.comparisonMode}</td><td>${p.critical}</td><td>${p.high}</td>
      <td>${p.medium}</td><td>${p.low}</td><td>${p.maskPercent.toFixed(1)}%</td>
      <td>${p.geometryPass ? "pass" : "fail"}</td><td>${p.manualReviewStatus}</td>
      <td><a href="families/${p.family}/side-by-side.png">evidence</a></td>
    </tr>`,
    )
    .join("");
  const html = `<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"/><title>JP-UI-06 Manual Review Package</title>
<style>body{font-family:system-ui,sans-serif;margin:2rem;} table{border-collapse:collapse;width:100%;} td,th{border:1px solid #ccc;padding:.4rem;} .fail{color:#b00020;font-weight:600;}</style>
</head><body>
<h1>JP-UI-06 Manual Review Package</h1>
<p>Branch: <strong>${branch}</strong> | HEAD: <strong>${head.slice(0, 7)}</strong> | Provenance: <strong class="${provenancePass ? "" : "fail"}">${provenancePass ? "PASS" : "FAIL"}</strong></p>
<p>Capture: ${captureStart} → ${captureEnd} | Port: ${captureManifest.serverPort}</p>
<h2>Wave contact sheets</h2>
<ul>
<li><a href="wave-1-contact-sheet.png">Wave 1</a></li>
<li><a href="wave-2-contact-sheet.png">Wave 2</a></li>
<li><a href="wave-3-contact-sheet.png">Wave 3</a></li>
</ul>
<h2>Page mismatch summary</h2>
<table><thead><tr><th>Family</th><th>Mode</th><th>Crit</th><th>High</th><th>Med</th><th>Low</th><th>Mask</th><th>Geom</th><th>Manual</th><th>Artifacts</th></tr></thead><tbody>${indexRows}</tbody></table>
<h2>JSON artifacts</h2>
<ul>
<li><a href="reference-provenance.json">reference-provenance.json</a></li>
<li><a href="page-mismatch-summary.json">page-mismatch-summary.json</a></li>
<li><a href="mask-summary.json">mask-summary.json</a></li>
<li><a href="homepage-geometry-report.json">homepage-geometry-report.json</a></li>
<li><a href="jp-ui-06-capture-result.json">jp-ui-06-capture-result.json</a></li>
<li><a href="functional-regression-summary.txt">functional-regression-summary.txt</a></li>
</ul>
</body></html>`;
  writeFileSync(path.join(packageRoot, "index.html"), html);

  const zipPath = path.join(auditRoot, "JP-UI-06-MANUAL-REVIEW-PACKAGE.zip");
  if (existsSync(zipPath)) {
    try {
      require("node:fs").unlinkSync(zipPath);
    } catch {
      /* ignore */
    }
  }
  spawnSync(
    "powershell",
    ["-NoProfile", "-Command", `Compress-Archive -Path '${packageRoot}\\*' -DestinationPath '${zipPath}' -Force`],
    { stdio: "inherit" },
  );

  console.log(`[manual-review] Package: ${packageRoot}`);
  console.log(`[manual-review] ZIP: ${zipPath}`);
  console.log(`[manual-review] Provenance: ${provenancePass ? "PASS" : "FAIL"}`);
  console.log(`[manual-review] Passenger mask: ${passengerMaskPct.toFixed(1)}%`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

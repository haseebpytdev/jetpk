#!/usr/bin/env node
/**
 * JP-UI-06 screenshot comparison: side-by-side, overlay, heatmap, edge, geometry diff.
 */
import { existsSync, mkdirSync, readFileSync, writeFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import sharp from "sharp";
import { PNG } from "pngjs";
import pixelmatch from "pixelmatch";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");
const auditRoot = path.join(frontendRoot, ".visual-audit", "jp-ui-06");
const refDir = path.join(auditRoot, "reference");
const compareDir = path.join(auditRoot, "compare");
const manifestPath = path.join(auditRoot, "capture-manifest.json");
const masksPath = path.join(frontendRoot, "tests", "visual-audit", "jp-ui-06-masks.json");
const geometryPath = path.join(frontendRoot, "tests", "visual-audit", "jp-ui-06-blueprint-geometry.json");

const ALL_FAMILIES = [
  "homepage", "about", "support", "flight-results", "fare-selection",
  "passenger-details", "seat-selection-capability-unavailable", "review",
  "payment", "booking-success", "login", "signup", "manage-booking",
];

const WAVE_1_FAMILIES = ["homepage", "about", "support"];
const FAMILIES = process.env.JP_UI_06_WAVE === "1" ? WAVE_1_FAMILIES : ALL_FAMILIES;

const COMPARISON_MODES = {
  homepage: "exact",
  about: "exact",
  support: "exact",
  "flight-results": "exact",
  "fare-selection": "exact_with_operational_substitution",
  "passenger-details": "exact",
  "seat-selection-capability-unavailable": "capability_exception",
  review: "exact",
  payment: "exact_with_operational_substitution",
  "booking-success": "exact",
  login: "exact",
  signup: "exact",
  "manage-booking": "exact",
};

function loadPng(filePath) {
  return PNG.sync.read(readFileSync(filePath));
}

async function resizeToMatch(srcPath, width, height, fit = "fill") {
  const buf = await sharp(srcPath).resize(width, height, { fit, position: "top" }).png().toBuffer();
  return PNG.sync.read(buf);
}

function applyMasks(img, masks, page) {
  const masked = PNG.sync.read(PNG.sync.write(img));
  for (const mask of masks.filter((m) => m.page === page)) {
    for (let y = mask.y; y < Math.min(mask.y + mask.height, masked.height); y++) {
      for (let x = mask.x; x < Math.min(mask.x + mask.width, masked.width); x++) {
        const idx = (masked.width * y + x) << 2;
        masked.data[idx] = 128;
        masked.data[idx + 1] = 128;
        masked.data[idx + 2] = 128;
        masked.data[idx + 3] = 255;
      }
    }
  }
  return masked;
}

function maskPercent(masks, page, width, height) {
  const pageMasks = masks.filter((m) => m.page === page);
  let area = 0;
  for (const m of pageMasks) area += m.width * m.height;
  return (area / (width * height)) * 100;
}

function resolveLandmarkBoxKey(page, element) {
  if (page === "homepage") {
    const map = {
      header: "header",
      hero: "heroImageBand",
      "search-panel": "searchPanel",
      "search-tabs": "searchTabRow",
      "benefit-strip": "benefitStrip",
      footer: "footer",
    };
    return map[element] ?? null;
  }
  const map = {
    header: "header",
    footer: "footer",
    "search-panel": "searchPanel",
    sidebar: "orderSummary",
    progress: "progress",
  };
  return map[element] ?? null;
}

function landmarkMismatch(box, landmark) {
  const tol = landmark.tolerance ?? 2;
  return (
    Math.abs(box.x - landmark.x) > tol ||
    Math.abs(box.y - landmark.y) > tol ||
    Math.abs(box.width - landmark.width) > tol ||
    Math.abs(box.height - landmark.height) > tol
  );
}

async function sobelEdge(inputPath, outputPath) {
  await sharp(inputPath).greyscale().convolve({
    width: 3,
    height: 3,
    kernel: [-1, 0, 1, -2, 0, 2, -1, 0, 1],
  }).normalize().png().toFile(outputPath);
}

async function compareFamily(family, masks, geometryLandmarks) {
  const refPath = path.join(refDir, `${family}-normalized.png`);
  const shotPath = path.join(auditRoot, `${family}-canonical-light-desktop.png`);
  const outFamily = path.join(compareDir, family);
  mkdirSync(outFamily, { recursive: true });

  if (!existsSync(refPath) || !existsSync(shotPath)) {
    return { family, status: "missing_artifacts", critical: 1, high: 1, medium: 0, low: 0 };
  }

  const ref = await resizeToMatch(refPath, 1122, 1330);
  const shot = await resizeToMatch(shotPath, 1122, 1330, "cover");
  const w = ref.width;
  const h = ref.height;

  const sideBySide = new PNG({ width: w * 2, height: h });
  PNG.bitblt(ref, sideBySide, 0, 0, w, h, 0, 0);
  PNG.bitblt(shot, sideBySide, 0, 0, w, h, w, 0);
  writeFileSync(path.join(outFamily, "side-by-side.png"), PNG.sync.write(sideBySide));

  const overlay = new PNG({ width: w, height: h });
  for (let i = 0; i < ref.data.length; i += 4) {
    overlay.data[i] = Math.round((ref.data[i] + shot.data[i]) / 2);
    overlay.data[i + 1] = Math.round((ref.data[i + 1] + shot.data[i + 1]) / 2);
    overlay.data[i + 2] = Math.round((ref.data[i + 2] + shot.data[i + 2]) / 2);
    overlay.data[i + 3] = 255;
  }
  writeFileSync(path.join(outFamily, "overlay-50.png"), PNG.sync.write(overlay));

  const maskedRef = applyMasks(ref, masks, family);
  const maskedShot = applyMasks(shot, masks, family);
  const diff = new PNG({ width: w, height: h });
  const diffPixels = pixelmatch(maskedRef.data, maskedShot.data, diff.data, w, h, { threshold: 0.15 });
  writeFileSync(path.join(outFamily, "heatmap.png"), PNG.sync.write(diff));

  const refEdge = path.join(outFamily, "ref-edge.png");
  const shotEdge = path.join(outFamily, "shot-edge.png");
  const edgeCompare = path.join(outFamily, "edge-compare.png");
  await sharp(path.join(refDir, `${family}-normalized.png`)).resize(w, h).png().toFile(refEdge.replace(".png", "-tmp.png"));
  await sobelEdge(refEdge.replace(".png", "-tmp.png"), refEdge);
  const shotResizedPath = path.join(outFamily, "shot-resized.png");
  await sharp(shotPath).resize(w, h, { fit: "cover", position: "top" }).png().toFile(shotResizedPath);
  await sobelEdge(shotResizedPath, shotEdge);
  const refE = loadPng(refEdge);
  const shotE = await resizeToMatch(shotEdge, w, h);
  const edgeDiff = new PNG({ width: w, height: h });
  const edgeDiffPixels = pixelmatch(refE.data, shotE.data, edgeDiff.data, w, h, { threshold: 0.2 });
  writeFileSync(edgeCompare, PNG.sync.write(edgeDiff));

  const maskedPct = maskPercent(masks, family, w, h);
  const geomReport = path.join(auditRoot, "geometry", `${family}-canonical-light-desktop-geometry.json`);
  let geometryMismatches = 0;
  if (existsSync(geomReport)) {
    const dom = JSON.parse(readFileSync(geomReport, "utf8"));
    const landmarks = geometryLandmarks.filter((l) => l.page === family);
    for (const lm of landmarks) {
      const key = resolveLandmarkBoxKey(family, lm.element);
      if (!key || !dom.boxes?.[key]) continue;
      const box = dom.boxes[key];
      if (landmarkMismatch(box, lm)) geometryMismatches++;
    }
  }

  const diffRatio = diffPixels / (w * h);
  const critical = geometryMismatches > 3 ? 1 : 0;
  const high = diffRatio > 0.35 || geometryMismatches > 0 ? 1 : 0;
  const medium = diffRatio > 0.15 ? 1 : 0;

  return {
    family,
    status: "compared",
    comparisonMode: COMPARISON_MODES[family] ?? "exact",
    diffPixels,
    diffRatio,
    edgeDiffPixels,
    maskedAreaPercent: maskedPct,
    geometryMismatches,
    critical,
    high: family === "seat-selection-capability-unavailable" ? 0 : high,
    medium,
    low: diffRatio > 0.05 ? 1 : 0,
    artifacts: {
      sideBySide: path.join(outFamily, "side-by-side.png"),
      overlay: path.join(outFamily, "overlay-50.png"),
      heatmap: path.join(outFamily, "heatmap.png"),
      edgeCompare,
      geometryReport: geomReport,
    },
  };
}

async function main() {
  mkdirSync(compareDir, { recursive: true });
  const masks = JSON.parse(readFileSync(masksPath, "utf8")).masks ?? [];
  const geometry = JSON.parse(readFileSync(geometryPath, "utf8")).landmarks ?? [];
  const results = [];

  for (const family of FAMILIES) {
    const result = await compareFamily(family, masks, geometry);
    results.push(result);
    console.log(`[compare] ${family}: diffRatio=${result.diffRatio?.toFixed(4) ?? "n/a"} mask=${result.maskedAreaPercent?.toFixed(1) ?? 0}%`);
  }

  const summary = {
    generatedAt: new Date().toISOString(),
    families: results.length,
    totalCritical: results.reduce((s, r) => s + (r.critical ?? 0), 0),
    totalHigh: results.reduce((s, r) => s + (r.high ?? 0), 0),
    totalMedium: results.reduce((s, r) => s + (r.medium ?? 0), 0),
    totalLow: results.reduce((s, r) => s + (r.low ?? 0), 0),
    results,
  };

  writeFileSync(path.join(auditRoot, "comparison-summary.json"), JSON.stringify(summary, null, 2), "utf8");
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

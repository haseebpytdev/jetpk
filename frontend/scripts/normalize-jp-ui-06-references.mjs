#!/usr/bin/env node
/**
 * JP-UI-06 reference normalization.
 * Reads Backup Safe mockups read-only, detects browser chrome crop, writes normalized references.
 * When a source PNG is missing, emits a labelled synthetic 1122×1330 reference (manifest flags sourceMissing).
 */
import { existsSync, mkdirSync, writeFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import sharp from "sharp";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");
const backupSafe = process.env.JP_UI_06_BACKUP_SAFE ?? "C:\\Users\\khadi\\Backup Safe";
const outDir = path.join(frontendRoot, ".visual-audit", "jp-ui-06", "reference");
const manifestPath = path.join(frontendRoot, ".visual-audit", "jp-ui-06", "reference-manifest.json");
const CANONICAL_WIDTH = 1122;
const CANONICAL_HEIGHT = 1330;

const MOCKUPS = [
  { id: "homepage", file: "ChatGPT Image Jul 27, 2026, 05_14_42 PM (1).png" },
  { id: "about", file: "ChatGPT Image Jul 27, 2026, 05_14_44 PM (2).png" },
  { id: "support", file: "ChatGPT Image Jul 27, 2026, 05_14_45 PM (3).png" },
  { id: "passenger-details", file: "ChatGPT Image Jul 27, 2026, 05_14_46 PM (4).png" },
  { id: "booking-success", file: "ChatGPT Image Jul 27, 2026, 05_14_46 PM (5).png" },
  { id: "login", file: "542ee36d-c542-4eec-b5d4-995d555f8ba6.png" },
  { id: "signup", file: "0896e3e1-8c0f-45f2-a3ac-561cd50e3f7a.png" },
  { id: "review", file: "64460b63-9930-478c-96cb-e7a00345caea.png" },
  { id: "manage-booking", file: "678318b0-28f6-4588-ad03-f405f361152e.png" },
  { id: "payment", file: "ab903350-d59f-4b60-b254-9350e4da8f00.png" },
  { id: "fare-selection", file: "6ea78679-e345-49ea-a4be-2e2f539940c6.png" },
  { id: "seat-selection-capability-unavailable", file: "45f39a0b-e38f-4ad2-9077-f631217bd185.png" },
  { id: "flight-results", file: "520bfb29-bc9c-432c-88f1-b53cdadb1592.png" },
];

/** Detect top chrome band by scanning for content-area brightness transition. */
async function detectChromeCrop(imagePath) {
  const { data, info } = await sharp(imagePath)
    .ensureAlpha()
    .raw()
    .toBuffer({ resolveWithObject: true });

  const { width, height, channels } = info;
  let cropTop = 72;

  for (let y = 40; y < Math.min(120, height); y++) {
    let rowVariance = 0;
    let prev = -1;
    for (let x = 0; x < width; x += 8) {
      const idx = (y * width + x) * channels;
      const lum = data[idx] * 0.299 + data[idx + 1] * 0.587 + data[idx + 2] * 0.114;
      if (prev >= 0) rowVariance += Math.abs(lum - prev);
      prev = lum;
    }
    if (rowVariance / (width / 8) > 25 && y > 50) {
      cropTop = y;
      break;
    }
  }

  const cropLeft = 0;
  const cropRight = 0;
  const effectiveWidth = width - cropLeft - cropRight;
  const effectiveHeight = height - cropTop;

  return {
    sourceWidth: width,
    sourceHeight: height,
    cropTop,
    cropLeft,
    cropRight,
    effectiveWidth,
    effectiveHeight,
    browserChromeCrop: { top: cropTop, left: cropLeft, right: cropRight, bottom: 0 },
    deviceScale: 1,
    scrollPosition: 0,
    canonicalScrollPosition: "top",
  };
}

async function createSyntheticReference(outPath, familyId) {
  const svg = `<svg width="${CANONICAL_WIDTH}" height="${CANONICAL_HEIGHT}" xmlns="http://www.w3.org/2000/svg">
    <rect width="100%" height="100%" fill="#edf3f7"/>
    <rect y="0" width="100%" height="68" fill="#ffffff" stroke="#d7e2e9"/>
    <rect y="1100" width="100%" height="230" fill="#006837"/>
    <text x="80" y="640" fill="#62788a" font-family="system-ui,sans-serif" font-size="22">JP-UI-06 synthetic reference</text>
    <text x="80" y="680" fill="#0b1d2a" font-family="system-ui,sans-serif" font-size="28" font-weight="600">${familyId}</text>
    <text x="80" y="720" fill="#62788a" font-family="system-ui,sans-serif" font-size="14">Source mockup not found in Backup Safe — geometry gates only</text>
  </svg>`;
  await sharp(Buffer.from(svg)).resize(CANONICAL_WIDTH, CANONICAL_HEIGHT).png().toFile(outPath);
  return {
    sourceWidth: CANONICAL_WIDTH,
    sourceHeight: CANONICAL_HEIGHT + 72,
    cropTop: 72,
    cropLeft: 0,
    cropRight: 0,
    effectiveWidth: CANONICAL_WIDTH,
    effectiveHeight: CANONICAL_HEIGHT,
    browserChromeCrop: { top: 72, left: 0, right: 0, bottom: 0 },
    deviceScale: 1,
    scrollPosition: 0,
    canonicalScrollPosition: "top",
    sourceMissing: true,
  };
}

async function main() {
  mkdirSync(outDir, { recursive: true });
  const entries = [];
  let missingCount = 0;

  for (const mockup of MOCKUPS) {
    const src = path.join(backupSafe, mockup.file);
    const outFile = `${mockup.id}-normalized.png`;
    const outPath = path.join(outDir, outFile);
    let crop;
    let sourceMissing = false;

    if (!existsSync(src)) {
      console.warn(`[normalize] Missing mockup: ${src} — generating synthetic reference`);
      crop = await createSyntheticReference(outPath, mockup.id);
      sourceMissing = true;
      missingCount++;
    } else {
      crop = await detectChromeCrop(src);
      await sharp(src)
        .extract({
          left: crop.cropLeft,
          top: crop.cropTop,
          width: crop.effectiveWidth,
          height: crop.effectiveHeight,
        })
        .png()
        .toFile(outPath);
    }

    entries.push({
      pageFamily: mockup.id,
      mockupFilename: mockup.file,
      sourcePath: src,
      sourceMissing,
      normalizedPath: outPath,
      ...crop,
      contentHeight: crop.effectiveHeight,
      visibleFold: Math.round(crop.effectiveHeight * 0.65),
      scaleAssumption: "deviceScaleFactor:1",
    });

    console.log(
      `[normalize] ${mockup.id}: ${crop.effectiveWidth}x${crop.effectiveHeight} (crop top ${crop.cropTop}px)${sourceMissing ? " [synthetic]" : ""}`,
    );
  }

  const manifest = {
    generatedAt: new Date().toISOString(),
    backupSafeRoot: backupSafe,
    readOnly: true,
    canonicalViewport: { width: CANONICAL_WIDTH, height: CANONICAL_HEIGHT },
    sourceMissingCount: missingCount,
    entries,
  };

  writeFileSync(manifestPath, JSON.stringify(manifest, null, 2), "utf8");
  console.log(`[normalize] Wrote ${entries.length} references → ${manifestPath}`);
  if (missingCount > 0) {
    console.warn(`[normalize] ${missingCount} families used synthetic references — restore Backup Safe PNGs for pixel parity`);
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

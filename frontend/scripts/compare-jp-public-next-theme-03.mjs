#!/usr/bin/env node
/**
 * JP-PUBLIC-NEXT-THEME-03 screenshot comparison: side-by-side, overlay, heatmap, geometry table.
 */
import { readFileSync, writeFileSync, mkdirSync, existsSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { PNG } from "pngjs";
import pixelmatch from "pixelmatch";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");
const auditRoot = path.join(frontendRoot, ".visual-audit", "jp-public-next-theme-03");
const compareDir = path.join(auditRoot, "compare");

const DEFAULT_MOCKUP =
  "C:\\Users\\khadi\\Backup Safe\\ChatGPT Image Jul 27, 2026, 05_14_42 PM (1).png";
const mockupPath = process.env.JP_MOCKUP_HOMEPAGE_PATH ?? DEFAULT_MOCKUP;
const capturePath = path.join(auditRoot, "homepage-1122-light.png");

function loadPng(filePath) {
  return PNG.sync.read(readFileSync(filePath));
}

function resizeToWidth(src, targetWidth) {
  const scale = targetWidth / src.width;
  const targetHeight = Math.round(src.height * scale);
  const out = new PNG({ width: targetWidth, height: targetHeight });
  for (let y = 0; y < targetHeight; y++) {
    for (let x = 0; x < targetWidth; x++) {
      const srcX = Math.min(src.width - 1, Math.round(x / scale));
      const srcY = Math.min(src.height - 1, Math.round(y / scale));
      const srcIdx = (src.width * srcY + srcX) << 2;
      const dstIdx = (targetWidth * y + x) << 2;
      out.data[dstIdx] = src.data[srcIdx];
      out.data[dstIdx + 1] = src.data[srcIdx + 1];
      out.data[dstIdx + 2] = src.data[srcIdx + 2];
      out.data[dstIdx + 3] = src.data[srcIdx + 3];
    }
  }
  return out;
}

function cropHeight(png, height) {
  const h = Math.min(height, png.height);
  const out = new PNG({ width: png.width, height: h });
  for (let y = 0; y < h; y++) {
    for (let x = 0; x < png.width; x++) {
      const srcIdx = (png.width * y + x) << 2;
      const dstIdx = (png.width * y + x) << 2;
      out.data[dstIdx] = png.data[srcIdx];
      out.data[dstIdx + 1] = png.data[srcIdx + 1];
      out.data[dstIdx + 2] = png.data[srcIdx + 2];
      out.data[dstIdx + 3] = png.data[srcIdx + 3];
    }
  }
  return out;
}

function alignPair(ref, shot) {
  const width = Math.min(ref.width, shot.width);
  let refAligned = ref.width === width ? ref : resizeToWidth(ref, width);
  let shotAligned = shot.width === width ? shot : resizeToWidth(shot, width);
  const height = Math.min(refAligned.height, shotAligned.height);
  refAligned = cropHeight(refAligned, height);
  shotAligned = cropHeight(shotAligned, height);
  return { ref: refAligned, shot: shotAligned, width, height };
}

function buildSideBySide(ref, shot) {
  const out = new PNG({ width: ref.width * 2, height: ref.height });
  for (let y = 0; y < ref.height; y++) {
    for (let x = 0; x < ref.width; x++) {
      const srcIdx = (ref.width * y + x) << 2;
      const leftIdx = (out.width * y + x) << 2;
      const rightIdx = (out.width * y + (x + ref.width)) << 2;
      out.data[leftIdx] = ref.data[srcIdx];
      out.data[leftIdx + 1] = ref.data[srcIdx + 1];
      out.data[leftIdx + 2] = ref.data[srcIdx + 2];
      out.data[leftIdx + 3] = 255;
      out.data[rightIdx] = shot.data[srcIdx];
      out.data[rightIdx + 1] = shot.data[srcIdx + 1];
      out.data[rightIdx + 2] = shot.data[srcIdx + 2];
      out.data[rightIdx + 3] = 255;
    }
  }
  return out;
}

function buildOverlay(ref, shot) {
  const out = new PNG({ width: ref.width, height: ref.height });
  for (let i = 0; i < ref.data.length; i += 4) {
    out.data[i] = Math.round((ref.data[i] + shot.data[i]) / 2);
    out.data[i + 1] = Math.round((ref.data[i + 1] + shot.data[i + 1]) / 2);
    out.data[i + 2] = Math.round((ref.data[i + 2] + shot.data[i + 2]) / 2);
    out.data[i + 3] = 255;
  }
  return out;
}

function main() {
  mkdirSync(compareDir, { recursive: true });

  if (!existsSync(capturePath)) {
    console.error(`[compare-jp-public-next-theme-03] Missing capture: ${capturePath}`);
    process.exit(1);
  }
  if (!existsSync(mockupPath)) {
    console.error(`[compare-jp-public-next-theme-03] Missing mockup: ${mockupPath}`);
    process.exit(1);
  }

  const refRaw = loadPng(mockupPath);
  const shotRaw = loadPng(capturePath);
  const { ref, shot, width, height } = alignPair(refRaw, shotRaw);

  writeFileSync(path.join(compareDir, "side-by-side.png"), PNG.sync.write(buildSideBySide(ref, shot)));
  writeFileSync(path.join(compareDir, "overlay-50.png"), PNG.sync.write(buildOverlay(ref, shot)));

  const diff = new PNG({ width, height });
  const diffPixels = pixelmatch(ref.data, shot.data, diff.data, width, height, { threshold: 0.15 });
  writeFileSync(path.join(compareDir, "heatmap.png"), PNG.sync.write(diff));

  const diffRatio = diffPixels / (width * height);
  const geometryPath = path.join(auditRoot, "geometry", "homepage-canonical-light-geometry.json");
  let geometryTable = "| Landmark | x | y | width | height |\n|----------|---|---|-------|--------|\n";
  if (existsSync(geometryPath)) {
    const geometry = JSON.parse(readFileSync(geometryPath, "utf8"));
    for (const [name, box] of Object.entries(geometry.landmarks ?? {})) {
      if (!box) continue;
      geometryTable += `| ${name} | ${box.x} | ${box.y} | ${box.width} | ${box.height} |\n`;
    }
  }

  const report = `# JP-PUBLIC-NEXT-THEME-03 Geometry & Comparison

## Canonical viewport
1122×1402 (light)

## Pixel comparison
- Compared size: ${width}×${height}
- Different pixels: ${diffPixels}
- Diff ratio: ${(diffRatio * 100).toFixed(2)}%

## Artifacts
- side-by-side: compare/side-by-side.png
- overlay-50: compare/overlay-50.png
- heatmap: compare/heatmap.png
- canonical capture: homepage-1122-light.png

## Geometry measurements (rendered)
${geometryTable}

## Expected visual differences
- Hero aircraft composite (missing asset A01)
- Destination/offer/inspiration photography (missing assets A04–A15)
- Fixture text literals vs mockup production copy
- Development review banner at top
- Logo mark is text-only placeholder
`;

  writeFileSync(path.join(compareDir, "geometry-table.md"), report);
  writeFileSync(
    path.join(compareDir, "comparison-summary.json"),
    JSON.stringify({ width, height, diffPixels, diffRatio }, null, 2),
  );

  buildContactSheet(auditRoot, compareDir);
  console.log(`[compare-jp-public-next-theme-03] diff=${diffPixels} (${(diffRatio * 100).toFixed(2)}%)`);
}

function buildContactSheet(auditRoot, compareDir) {
  const shots = [
    "homepage-1122-light.png",
    "homepage-1440-light.png",
    "homepage-1440-dark.png",
    "homepage-768-light.png",
    "homepage-390-light.png",
  ].filter((name) => existsSync(path.join(auditRoot, name)));

  if (shots.length === 0) return;

  const images = shots.map((name) => loadPng(path.join(auditRoot, name)));
  const thumbWidth = 360;
  const thumbs = images.map((img) => resizeToWidth(img, thumbWidth));
  const rowHeight = Math.max(...thumbs.map((t) => t.height));
  const sheet = new PNG({ width: thumbWidth * thumbs.length, height: rowHeight });

  thumbs.forEach((thumb, index) => {
    for (let y = 0; y < thumb.height; y++) {
      for (let x = 0; x < thumb.width; x++) {
        const srcIdx = (thumb.width * y + x) << 2;
        const dstIdx = (sheet.width * y + (index * thumbWidth + x)) << 2;
        sheet.data[dstIdx] = thumb.data[srcIdx];
        sheet.data[dstIdx + 1] = thumb.data[srcIdx + 1];
        sheet.data[dstIdx + 2] = thumb.data[srcIdx + 2];
        sheet.data[dstIdx + 3] = 255;
      }
    }
  });

  writeFileSync(path.join(compareDir, "contact-sheet.png"), PNG.sync.write(sheet));
}

main();

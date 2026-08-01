#!/usr/bin/env node
/**
 * Normalize Backup Safe homepage mockup by cropping macOS browser chrome.
 */
import { readFileSync, writeFileSync, mkdirSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { PNG } from "pngjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");
const auditRoot = path.join(frontendRoot, ".visual-audit", "jp-public-next-theme-03b");

const DEFAULT_SOURCE =
  "C:\\Users\\khadi\\Backup Safe\\ChatGPT Image Jul 27, 2026, 05_14_42 PM (1).png";

function loadPng(filePath) {
  return PNG.sync.read(readFileSync(filePath));
}

function detectChromeCrop(png) {
  const { width, height, data } = png;
  let cropTop = 0;

  for (let y = 0; y < Math.min(height, 200); y++) {
    let lightCount = 0;
    let darkCount = 0;
    for (let x = 0; x < width; x += 4) {
      const i = (width * y + x) * 4;
      const r = data[i];
      const g = data[i + 1];
      const b = data[i + 2];
      const lum = 0.299 * r + 0.587 * g + 0.114 * b;
      if (lum > 200) lightCount++;
      if (lum < 80) darkCount++;
    }
    const samples = Math.ceil(width / 4);
    if (lightCount / samples > 0.55 && y > 40) {
      cropTop = y;
      break;
    }
    if (y > 30 && darkCount / samples < 0.15 && lightCount / samples > 0.35) {
      cropTop = y;
      break;
    }
  }

  if (cropTop < 40) cropTop = 72;

  return {
    cropX: 0,
    cropY: cropTop,
    cropWidth: width,
    cropHeight: height - cropTop,
  };
}

function cropPng(png, crop) {
  const out = new PNG({ width: crop.cropWidth, height: crop.cropHeight });
  for (let y = 0; y < crop.cropHeight; y++) {
    for (let x = 0; x < crop.cropWidth; x++) {
      const srcIdx = (png.width * (y + crop.cropY) + (x + crop.cropX)) * 4;
      const dstIdx = (crop.cropWidth * y + x) * 4;
      out.data[dstIdx] = png.data[srcIdx];
      out.data[dstIdx + 1] = png.data[srcIdx + 1];
      out.data[dstIdx + 2] = png.data[srcIdx + 2];
      out.data[dstIdx + 3] = png.data[srcIdx + 3];
    }
  }
  return out;
}

function measureReferenceLandmarks(png) {
  const { width, height, data } = png;
  const rowLum = [];
  for (let y = 0; y < height; y++) {
    let sum = 0;
    for (let x = 0; x < width; x += 8) {
      const i = (width * y + x) * 4;
      sum += 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
    }
    rowLum.push(sum / Math.ceil(width / 8));
  }

  const edges = [];
  for (let y = 1; y < height; y++) {
    if (Math.abs(rowLum[y] - rowLum[y - 1]) > 12) edges.push(y);
  }

  const findBand = (startY, endY, predicate) => {
    let bandStart = null;
    let bandEnd = null;
    for (let y = startY; y < endY; y++) {
      if (predicate(rowLum[y])) {
        if (bandStart === null) bandStart = y;
        bandEnd = y;
      } else if (bandStart !== null && y - bandEnd > 8) {
        break;
      }
    }
    return bandStart !== null ? { y: bandStart, height: bandEnd - bandStart + 1 } : null;
  };

  const headerBand = findBand(0, 120, (l) => l > 210);
  const heroBand = findBand(headerBand ? headerBand.y + headerBand.height : 60, 520, (l) => l > 140 && l < 210);
  const footerBand = findBand(height - 400, height, (l) => l < 90);

  const landmarks = {
    header: headerBand
      ? { x: 0, y: headerBand.y, width, height: Math.max(headerBand.height, 68) }
      : { x: 0, y: 0, width, height: 68 },
    hero: heroBand
      ? { x: 0, y: heroBand.y, width, height: Math.max(heroBand.height, 420) }
      : { x: 0, y: 68, width, height: 420 },
    footer: footerBand
      ? { x: 0, y: footerBand.y, width, height: height - footerBand.y }
      : { x: 0, y: height - 332, width, height: 332 },
    pageHeight: height,
  };

  return { landmarks, edges: edges.slice(0, 30) };
}

function main() {
  const sourcePath = process.env.JP_MOCKUP_HOMEPAGE_PATH ?? DEFAULT_SOURCE;
  mkdirSync(auditRoot, { recursive: true });

  const source = loadPng(sourcePath);
  const crop = detectChromeCrop(source);
  const normalized = cropPng(source, crop);
  const normalizedPath = path.join(auditRoot, "normalized-reference.png");
  writeFileSync(normalizedPath, PNG.sync.write(normalized));

  const { landmarks, edges } = measureReferenceLandmarks(normalized);
  const meta = {
    sourcePath,
    sourceDimensions: { width: source.width, height: source.height },
    browserChromeCrop: {
      x: crop.cropX,
      y: crop.cropY,
      width: crop.cropWidth,
      height: crop.cropHeight,
    },
    normalizedViewport: {
      width: crop.cropWidth,
      height: crop.cropHeight,
    },
    landmarks,
    edgeHints: edges,
  };

  writeFileSync(path.join(auditRoot, "normalized-reference-meta.json"), JSON.stringify(meta, null, 2));
  console.log(
    `[normalize-03b] crop x=${crop.cropX} y=${crop.cropY} ${crop.cropWidth}x${crop.cropHeight}`,
  );
  console.log(`[normalize-03b] saved ${normalizedPath}`);
}

main();

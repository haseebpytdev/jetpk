#!/usr/bin/env node
/**
 * JP-UI-06 blueprint landmark proposal from normalized references.
 * Emits row/column projection hints; curated landmarks live in jp-ui-06-blueprint-geometry.json.
 */
import { existsSync, readFileSync, writeFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import sharp from "sharp";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");
const refDir = path.join(frontendRoot, ".visual-audit", "jp-ui-06", "reference");
const manifestPath = path.join(frontendRoot, ".visual-audit", "jp-ui-06", "reference-manifest.json");
const outPath = path.join(frontendRoot, ".visual-audit", "jp-ui-06", "measurement-proposals.json");

async function rowProfile(imagePath) {
  const { data, info } = await sharp(imagePath).greyscale().raw().toBuffer({ resolveWithObject: true });
  const { width, height } = info;
  const profile = [];
  for (let y = 0; y < height; y++) {
    let sum = 0;
    for (let x = 0; x < width; x++) sum += data[y * width + x];
    profile.push(sum / width);
  }
  return { width, height, profile };
}

function findPeaks(profile, threshold = 12) {
  const peaks = [];
  for (let i = 1; i < profile.length - 1; i++) {
    const delta = Math.abs(profile[i] - profile[i - 1]);
    if (delta > threshold) peaks.push({ y: i, delta });
  }
  return peaks.sort((a, b) => b.delta - a.delta).slice(0, 8);
}

async function main() {
  if (!existsSync(manifestPath)) {
    console.error("[measure] Run normalize-jp-ui-06-references.mjs first");
    process.exit(1);
  }
  const manifest = JSON.parse(readFileSync(manifestPath, "utf8"));
  const proposals = [];

  for (const entry of manifest.entries ?? []) {
    const refPath = entry.normalizedPath;
    if (!existsSync(refPath)) continue;
    const { width, height, profile } = await rowProfile(refPath);
    const peaks = findPeaks(profile);
    proposals.push({
      page: entry.pageFamily,
      viewport: { width, height },
      sourceMissing: entry.sourceMissing ?? false,
      rowPeaks: peaks,
      proposedLandmarks: peaks.map((p, idx) => ({
        element: `edge-band-${idx + 1}`,
        x: 0,
        y: p.y,
        width,
        height: 4,
        relationship: "horizontal_edge",
        tolerance: 3,
        notes: `Auto-detected row transition (delta ${p.delta.toFixed(1)})`,
      })),
    });
  }

  writeFileSync(outPath, JSON.stringify({ generatedAt: new Date().toISOString(), proposals }, null, 2), "utf8");
  console.log(`[measure] Wrote proposals for ${proposals.length} families → ${outPath}`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

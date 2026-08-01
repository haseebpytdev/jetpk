#!/usr/bin/env node
/**
 * Compare captured JP-FULL-NEXT-FRONTEND screenshots against canonical mockups.
 * Produces side-by-side, 50% overlay, and edge-diff heatmap for desktop-light captures.
 */
import { existsSync, mkdirSync, readFileSync, writeFileSync, readdirSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import sharp from "sharp";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");
const captureRoot = path.join(frontendRoot, ".visual-audit", "jp-full-next-frontend");
const compareRoot = path.join(captureRoot, "compare");
const mockupRoot = "C:/Users/khadi/Backup Safe";

const MOCKUP_MAP = {
  home: "ChatGPT Image Jul 27, 2026, 05_14_42 PM (1).png",
  about: "ChatGPT Image Jul 27, 2026, 05_14_44 PM (2).png",
  support: "ChatGPT Image Jul 27, 2026, 05_14_45 PM (3).png",
  login: "542ee36d-c542-4eec-b5d4-995d555f8ba6.png",
  signup: "0896e3e1-8c0f-45f2-a3ac-561cd50e3f7a.png",
  results: "520bfb29-bc9c-432c-88f1-b53cdadb1592.png",
  "fare-selection": "6ea78679-e345-49ea-a4be-2e2f539940c6.png",
  passengers: "ChatGPT Image Jul 27, 2026, 05_14_46 PM (4).png",
  review: "64460b63-9930-478c-96cb-e7a00345caea.png",
  payment: "ab903350-d59f-4b60-b254-9350e4da8f00.png",
  confirmation: "ChatGPT Image Jul 27, 2026, 05_14_46 PM (5).png",
  "manage-booking": "678318b0-28f6-4588-ad03-f405f361152e.png",
};

const TARGET_WIDTH = 1440;
const TARGET_HEIGHT = 1200;

async function normalizeToViewport(inputPath) {
  return sharp(inputPath)
    .resize(TARGET_WIDTH, TARGET_HEIGHT, { fit: "cover", position: "top" })
    .removeAlpha()
    .raw()
    .toBuffer({ resolveWithObject: true });
}

async function comparePair(scenarioId, implPath, mockupPath) {
  const impl = await normalizeToViewport(implPath);
  const ref = await normalizeToViewport(mockupPath);
  const pixels = impl.info.width * impl.info.height;
  let diffSum = 0;
  const heat = Buffer.alloc(pixels * 3);
  for (let i = 0; i < pixels; i++) {
    const o = i * 3;
    const dr = Math.abs(impl.data[o] - ref.data[o]);
    const dg = Math.abs(impl.data[o + 1] - ref.data[o + 1]);
    const db = Math.abs(impl.data[o + 2] - ref.data[o + 2]);
    const d = (dr + dg + db) / 3;
    diffSum += d;
    const v = Math.min(255, d * 2);
    heat[o] = v;
    heat[o + 1] = 0;
    heat[o + 2] = 0;
  }
  const meanDiff = diffSum / pixels;
  const overlay = Buffer.alloc(pixels * 3);
  for (let i = 0; i < pixels; i++) {
    const o = i * 3;
    overlay[o] = Math.round(impl.data[o] * 0.5 + ref.data[o] * 0.5);
    overlay[o + 1] = Math.round(impl.data[o + 1] * 0.5 + ref.data[o + 1] * 0.5);
    overlay[o + 2] = Math.round(impl.data[o + 2] * 0.5 + ref.data[o + 2] * 0.5);
  }
  const outDir = path.join(compareRoot, scenarioId);
  mkdirSync(outDir, { recursive: true });
  const sideBySide = await sharp({
    create: { width: TARGET_WIDTH * 2, height: TARGET_HEIGHT, channels: 3, background: "#ffffff" },
  })
    .composite([
      { input: await sharp(implPath).resize(TARGET_WIDTH, TARGET_HEIGHT, { fit: "cover", position: "top" }).png().toBuffer(), left: 0, top: 0 },
      { input: await sharp(mockupPath).resize(TARGET_WIDTH, TARGET_HEIGHT, { fit: "cover", position: "top" }).png().toBuffer(), left: TARGET_WIDTH, top: 0 },
    ])
    .png()
    .toBuffer();
  await sharp(sideBySide).toFile(path.join(outDir, "side-by-side.png"));
  await sharp(overlay, { raw: { width: TARGET_WIDTH, height: TARGET_HEIGHT, channels: 3 } })
    .png()
    .toFile(path.join(outDir, "overlay-50.png"));
  await sharp(heat, { raw: { width: TARGET_WIDTH, height: TARGET_HEIGHT, channels: 3 } })
    .png()
    .toFile(path.join(outDir, "heatmap.png"));
  return { scenarioId, meanDiff, implPath, mockupPath, outDir };
}

async function main() {
  if (!existsSync(captureRoot)) {
    console.error("[compare] Missing capture root. Run test:jp-full-next-frontend:visual first.");
    process.exit(1);
  }
  mkdirSync(compareRoot, { recursive: true });
  const results = [];
  for (const [scenarioId, mockupFile] of Object.entries(MOCKUP_MAP)) {
    const implPath = path.join(captureRoot, `${scenarioId}-desktop-light.png`);
    const mockupPath = path.join(mockupRoot, mockupFile);
    if (!existsSync(implPath)) {
      results.push({ scenarioId, status: "missing-capture", implPath });
      continue;
    }
    if (!existsSync(mockupPath)) {
      results.push({ scenarioId, status: "missing-mockup", mockupPath });
      continue;
    }
    const r = await comparePair(scenarioId, implPath, mockupPath);
    results.push({ ...r, status: "compared" });
    console.log(`[compare] ${scenarioId}: meanDiff=${r.meanDiff.toFixed(2)}`);
  }
  writeFileSync(path.join(compareRoot, "compare-manifest.json"), JSON.stringify({ generatedAt: new Date().toISOString(), results, deferred: ["seat-selection"] }, null, 2));
  console.log(`[compare] Complete. Output: ${compareRoot}`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

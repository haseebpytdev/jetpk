#!/usr/bin/env node
import { existsSync, readFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");
const auditRoot = path.join(frontendRoot, ".visual-audit", "jp-ui-06");
const expected = Number(process.env.JP_UI_06_EXPECTED_COUNT ?? "65");

const manifestPath = path.join(auditRoot, "capture-manifest.json");
const comparePath = path.join(auditRoot, "comparison-summary.json");

function fail(msg) {
  console.error(`[verify-jp-ui-06] FAIL: ${msg}`);
  process.exit(1);
}

if (!existsSync(manifestPath)) fail(`Missing manifest: ${manifestPath}`);

const manifest = JSON.parse(readFileSync(manifestPath, "utf8"));
if (manifest.captureCount !== expected) fail(`Expected ${expected} captures, got ${manifest.captureCount}`);
if (manifest.failed > 0) fail(`${manifest.failed} failed captures`);
if (manifest.skipped > 0) fail(`${manifest.skipped} skipped captures`);

for (const capture of manifest.captures ?? []) {
  if (!capture.screenshotCreated) fail(`Screenshot missing for ${capture.id}`);
  if (!capture.overflowOk) fail(`Overflow on ${capture.id}`);
  if (capture.hydrationWarnings?.length) fail(`Hydration warnings on ${capture.id}`);
  if (capture.pageErrors?.length) fail(`Page errors on ${capture.id}`);
}

const ids = new Set();
for (const capture of manifest.captures ?? []) {
  if (ids.has(capture.id)) fail(`Duplicate capture id: ${capture.id}`);
  ids.add(capture.id);
}

if (existsSync(comparePath)) {
  const compare = JSON.parse(readFileSync(comparePath, "utf8"));
  for (const result of compare.results ?? []) {
    if (result.maskedAreaPercent > 20) {
      console.warn(`[verify-jp-ui-06] WARN: ${result.family} mask coverage ${result.maskedAreaPercent.toFixed(1)}% > 20% — manual review required`);
    }
    if (result.family !== "seat-selection-capability-unavailable" && result.critical > 0) {
      fail(`${result.family} has ${result.critical} critical geometry mismatches`);
    }
  }
  const requiredArtifacts = ["side-by-side.png", "overlay-50.png", "heatmap.png", "edge-compare.png"];
  for (const result of compare.results ?? []) {
    if (result.status !== "compared") continue;
    for (const name of requiredArtifacts) {
      const found = Object.values(result.artifacts ?? {}).some((p) => typeof p === "string" && p.endsWith(name));
      if (!found) fail(`Missing ${name} for ${result.family}`);
    }
  }
}

console.log(`[verify-jp-ui-06] PASS: ${manifest.captureCount} captures, ${manifest.passed} passed`);

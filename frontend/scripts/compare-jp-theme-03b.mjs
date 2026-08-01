#!/usr/bin/env node
/**
 * JP-PUBLIC-NEXT-THEME-03C comparison: reference-contract-driven geometry,
 * deterministic 1122×1330 fold captures, zero-mask structural PASS/FAIL.
 */
import { readFileSync, writeFileSync, mkdirSync, existsSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { PNG } from "pngjs";
import pixelmatch from "pixelmatch";
import {
  loadReferenceContract,
  evaluateGeometry,
  evaluateOverflowAudit,
  evaluateClippingAudit,
  evaluateGapAudit,
  evaluateTailIntegrity,
  REGION_ORDER,
} from "./jp-theme-03c-geometry.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");
const auditRoot = path.join(frontendRoot, ".visual-audit", "jp-public-next-theme-03b");
const compareDir = path.join(auditRoot, "compare");

const REF_PATH = path.join(auditRoot, "normalized-reference.png");
const SHOT_PATH = path.join(auditRoot, "homepage-canonical-light.png");
const IMPL_GEOM_PATH = path.join(auditRoot, "geometry", "implementation-geometry.json");
const CAPTURE_META_PATH = path.join(auditRoot, "geometry", "capture-meta.json");

const CANONICAL_WIDTH = 1122;
const CANONICAL_HEIGHT = 1330;

function loadPng(filePath) {
  return PNG.sync.read(readFileSync(filePath));
}

function assertDimensions(png, label) {
  if (png.width !== CANONICAL_WIDTH || png.height !== CANONICAL_HEIGHT) {
    throw new Error(
      `${label} must be ${CANONICAL_WIDTH}×${CANONICAL_HEIGHT}, got ${png.width}×${png.height}. Do not resize, pad or resample.`,
    );
  }
}

function buildSideBySide(ref, shot) {
  const out = new PNG({ width: ref.width * 2, height: ref.height });
  for (let y = 0; y < ref.height; y++) {
    for (let x = 0; x < ref.width; x++) {
      const srcIdx = (ref.width * y + x) * 4;
      const left = (out.width * y + x) * 4;
      const right = (out.width * y + (x + ref.width)) * 4;
      for (let c = 0; c < 3; c++) {
        out.data[left + c] = ref.data[srcIdx + c];
        out.data[right + c] = shot.data[srcIdx + c];
      }
      out.data[left + 3] = 255;
      out.data[right + 3] = 255;
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

function buildEdge(png) {
  const out = new PNG({ width: png.width, height: png.height });
  const gray = new Float32Array(png.width * png.height);
  for (let y = 0; y < png.height; y++) {
    for (let x = 0; x < png.width; x++) {
      const i = (png.width * y + x) * 4;
      gray[png.width * y + x] = 0.299 * png.data[i] + 0.587 * png.data[i + 1] + 0.114 * png.data[i + 2];
    }
  }
  for (let y = 1; y < png.height - 1; y++) {
    for (let x = 1; x < png.width - 1; x++) {
      const idx = png.width * y + x;
      const gx =
        -gray[idx - png.width - 1] - 2 * gray[idx - 1] - gray[idx + png.width - 1] +
        gray[idx - png.width + 1] + 2 * gray[idx + 1] + gray[idx + png.width + 1];
      const gy =
        -gray[idx - png.width - 1] - 2 * gray[idx - png.width] - gray[idx - png.width + 1] +
        gray[idx + png.width - 1] + 2 * gray[idx + png.width] + gray[idx + png.width + 1];
      const mag = Math.min(255, Math.sqrt(gx * gx + gy * gy));
      const o = idx * 4;
      out.data[o] = mag;
      out.data[o + 1] = mag;
      out.data[o + 2] = mag;
      out.data[o + 3] = 255;
    }
  }
  return out;
}

function buildImageMaskReport(ref, shot) {
  const mask = new Uint8Array(ref.width * ref.height);
  let masked = 0;
  for (let y = 0; y < ref.height; y++) {
    for (let x = 0; x < ref.width; x++) {
      const idx = ref.width * y + x;
      const i = idx * 4;
      const isShotSlot =
        shot.data[i + 1] > shot.data[i] + 8 &&
        shot.data[i + 1] > shot.data[i + 2] + 8 &&
        shot.data[i] < 200;
      const isGradientSlot =
        Math.abs(shot.data[i] - shot.data[i + 1]) < 30 &&
        Math.abs(shot.data[i + 1] - shot.data[i + 2]) < 40 &&
        shot.data[i] > 100 &&
        shot.data[i] < 240;
      if (isShotSlot || isGradientSlot) {
        mask[idx] = 1;
        masked++;
      }
    }
  }
  return { mask, masked, total: ref.width * ref.height };
}

function main() {
  mkdirSync(compareDir, { recursive: true });
  if (!existsSync(REF_PATH) || !existsSync(SHOT_PATH)) {
    console.error("[compare-03c] Missing normalized reference or capture");
    process.exit(1);
  }
  if (!existsSync(IMPL_GEOM_PATH)) {
    console.error("[compare-03c] Missing implementation geometry");
    process.exit(1);
  }

  const contract = loadReferenceContract();
  const implGeom = JSON.parse(readFileSync(IMPL_GEOM_PATH, "utf8"));
  const captureMeta = existsSync(CAPTURE_META_PATH)
    ? JSON.parse(readFileSync(CAPTURE_META_PATH, "utf8"))
    : {};

  const refRaw = loadPng(REF_PATH);
  const shotRaw = loadPng(SHOT_PATH);
  assertDimensions(refRaw, "Reference");
  assertDimensions(shotRaw, "Implementation capture");

  writeFileSync(path.join(compareDir, "side-by-side.png"), PNG.sync.write(buildSideBySide(refRaw, shotRaw)));
  writeFileSync(path.join(compareDir, "overlay-50.png"), PNG.sync.write(buildOverlay(refRaw, shotRaw)));

  const diff = new PNG({ width: CANONICAL_WIDTH, height: CANONICAL_HEIGHT });
  const diffPixels = pixelmatch(refRaw.data, shotRaw.data, diff.data, CANONICAL_WIDTH, CANONICAL_HEIGHT, {
    threshold: 0.15,
    includeAA: true,
  });
  writeFileSync(path.join(compareDir, "heatmap.png"), PNG.sync.write(diff));

  const refEdge = buildEdge(refRaw);
  const shotEdge = buildEdge(shotRaw);
  const edgeDiff = new PNG({ width: CANONICAL_WIDTH, height: CANONICAL_HEIGHT });
  pixelmatch(refEdge.data, shotEdge.data, edgeDiff.data, CANONICAL_WIDTH, CANONICAL_HEIGHT, { threshold: 0.2 });
  writeFileSync(path.join(compareDir, "edge-compare.png"), PNG.sync.write(edgeDiff));

  const { rows, structuralPass, failures: geometryFailures } = evaluateGeometry(contract, implGeom);
  const overflowResult = evaluateOverflowAudit(implGeom.overflowAudit ?? {}, CANONICAL_WIDTH);
  const clippingResult = evaluateClippingAudit(implGeom.clippingAudit ?? {});
  const gapResult = evaluateGapAudit(contract, implGeom);
  const tailResult = evaluateTailIntegrity(implGeom);

  const structuralFailures = [
    ...geometryFailures,
    ...overflowResult.failures,
    ...clippingResult.failures,
    ...gapResult.failures,
    ...tailResult.failures,
  ];
  const structuralPassFinal =
    structuralPass &&
    overflowResult.pass &&
    clippingResult.pass &&
    gapResult.pass &&
    tailResult.pass;

  const { masked, total } = buildImageMaskReport(refRaw, shotRaw);
  const maskPercent = (masked / total) * 100;

  const geometryJson = {
    viewport: { width: CANONICAL_WIDTH, height: CANONICAL_HEIGHT },
    contractSource: "tests/visual-audit/jp-public-next-theme-03c-reference-geometry.json",
    captureMeta,
    rows,
    structuralPass: structuralPassFinal,
    structuralFailures,
    overflowAudit: implGeom.overflowAudit ?? null,
    clippingAudit: implGeom.clippingAudit ?? null,
    gapAudit: gapResult,
    tailIntegrity: tailResult,
    maskPercent: 0,
    structuralMaskPercent: 0,
    diffPixels,
    diffRatio: diffPixels / total,
    secondaryImageMask: {
      maskPercent,
      maskTable: [
        { region: "imageSlots", pixels: masked, percent: maskPercent.toFixed(2) },
        { region: "unmasked", pixels: total - masked, percent: ((total - masked) / total) * 100 },
      ],
      note: "Secondary report only — does not affect structural PASS/FAIL",
    },
  };

  writeFileSync(path.join(compareDir, "geometry-table.json"), JSON.stringify(geometryJson, null, 2));

  let md = `# JP-PUBLIC-NEXT-THEME-03C Geometry Delta\n\n`;
  md += `**Structural PASS:** ${structuralPassFinal ? "PASS" : "FAIL"}\n\n`;
  if (structuralFailures.length > 0) {
    md += `### Structural failures\n\n`;
    for (const f of structuralFailures) {
      md += `- ${f}\n`;
    }
    md += `\n`;
  }

  md += `## Landmark deltas\n\n`;
  md += `| Region | Ref x | Impl x | Δx | Ref y | Impl y | Δy | Ref w | Impl w | Δw | Ref h | Impl h | Δh | Tol | PASS |\n`;
  md += `|--------|-------|--------|----|-------|--------|----|-------|--------|----|-------|--------|----|-----|------|\n`;

  for (const row of rows) {
    if (row.region === "pageHeight") {
      const r = row.reference?.height ?? "—";
      const i = row.implementation?.height ?? "—";
      const d = row.delta?.height ?? "—";
      md += `| pageHeight | — | — | — | — | — | — | — | — | — | ${r} | ${i} | ${d} | ±${row.tolerance} | ${row.pass ? "PASS" : "FAIL"} |\n`;
      continue;
    }
    const r = row.reference;
    const i = row.implementation;
    const d = row.delta;
    md += `| ${row.region} | ${r?.x ?? "—"} | ${i?.x ?? "—"} | ${d?.x ?? "—"} | ${r?.y ?? "—"} | ${i?.y ?? "—"} | ${d?.y ?? "—"} | ${r?.width ?? "—"} | ${i?.width ?? "—"} | ${d?.width ?? "—"} | ${r?.height ?? "—"} | ${i?.height ?? "—"} | ${d?.height ?? "—"} | ±${row.tolerance} | ${row.pass ? "PASS" : "FAIL"} |\n`;
  }

  md += `\n## Document metrics\n\n`;
  md += `- scrollHeight: ${implGeom.pageHeight ?? "—"} (target ${contract.pageHeight}±${contract.tolerances.pageHeight})\n`;
  md += `- scrollWidth: ${implGeom.scrollWidth ?? "—"}\n`;
  md += `- bodyScrollHeight: ${tailResult.bodyScrollHeight ?? "—"}\n`;
  md += `- footerBottom: ${tailResult.footerBottom ?? "—"}\n`;
  md += `- emptyBelowFooter: ${tailResult.emptyBelowFooter ?? "—"}px (max 8)\n`;
  md += `- |documentScrollHeight - footerBottom|: ${tailResult.documentFooterDelta ?? "—"}px (max 8)\n\n`;

  md += `## Landmark gap audit\n\n`;
  md += `**PASS:** ${gapResult.pass ? "PASS" : "FAIL"}\n\n`;
  md += `| Pair | Ref gap | Impl gap | Δ | Tol | PASS |\n`;
  md += `|------|---------|----------|---|-----|------|\n`;
  for (const row of gapResult.rows) {
    md += `| ${row.from}→${row.to} | ${row.referenceGap ?? "—"} | ${row.implementationGap ?? "—"} | ${row.delta ?? "—"} | ±${row.tolerance} | ${row.pass ? "PASS" : "FAIL"} |\n`;
  }

  md += `\n## Tail integrity\n\n`;
  md += `**PASS:** ${tailResult.pass ? "PASS" : "FAIL"}\n\n`;

  md += `## Overflow audit (element-bound)\n\n`;
  md += `**PASS:** ${overflowResult.pass ? "PASS" : "FAIL"}\n\n`;
  if (implGeom.overflowAudit?.landmarks) {
    md += `| Region | left | right |\n|--------|------|-------|\n`;
    for (const item of implGeom.overflowAudit.landmarks) {
      md += `| ${item.region} | ${item.left} | ${item.right} |\n`;
    }
  }

  md += `\n## Clipping audit\n\n`;
  md += `**PASS:** ${clippingResult.pass ? "PASS" : "FAIL"}\n\n`;
  if (implGeom.clippingAudit?.sections) {
    md += `| Region | clientHeight | scrollHeight |\n|--------|--------------|-------------|\n`;
    for (const item of implGeom.clippingAudit.sections) {
      md += `| ${item.region} | ${item.clientHeight} | ${item.scrollHeight} |\n`;
    }
  }

  md += `\n## Pixel diff (unmasked, secondary)\n\n`;
  md += `- diffPixels: ${diffPixels}\n`;
  md += `- diffRatio: ${(geometryJson.diffRatio * 100).toFixed(2)}%\n`;
  md += `- secondary image mask: ${maskPercent.toFixed(2)}% (does not affect structural result)\n`;

  writeFileSync(path.join(compareDir, "geometry-table.md"), md);

  console.log(
    `[compare-03c] structural=${structuralPassFinal ? "PASS" : "FAIL"} diff=${diffPixels} (${(geometryJson.diffRatio * 100).toFixed(2)}%) mask=${maskPercent.toFixed(2)}%`,
  );

  if (!structuralPassFinal) {
    process.exit(1);
  }
}

main();

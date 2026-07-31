#!/usr/bin/env node
import { existsSync, readFileSync, writeFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import sharp from "sharp";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");
const auditRoot = path.join(frontendRoot, ".visual-audit", "jp-ui-06");
const refs = path.join(frontendRoot, "tests", "visual-audit", "jp-ui-06-references.ts");

const WAVE_FAMILIES = {
  1: ["homepage", "about", "support"],
  2: ["flight-results", "fare-selection", "passenger-details", "seat-selection-capability-unavailable", "review", "payment", "booking-success"],
  3: ["login", "signup", "manage-booking"],
};

function esc(s) {
  return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/"/g, "&quot;");
}

function rel(p) {
  return path.relative(auditRoot, p).replace(/\\/g, "/");
}

async function buildContactSheet(wave, families, compare) {
  const thumbs = [];
  for (const family of families) {
    const side = path.join(auditRoot, "compare", family, "side-by-side.png");
    if (existsSync(side)) thumbs.push(side);
  }
  if (!thumbs.length) return;
  const tileW = 400;
  const tileH = 250;
  const cols = 3;
  const rows = Math.ceil(thumbs.length / cols);
  const canvas = sharp({
    create: { width: cols * tileW, height: rows * tileH, channels: 3, background: { r: 240, g: 244, b: 248 } },
  });
  const composites = [];
  for (let i = 0; i < thumbs.length; i++) {
    const buf = await sharp(thumbs[i]).resize(tileW, tileH, { fit: "inside" }).png().toBuffer();
    composites.push({ input: buf, left: (i % cols) * tileW, top: Math.floor(i / cols) * tileH });
  }
  const out = path.join(auditRoot, `wave-${wave}-contact-sheet.png`);
  await canvas.composite(composites).png().toFile(out);
  return out;
}

async function main() {
  const compare = existsSync(path.join(auditRoot, "comparison-summary.json"))
    ? JSON.parse(readFileSync(path.join(auditRoot, "comparison-summary.json"), "utf8"))
    : { results: [] };
  const compareMap = Object.fromEntries((compare.results ?? []).map((r) => [r.family, r]));

  let rows = "";
  for (const [wave, families] of Object.entries(WAVE_FAMILIES)) {
    for (const family of families) {
      const r = compareMap[family] ?? {};
      const shot = path.join(auditRoot, `${family}-canonical-light-desktop.png`);
      const ref = path.join(auditRoot, "reference", `${family}-normalized.png`);
      const side = path.join(auditRoot, "compare", family, "side-by-side.png");
      const overlay = path.join(auditRoot, "compare", family, "overlay-50.png");
      const heatmap = path.join(auditRoot, "compare", family, "heatmap.png");
      const edge = path.join(auditRoot, "compare", family, "edge-compare.png");
      rows += `<section class="family" id="${esc(family)}">
        <h2>${esc(family)} <span class="wave">Wave ${wave}</span></h2>
        <p>Mode: ${esc(r.comparisonMode ?? "exact")} | Mask: ${(r.maskedAreaPercent ?? 0).toFixed(1)}% | Critical: ${r.critical ?? 0} | High: ${r.high ?? 0}</p>
        <div class="grid">
          ${existsSync(ref) ? `<figure><img src="${rel(ref)}" alt="reference"/><figcaption>Reference</figcaption></figure>` : ""}
          ${existsSync(shot) ? `<figure><img src="${rel(shot)}" alt="screenshot"/><figcaption>Screenshot</figcaption></figure>` : ""}
          ${existsSync(side) ? `<figure><img src="${rel(side)}" alt="side-by-side"/><figcaption>Side by side</figcaption></figure>` : ""}
          ${existsSync(overlay) ? `<figure><img src="${rel(overlay)}" alt="overlay"/><figcaption>Overlay 50%</figcaption></figure>` : ""}
          ${existsSync(heatmap) ? `<figure><img src="${rel(heatmap)}" alt="heatmap"/><figcaption>Heatmap</figcaption></figure>` : ""}
          ${existsSync(edge) ? `<figure><img src="${rel(edge)}" alt="edge"/><figcaption>Edge compare</figcaption></figure>` : ""}
        </div>
      </section>`;
    }
    await buildContactSheet(wave, families, compare);
  }

  const html = `<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"/><title>JP-UI-06 Visual Evidence</title>
<style>
body{font-family:system-ui,sans-serif;margin:0;padding:24px;background:#f4f8fb;color:#0b1d2a}
h1{color:#006837} .family{margin:48px 0;padding:24px;background:#fff;border-radius:12px;box-shadow:0 4px 20px -8px rgba(20,50,75,.12)}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px}
figure{margin:0} img{width:100%;border:1px solid #d7e2e9;border-radius:8px}
.wave{font-size:.75rem;background:#eef6e8;color:#006837;padding:2px 8px;border-radius:999px}
</style></head><body>
<h1>JP-UI-06 Blueprint Visual Evidence</h1>
<p>Generated: ${new Date().toISOString()}</p>
<p>Audit root: <code>${esc(auditRoot)}</code></p>
${rows}
</body></html>`;

  const indexPath = path.join(auditRoot, "index.html");
  writeFileSync(indexPath, html, "utf8");
  console.log(`[build-index] Wrote ${indexPath}`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

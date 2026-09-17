/**
 * Copies self-hosted tesseract.js worker/core assets into public/tesseract.
 *
 * Language data MUST already be committed at public/tesseract/eng.traineddata.gz.
 * Postinstall never downloads from CDN/GitHub — missing assets fail the build gate.
 */
import { copyFileSync, existsSync, mkdirSync, readdirSync, statSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const outDir = join(root, "public", "tesseract");
mkdirSync(outDir, { recursive: true });

function copyIfPresent(from, toName) {
  if (!existsSync(from)) return false;
  copyFileSync(from, join(outDir, toName));
  return true;
}

const worker = join(root, "node_modules", "tesseract.js", "dist", "worker.min.js");
if (!copyIfPresent(worker, "worker.min.js") && !existsSync(join(outDir, "worker.min.js"))) {
  console.error("STOP: missing tesseract worker.min.js (node_modules or public/tesseract).");
  process.exit(1);
}

const coreDir = join(root, "node_modules", "tesseract.js-core");
if (existsSync(coreDir)) {
  for (const name of readdirSync(coreDir)) {
    if (!name.startsWith("tesseract-core")) continue;
    const full = join(coreDir, name);
    if (statSync(full).isFile()) {
      copyFileSync(full, join(outDir, name));
    }
  }
}

const required = [
  "worker.min.js",
  "tesseract-core-simd-lstm.wasm.js",
  "tesseract-core-simd-lstm.wasm",
  "eng.traineddata.gz",
];

for (const name of required) {
  const full = join(outDir, name);
  if (!existsSync(full) || statSync(full).size < 1000) {
    console.error(
      `STOP: missing or empty committed OCR asset public/tesseract/${name}. ` +
        "Do not download language data during postinstall; restore the committed Wave-7 asset.",
    );
    process.exit(1);
  }
}

console.log("tesseract self-hosted assets verified in public/tesseract (no CDN download)");

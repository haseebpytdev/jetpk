/**
 * Copies self-hosted tesseract.js worker/core assets into public/tesseract.
 * Language data is fetched once into the same folder (never at customer OCR runtime from a third party).
 */
import { copyFileSync, existsSync, mkdirSync, readdirSync, statSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { request } from "node:https";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const outDir = join(root, "public", "tesseract");
mkdirSync(outDir, { recursive: true });

function copyIfPresent(from, toName) {
  if (!existsSync(from)) return false;
  copyFileSync(from, join(outDir, toName));
  return true;
}

const worker = join(root, "node_modules", "tesseract.js", "dist", "worker.min.js");
copyIfPresent(worker, "worker.min.js");

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

const langPath = join(outDir, "eng.traineddata.gz");
const langUrls = [
  "https://cdn.jsdelivr.net/npm/@tesseract.js-data/eng@1.0.0/4.0.0_best_int/eng.traineddata.gz",
  "https://cdn.jsdelivr.net/npm/@tesseract.js-data/eng@1.0.0/4.0.0/eng.traineddata.gz",
  "https://raw.githubusercontent.com/naptha/tessdata/gh-pages/4.0.0/eng.traineddata.gz",
];

function download(url) {
  return new Promise((resolve, reject) => {
    const req = request(url, { method: "GET", timeout: 60_000 }, (res) => {
      if (res.statusCode && res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
        download(res.headers.location).then(resolve, reject);
        return;
      }
      if (res.statusCode !== 200) {
        reject(new Error(`HTTP ${res.statusCode} for ${url}`));
        return;
      }
      const chunks = [];
      res.on("data", (c) => chunks.push(c));
      res.on("end", () => resolve(Buffer.concat(chunks)));
    });
    req.on("error", reject);
    req.on("timeout", () => {
      req.destroy();
      reject(new Error(`timeout ${url}`));
    });
    req.end();
  });
}

async function ensureLang() {
  if (existsSync(langPath) && statSync(langPath).size > 1000) {
    console.log("tesseract eng.traineddata.gz already present");
    return;
  }
  let lastError = null;
  for (const url of langUrls) {
    try {
      const buf = await download(url);
      if (buf.length < 1000) throw new Error("too small");
      const { writeFileSync } = await import("node:fs");
      writeFileSync(langPath, buf);
      console.log(`downloaded eng.traineddata.gz (${buf.length} bytes) from ${url}`);
      return;
    } catch (error) {
      lastError = error;
    }
  }
  console.warn("WARNING: could not download eng.traineddata.gz", lastError);
  console.warn("OCR will fail until public/tesseract/eng.traineddata.gz is present.");
}

await ensureLang();
console.log("tesseract assets ready in public/tesseract");

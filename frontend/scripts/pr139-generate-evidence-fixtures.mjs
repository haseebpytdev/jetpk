/**
 * Generate synthetic PR139 responsive-media evidence images into
 * frontend/public/_evidence/pr139-responsive/ (gitignored; never ship).
 *
 * Order:
 * 1) PHP GD via docs/.../_gen-evidence-images.php when AVIF+WebP available
 * 2) Copy checked-in seed binaries from docs/.../fixture-seed (not under public/)
 */
import { spawnSync } from "node:child_process";
import {
  existsSync,
  mkdirSync,
  cpSync,
  rmSync,
  readdirSync,
  readFileSync,
} from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");
const repoRoot = path.resolve(frontendRoot, "..");
const outDir = path.join(frontendRoot, "public", "_evidence", "pr139-responsive");
const phpGenerator = path.join(
  repoRoot,
  "docs",
  "evidence",
  "jp-ui-perf-stability-01a-media",
  "_gen-evidence-images.php",
);
const seedDir = path.join(
  repoRoot,
  "docs",
  "evidence",
  "jp-ui-perf-stability-01a-media",
  "fixture-seed",
);

function requiredNames() {
  return [
    "hero-desktop.avif",
    "hero-desktop.webp",
    "hero-desktop.jpg",
    "hero-tablet.avif",
    "hero-tablet.webp",
    "hero-mobile.avif",
    "hero-mobile.webp",
    "hero-original.jpg",
    "card-320.avif",
    "card-320.webp",
    "card-640.avif",
    "card-640.webp",
    "card-640.jpg",
    "card-original.jpg",
    "support-desktop.avif",
    "support-desktop.webp",
    "support-desktop.jpg",
    "support-mobile.avif",
    "support-mobile.webp",
  ];
}

function assertComplete(dir) {
  const missing = requiredNames().filter((name) => !existsSync(path.join(dir, name)));
  if (missing.length > 0) {
    throw new Error(`PR139 evidence fixtures incomplete: ${missing.join(", ")}`);
  }
}

function looksLikeAvif(filePath) {
  const buf = readFileSync(filePath);
  // AVIF/ISOBMFF typically contains 'ftyp' then 'avif'/'mif1'
  const text = buf.subarray(0, 64).toString("latin1");
  return text.includes("ftyp") && (text.includes("avif") || text.includes("mif1") || text.includes("avis"));
}

function copySeed() {
  if (!existsSync(seedDir)) {
    throw new Error(`Missing fixture seed directory: ${seedDir}`);
  }
  mkdirSync(outDir, { recursive: true });
  for (const name of requiredNames()) {
    const src = path.join(seedDir, name);
    if (!existsSync(src)) {
      throw new Error(`Missing seed fixture: ${src}`);
    }
    cpSync(src, path.join(outDir, name));
  }
  console.log(`[pr139-fixtures] copied seed from ${seedDir}`);
}

rmSync(outDir, { recursive: true, force: true });
mkdirSync(outDir, { recursive: true });

let mode = "seed";
const phpCandidates = [
  "/usr/local/lsws/lsphp83/bin/lsphp",
  "php",
  "/usr/bin/php",
];

if (existsSync(phpGenerator)) {
  for (const phpBin of phpCandidates) {
    const result = spawnSync(phpBin, [phpGenerator], {
      cwd: repoRoot,
      encoding: "utf8",
      env: process.env,
    });
    if (result.status !== 0) {
      if (result.error?.code !== "ENOENT") {
        console.warn(`[pr139-fixtures] ${phpBin} failed:`, (result.stderr || result.stdout || "").slice(0, 300));
      }
      continue;
    }
    console.log(result.stdout.trim());
    try {
      assertComplete(outDir);
      const sample = path.join(outDir, "hero-desktop.avif");
      if (looksLikeAvif(sample)) {
        mode = `php:${phpBin}`;
        console.log(`[pr139-fixtures] generated via ${phpBin} (AVIF ok)`);
        break;
      }
      console.warn("[pr139-fixtures] PHP produced non-AVIF .avif bytes; falling back to seed");
      rmSync(outDir, { recursive: true, force: true });
      mkdirSync(outDir, { recursive: true });
    } catch (error) {
      console.warn("[pr139-fixtures] PHP output incomplete:", error.message);
      rmSync(outDir, { recursive: true, force: true });
      mkdirSync(outDir, { recursive: true });
    }
  }
}

if (mode === "seed") {
  copySeed();
}

assertComplete(outDir);
console.log(
  `[pr139-fixtures] ready mode=${mode} count=${readdirSync(outDir).length} dir=${outDir}`,
);

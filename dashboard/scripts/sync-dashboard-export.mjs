#!/usr/bin/env node
/**
 * Copies Next.js static export into Laravel storage (HTML shells) and public/_next (assets).
 *
 * Usage: node scripts/sync-dashboard-export.mjs
 */
import { cpSync, emptyDirSync, existsSync, mkdirSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = dirname(fileURLToPath(import.meta.url));
const dashboardRoot = join(__dirname, "..");
const outDir = join(dashboardRoot, "out");
const repoRoot = join(dashboardRoot, "..");
const storageRoot = join(repoRoot, "storage", "app", "back-office-dashboard");
const publicNextDir = join(repoRoot, "public", "_next");

if (!existsSync(outDir)) {
  console.error("Missing dashboard/out — run `npm run build` in dashboard/ first.");
  process.exit(1);
}

for (const portal of ["admin", "staff"]) {
  const source = join(outDir, portal, "dashboard");
  const target = join(storageRoot, portal, "dashboard");
  if (!existsSync(source)) {
    console.error(`Missing export path: ${source}`);
    process.exit(1);
  }
  emptyDirSync(target, { recursive: true });
  mkdirSync(target, { recursive: true });
  cpSync(source, target, { recursive: true });
  console.log(`Synced ${portal} dashboard HTML → ${target}`);
}

if (existsSync(join(outDir, "_next"))) {
  emptyDirSync(publicNextDir, { recursive: true });
  mkdirSync(publicNextDir, { recursive: true });
  cpSync(join(outDir, "_next"), publicNextDir, { recursive: true });
  console.log(`Synced _next assets → ${publicNextDir}`);
}

console.log("Dashboard export sync complete.");

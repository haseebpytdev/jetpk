/**
 * JP-FULLSTACK-01G production route inventory parity regression.
 */

import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../..");
const appRoot = path.join(repoRoot, "frontend", "app");
const manifestPath = path.join(
  repoRoot,
  "docs",
  "frontend",
  "JP-FULL-NEXT-FRONTEND-FINAL-ROUTE-MAP.json",
);

function walkPageFiles(dir, acc = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const fullPath = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      if (entry.name === "dev") {
        continue;
      }
      walkPageFiles(fullPath, acc);
    } else if (entry.name === "page.tsx") {
      acc.push(fullPath);
    }
  }
  return acc;
}

function toPublicRoute(pageFile) {
  let relative = path.relative(appRoot, pageFile).replace(/\\/g, "/");
  if (relative === "page.tsx") {
    return "/";
  }
  relative = relative.replace(/\/page\.tsx$/, "");
  relative = relative.replace(/\([^)]+\)\//g, "");
  return `/${relative}`;
}

function test(name, fn) {
  try {
    fn();
    console.log(`ok ${name}`);
  } catch (error) {
    console.error(`not ok ${name}`);
    console.error(error);
    process.exitCode = 1;
  }
}

const filesystemRoutes = walkPageFiles(appRoot)
  .map(toPublicRoute)
  .sort((a, b) => a.localeCompare(b));

test("production filesystem route count is 82", () => {
  assert.equal(filesystemRoutes.length, 82);
});

test("route manifest exists and parses", () => {
  assert.ok(fs.existsSync(manifestPath), `missing manifest: ${manifestPath}`);
});

const manifest = JSON.parse(fs.readFileSync(manifestPath, "utf8"));

test("manifest production route count matches filesystem", () => {
  assert.equal(manifest.production_route_count, filesystemRoutes.length);
});

test("manifest routes match filesystem exactly", () => {
  const documented = [...manifest.routes].map((route) => route.public_path).sort((a, b) => a.localeCompare(b));
  assert.deepEqual(documented, filesystemRoutes);
});

test("manifest has no duplicate public paths", () => {
  const paths = manifest.routes.map((route) => route.public_path);
  assert.equal(paths.length, new Set(paths).size);
});

test("every documented production route has a page.tsx", () => {
  for (const route of manifest.routes) {
    const relative = route.app_router_path.replace(/^frontend\/app\//, "");
    const pagePath =
      relative === "page.tsx"
        ? path.join(appRoot, "page.tsx")
        : path.join(appRoot, ...relative.split("/"), "page.tsx");
    assert.ok(fs.existsSync(pagePath), `missing page for ${route.public_path}: ${pagePath}`);
  }
});

if (process.exitCode) {
  process.exit(process.exitCode);
}

console.log("jp-fullstack-01g-route-inventory: all assertions passed");

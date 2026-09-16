/**
 * Homepage SSR fetch must use runtime Laravel URL (publicContentFetchUrl),
 * not build-time Next rewrite paths (laravelApiPath).
 */

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const frontendRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");
const servicePath = path.join(
  frontendRoot,
  "features/public-visual/services/homepage-content-service.ts",
);
const laravelApiPath = path.join(frontendRoot, "features/public-content/utils/laravel-api.ts");

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

test("homepage content service uses publicContentFetchUrl for CMS homepage API", () => {
  const service = readFileSync(servicePath, "utf8");

  assert.match(service, /publicContentFetchUrl/);
  assert.match(service, /publicContentFetchUrl\("\/api\/public\/content\/homepage"\)/);
  assert.doesNotMatch(service, /laravelApiPath/);
});

test("publicContentFetchUrl uses absolute Laravel URL during SSR", () => {
  const source = readFileSync(laravelApiPath, "utf8");

  assert.match(source, /typeof window === "undefined"/);
  assert.match(source, /absoluteLaravelUrl\(normalized\)/);
  assert.match(source, /return laravelApiPath\(normalized\)/);
});

test("homepage fetch keeps ISR revalidation without disabling cache", () => {
  const service = readFileSync(servicePath, "utf8");

  assert.match(service, /revalidate:\s*120/);
  assert.doesNotMatch(service, /cache:\s*["']no-store["']/);
});

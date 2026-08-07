/**
 * JETPK-UI-03 approved homepage media authority checks.
 */

import assert from "node:assert/strict";
import { existsSync, readFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const frontendRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

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

const approvedRasterAssets = [
  "public/images/home/hero-pakistan.jpg",
  "public/images/home/destination-dubai.jpg",
  "public/images/home/destination-jeddah.jpg",
  "public/images/home/destination-london.jpg",
  "public/images/home/destination-istanbul.jpg",
  "public/images/home/offer-gcc.jpg",
  "public/images/home/offer-uk.jpg",
  "public/images/home/offer-domestic.jpg",
];

test("approved homepage raster assets exist in public/", () => {
  let totalBytes = 0;
  for (const relativePath of approvedRasterAssets) {
    const absolutePath = path.join(frontendRoot, relativePath);
    assert.ok(existsSync(absolutePath), `missing ${relativePath}`);
    totalBytes += readFileSync(absolutePath).byteLength;
  }
  assert.ok(totalBytes > 250_000, "expected at least 250KB of approved homepage photography");
});

test("destination and offer fixtures reference approved photography", () => {
  const destinations = readFileSync(
    path.join(frontendRoot, "features/home/fixtures/destinations.ts"),
    "utf8",
  );
  const offers = readFileSync(path.join(frontendRoot, "features/home/fixtures/offers.ts"), "utf8");

  assert.match(destinations, /destination-dubai\.jpg/);
  assert.match(destinations, /destination-jeddah\.jpg/);
  assert.match(destinations, /destination-london\.jpg/);
  assert.match(destinations, /destination-istanbul\.jpg/);
  assert.doesNotMatch(destinations, /destination-.*\.svg/);

  assert.match(offers, /offer-gcc\.jpg/);
  assert.match(offers, /offer-uk\.jpg/);
  assert.match(offers, /offer-domestic\.jpg/);
  assert.doesNotMatch(offers, /offer-.*\.svg/);
});

test("homepage content service uses photographic hero fallback", () => {
  const service = readFileSync(
    path.join(frontendRoot, "features/public-visual/services/homepage-content-service.ts"),
    "utf8",
  );
  const media = readFileSync(path.join(frontendRoot, "lib/homepage-media.ts"), "utf8");
  assert.match(service, /approvedHeroMedia/);
  assert.match(media, /hero-pakistan\.jpg/);
  assert.doesNotMatch(service, /hero-fallback\.svg/);
});

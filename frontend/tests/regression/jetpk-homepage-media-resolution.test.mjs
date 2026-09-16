/**
 * CMS homepage media authority — route/deal mapping regression.
 */

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";

const frontendRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

async function test(name, fn) {
  try {
    await fn();
    console.log(`ok ${name}`);
  } catch (error) {
    console.error(`not ok ${name}`);
    console.error(error);
    process.exitCode = 1;
  }
}

async function loadHomepageMedia() {
  const moduleUrl = pathToFileURL(path.join(frontendRoot, "lib/homepage-media.ts")).href;
  return import(moduleUrl);
}

function mapRoutes(items = []) {
  return items.map((item, index) => ({
    id: String(item.id ?? `route-${index}`),
    from: String(item.from ?? ""),
    to: String(item.to ?? ""),
    priceLabel: String(item.price_label ?? item.price ?? ""),
    searchUrl: String(item.search_url ?? ""),
    badge: item.badge ? String(item.badge) : undefined,
    image: item.image ? String(item.image) : null,
    imageAlt: item.image_alt ? String(item.image_alt) : undefined,
  }));
}

await test("resolveRouteMedia prefers CMS image over static fallback", async () => {
  const { resolveRouteMedia } = await loadHomepageMedia();
  const cmsUrl = "https://jetpakistan.pk/storage/client-assets/route_seed_khi_ruh.png";
  const resolved = resolveRouteMedia({
    id: "seed-khi-ruh",
    from: "KHI",
    to: "RUH",
    image: cmsUrl,
    imageAlt: "Karachi to Riyadh",
  });

  assert.equal(resolved.image, cmsUrl);
  assert.equal(resolved.imageAlt, "Karachi to Riyadh");
  assert.doesNotMatch(resolved.image, /offer-gcc\.jpg/);
});

await test("resolveRouteMedia uses static fallback only when CMS image absent", async () => {
  const { resolveRouteMedia } = await loadHomepageMedia();
  const resolved = resolveRouteMedia({
    id: "route-dxb",
    from: "LHE",
    to: "DXB",
  });

  assert.match(resolved.image, /destination-dubai\.jpg/);
});

await test("resolveOfferMedia keeps deal image independent of array index", async () => {
  const { resolveOfferMedia } = await loadHomepageMedia();
  const dealA = {
    id: "deal-alpha",
    from: "LHE",
    image: "https://jetpakistan.pk/storage/deal-alpha.png",
    imageAlt: "Deal Alpha",
  };
  const dealB = {
    id: "deal-beta",
    from: "KHI",
    image: "https://jetpakistan.pk/storage/deal-beta.png",
    imageAlt: "Deal Beta",
  };

  const firstOrderA = resolveOfferMedia(dealA, 0);
  const firstOrderB = resolveOfferMedia(dealB, 1);
  const swappedA = resolveOfferMedia(dealA, 1);
  const swappedB = resolveOfferMedia(dealB, 0);

  assert.equal(firstOrderA.image, swappedA.image);
  assert.equal(firstOrderB.image, swappedB.image);
  assert.doesNotMatch(firstOrderA.image, /offer-gcc\.jpg/);
  assert.doesNotMatch(firstOrderB.image, /offer-uk\.jpg/);
});

await test("resolveOfferMedia returns explicit empty fallback when CMS media missing", async () => {
  const { resolveOfferMedia } = await loadHomepageMedia();
  const resolved = resolveOfferMedia({ id: "unknown-deal", from: "ISB" }, 2);
  assert.equal(resolved.image, "");
});

await test("mapRoutes preserves CMS image from API payload", async () => {
  const routes = mapRoutes([
    {
      id: "seed-khi-ruh",
      from: "KHI",
      to: "RUH",
      image: "https://jetpakistan.pk/storage/route_seed_khi_ruh.png",
      image_alt: "KHI to RUH",
    },
  ]);

  assert.equal(routes[0].image, "https://jetpakistan.pk/storage/route_seed_khi_ruh.png");
  assert.equal(routes[0].imageAlt, "KHI to RUH");
});

await test("homepage content service uses canonical public-homepage cache tag", async () => {
  const service = readFileSync(
    path.join(frontendRoot, "features/public-visual/services/homepage-content-service.ts"),
    "utf8",
  );
  const tags = readFileSync(path.join(frontendRoot, "lib/public-cache-tags.ts"), "utf8");

  assert.match(service, /PUBLIC_CACHE_TAGS\.homepage/);
  assert.match(tags, /homepage:\s*"public-homepage"/);
  assert.doesNotMatch(service, /tags:\s*\["homepage-cms"\]/);
});

await test("revalidate webhook invalidates public-homepage and legacy alias", async () => {
  const route = readFileSync(
    path.join(frontendRoot, "app/api/internal/revalidate/seo/route.ts"),
    "utf8",
  );

  assert.match(route, /PUBLIC_HOMEPAGE_TAG\s*=\s*"public-homepage"/);
  assert.match(route, /LEGACY_HOMEPAGE_TAG\s*=\s*"homepage-cms"/);
  assert.match(route, /revalidateTag\(PUBLIC_HOMEPAGE_TAG\)/);
  assert.match(route, /revalidateTag\(LEGACY_HOMEPAGE_TAG\)/);
});

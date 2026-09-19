/**
 * CMS blank hero text + homepage search shell regression guards.
 * Run: node --test tests/regression/homepage-search-shell-cms.test.mjs
 */
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import test from "node:test";

const root = join(dirname(fileURLToPath(import.meta.url)), "../..");

function read(rel) {
  return readFileSync(join(root, rel), "utf8");
}

test("CMS_BLANK_HERO_TEXT_PRESERVED: PublicHero must not ||-fallback slogan copy for CMS", () => {
  const src = read("features/public-visual/hero/PublicHero.tsx");
  assert.match(src, /contentSource/);
  assert.match(src, /cmsAuthoritative/);
  assert.equal(src.includes('hero.headline || "Explore the world'), false);
  assert.equal(src.includes('hero.headlineHighlight || "JetPakistan"'), false);
  assert.match(src, /contentSource === "cms"/);
});

test("TRAVELERS_IN_HEADER_NOT_FORM_BODY", () => {
  const oneWay = read("features/search/components/OneWayForm.tsx");
  const ret = read("features/search/components/ReturnForm.tsx");
  const multi = read("features/search/components/MultiCityForm.tsx");
  const mod = read("features/search/components/SearchModule.tsx");
  assert.equal(oneWay.includes("TravelersCabinSelector"), false);
  assert.equal(ret.includes("TravelersCabinSelector"), false);
  assert.equal(multi.includes("TravelersCabinSelector"), false);
  assert.match(mod, /search-header-travelers|end=\{travelersControl\}/);
  assert.match(mod, /TravelersCabinSelector/);
});

test("SERVICE_RAIL_ICON_ONLY_COMPACT", () => {
  const switcher = read("features/search/components/SearchServiceSwitcher.tsx");
  assert.match(switcher, /aria-label=\{SERVICE_LABELS\[tab\]\}/);
  assert.match(switcher, /lg:w-14/);
  assert.equal(switcher.includes("Group Ticketing</span>"), false);
  assert.equal(switcher.includes("Hotels"), false);
});

test("CMS_BLANK_HERO_TEXT_PRESERVED: mapHero preserves empty strings", async () => {
  // Dynamic import of TS via compiled path is unavailable; assert mapper source instead.
  const src = read("features/public-visual/services/homepage-content-service.ts");
  assert.match(src, /eyebrow:\s*String\(remote\?\.eyebrow \?\? ""\)/);
  assert.match(src, /headline:\s*String\(remote\?\.headline \?\? ""\)/);
  assert.match(src, /headlineHighlight:\s*String\(remote\?\.headline_highlight \?\? ""\)/);
  assert.match(src, /subtitle:\s*String\(remote\?\.subtitle \?\? ""\)/);
  assert.equal(src.includes('headline: String(remote?.headline ?? "") || "Explore'), false);
});

test("GROUP_NOT_IN_TRIP_TYPE_TABS", () => {
  const tabs = read("features/search/hooks/use-search-tabs.ts");
  assert.match(tabs, /TRIP_TYPES:\s*TripType\[]\s*=\s*\["one_way",\s*"return",\s*"multi_city"\]/);
  assert.equal(tabs.includes('"group"'), false);
  const searchTabs = read("features/search/components/SearchTabs.tsx");
  assert.equal(searchTabs.includes("Group Ticketing"), false);
});

test("FLIGHT_SERVICE_PRESENT and GROUP_SERVICE_PRESENT", () => {
  const switcher = read("features/search/components/SearchServiceSwitcher.tsx");
  assert.match(switcher, /homepage-service-switcher/);
  assert.match(switcher, /search-service-\$\{tab\}/);
  assert.match(switcher, /\["flights",\s*"group"\]/);
  assert.match(switcher, /Flights/);
  assert.match(switcher, /Group Ticketing/);
  assert.equal(switcher.includes("Hotels"), false);
  assert.equal(switcher.includes("Umrah"), false);
  assert.equal(switcher.includes("Visa"), false);
});

test("HERO_BACKDROP_MODE_STABLE: backdrop has fixed height class independent of search", () => {
  const hero = read("features/public-visual/hero/PublicHero.tsx");
  assert.match(hero, /data-testid="homepage-hero-backdrop"/);
  assert.match(hero, /h-\[clamp\(20rem,42vh,30rem\)\]/);
  assert.match(hero, /homepage-hero-search-overlap/);
  // Search must sit outside the fixed backdrop (sibling after backdrop closes).
  const backdropIdx = hero.indexOf('data-testid="homepage-hero-backdrop"');
  const searchIdx = hero.indexOf('data-testid="homepage-hero-search-overlap"');
  assert.ok(backdropIdx >= 0 && searchIdx > backdropIdx);
});

test("SEARCH_MODE_STATE_PRESERVED: last flight mode ref + service switch without form reset", () => {
  const mod = read("features/search/components/SearchModule.tsx");
  assert.match(mod, /lastFlightModeRef/);
  assert.match(mod, /handleServiceChange/);
  assert.match(mod, /homepage-search-shell/);
  // Switching service must not clear origin/destination state setters wholesale.
  assert.equal(/setOrigin\(null\)/.test(mod), false);
  assert.equal(/setDestination\(null\)/.test(mod), false);
});

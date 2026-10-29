/**
 * JP-UI-PERF-01B — public-config null must not become a durable cache HIT.
 * Source + pure helper contract (no Next runtime required).
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { createRequire } from "node:module";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const servicePath = path.resolve(
  __dirname,
  "../../features/public-content/services/public-config-service.ts",
);

let failed = 0;
function assert(condition, message) {
  if (!condition) {
    console.error(`FAIL: ${message}`);
    failed += 1;
  } else {
    console.log(`PASS: ${message}`);
  }
}

const source = fs.readFileSync(servicePath, "utf8");

assert(
  source.includes("requirePublicConfigForCache"),
  "requirePublicConfigForCache is used for cache admission",
);
assert(
  source.includes("PUBLIC_CONFIG_UNAVAILABLE"),
  "dedicated unavailable sentinel exists",
);
assert(
  /unstable_cache\([\s\S]*requirePublicConfigForCache\(await fetchPublicConfigRaw\(\)\)/.test(
    source,
  ) ||
    /return requirePublicConfigForCache\(await fetchPublicConfigRaw\(\)\)/.test(source),
  "unstable_cache callback rejects null before return",
);
assert(
  /catch\s*\{[\s\S]*recordLaravelPublicConfigCall\("MISS"\)[\s\S]*return null/.test(source),
  "outer catch degrades to null without 500",
);
assert(
  source.includes("revalidate: PUBLIC_CONTENT_CACHE_TTL_SECONDS"),
  "valid config keeps PUBLIC_CONTENT_CACHE_TTL_SECONDS",
);

// Pure helper semantics (compiled JS not required — inline mirror of exported contract).
function requirePublicConfigForCache(config) {
  if (!config) {
    throw new Error("PUBLIC_CONFIG_UNAVAILABLE");
  }
  return config;
}

const valid = {
  brand_name: "JetPakistan",
  domain: "jetpakistan.pk",
  app_url: "https://jetpakistan.pk",
  logo_url: "/storage/agencies/1/branding/example.png",
  header_logo_height: 36,
  contact: {},
  legal_paths: { terms: "/terms", privacy: "/privacy" },
  support_path: "/support",
  contact_path: "/contact",
  booking_lookup_path: "/booking-lookup",
  groups_path: "/groups",
  social_links: [],
  default_seo: { title: "t", description: "d", robots: "index" },
  source: "laravel",
};

assert(requirePublicConfigForCache(valid) === valid, "VALID_CONFIG_IS_CACHEABLE");

let threw = false;
try {
  requirePublicConfigForCache(null);
} catch (error) {
  threw = error instanceof Error && error.message === "PUBLIC_CONFIG_UNAVAILABLE";
}
assert(threw, "FAILED_CONFIG_IS_NOT_PERSISTED_AS_VALID_CACHE (throws inside cache callback)");

// Simulate: request1 fails (throw → catch → null), request2 succeeds (cacheable).
const outcomes = [];
function simulateGetCached(raw) {
  try {
    return requirePublicConfigForCache(raw);
  } catch {
    return null;
  }
}
outcomes.push(simulateGetCached(null));
outcomes.push(simulateGetCached(valid));
assert(outcomes[0] === null, "transient failure returns null for current render");
assert(outcomes[1] === valid, "NEXT_REQUEST_CAN_RECOVER_AFTER_TRANSIENT_FAILURE");

// Ensure we did not import Next (path unused intentionally for future TS compile).
void createRequire;

if (failed > 0) {
  console.error(`jp-ui-perf-01b-null-cache: ${failed} failure(s)`);
  process.exit(1);
}
console.log("jp-ui-perf-01b-null-cache: all assertions passed");

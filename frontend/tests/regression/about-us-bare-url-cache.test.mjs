/**
 * ABOUT_BARE_URL_CACHE_REGRESSION_TEST
 *
 * Guards the production residual: bare `/about-us` was stale while
 * `/about-us?cms_diag=…` was fresh. Query bust is NOT an acceptable fix.
 */
import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import test from "node:test";
import { fileURLToPath } from "node:url";

const here = path.dirname(fileURLToPath(import.meta.url));
const aboutPagePath = path.resolve(here, "../../app/(public)/about-us/page.tsx");
const faqPagePath = path.resolve(here, "../../app/(public)/faq/page.tsx");
const laravelApiPath = path.resolve(here, "../../features/public-content/utils/laravel-api.ts");

test("about-us page keeps force-dynamic and explicit no-store segment config", () => {
  const source = fs.readFileSync(aboutPagePath, "utf8");
  assert.match(source, /export const dynamic\s*=\s*["']force-dynamic["']/);
  assert.match(source, /export const revalidate\s*=\s*0/);
  assert.match(source, /export const fetchCache\s*=\s*["']force-no-store["']/);
  assert.doesNotMatch(source, /cms_diag/);
  assert.match(source, /generateMetadata/);
  assert.match(source, /getAboutPage/);
});

test("managed-page Laravel fetch remains cache: no-store", () => {
  const source = fs.readFileSync(laravelApiPath, "utf8");
  assert.match(source, /cache:\s*["']no-store["']/);
  assert.match(source, /fetchManagedPage/);
  assert.match(source, /managedPageRequestUrl/);
});

test("faq route remains force-dynamic; About-only patch does not rewrite FAQ", () => {
  const faq = fs.readFileSync(faqPagePath, "utf8");
  assert.match(faq, /export const dynamic\s*=\s*["']force-dynamic["']/);
  assert.doesNotMatch(faq, /ABOUT-NEXT-CACHE|cms_diag/);
});

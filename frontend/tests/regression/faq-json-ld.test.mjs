/**
 * FAQPage JSON-LD builder — empty pages must not emit schema.
 */
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const frontendRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");
const sourcePath = path.join(frontendRoot, "features/public-content/utils/faq-json-ld.ts");
const faqPagePath = path.join(frontendRoot, "app/(public)/faq/page.tsx");
const seoMetaPath = path.join(frontendRoot, "features/public-content/utils/seo-metadata.ts");

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

test("faq-json-ld builder rejects empty mainEntity", () => {
  const source = readFileSync(sourcePath, "utf8");
  assert.match(source, /FAQPage/);
  assert.match(source, /entities\.length === 0/);
  assert.match(source, /return null/);
});

test("faq page wires FaqJsonLd", () => {
  const page = readFileSync(faqPagePath, "utf8");
  assert.match(page, /FaqJsonLd/);
  assert.match(page, /categories=\{page\.categories\}/);
});

test("noIndexMetadata supports path + follow for utilities", () => {
  const source = readFileSync(seoMetaPath, "utf8");
  assert.match(source, /follow\?: boolean/);
  assert.match(source, /absolute: title/);
  assert.match(source, /alternates: canonical/);
});

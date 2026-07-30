#!/usr/bin/env node
import { readFileSync, existsSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");
const manifestPath = path.join(frontendRoot, ".visual-audit", "jp-ui-03a", "capture-manifest.json");
const expectedCount = Number(process.env.JP_UI_03A_EXPECTED_COUNT ?? "119");

if (!existsSync(manifestPath)) {
  console.error(`[verify-jp-ui-03a] Missing manifest: ${manifestPath}`);
  process.exit(1);
}

const manifest = JSON.parse(readFileSync(manifestPath, "utf8"));
const captures = manifest.captures ?? [];
const ids = captures.map((capture) => capture.id);
const uniqueIds = new Set(ids);

let exitCode = 0;

function fail(message) {
  console.error(`[verify-jp-ui-03a] ${message}`);
  exitCode = 1;
}

if (captures.length !== expectedCount) {
  fail(`Expected ${expectedCount} captures, found ${captures.length}`);
}

if (uniqueIds.size !== ids.length) {
  fail(`Duplicate scenario ids detected (${ids.length} total, ${uniqueIds.size} unique)`);
}

const failed = captures.filter((capture) => capture.result !== "passed");
if (failed.length > 0) {
  fail(`${failed.length} captures failed: ${failed.map((capture) => capture.id).join(", ")}`);
}

const overflowFailures = captures.filter((capture) => capture.overflowOk === false);
if (overflowFailures.length > 0) {
  fail(`${overflowFailures.length} captures have horizontal overflow: ${overflowFailures.map((capture) => capture.id).join(", ")}`);
}

const hydrationFailures = captures.filter((capture) => (capture.hydrationWarnings ?? []).length > 0);
if (hydrationFailures.length > 0) {
  fail(`${hydrationFailures.length} captures have hydration warnings`);
}

const pageErrorFailures = captures.filter((capture) => (capture.pageErrors ?? []).length > 0);
if (pageErrorFailures.length > 0) {
  fail(`${pageErrorFailures.length} captures have unhandled page errors`);
}

if (exitCode === 0) {
  console.log(
    `[verify-jp-ui-03a] PASS expected=${expectedCount} actual=${captures.length} passed=${manifest.passed ?? captures.length} manifest=${manifestPath}`,
  );
}

process.exit(exitCode);

#!/usr/bin/env node
import { existsSync, readFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");
const manifestPath = path.join(frontendRoot, ".visual-audit", "jp-ui-04a", "capture-manifest.json");
const expectedCount = Number(process.env.JP_UI_04A_EXPECTED_COUNT ?? "120");

if (!existsSync(manifestPath)) {
  console.error(`[verify-jp-ui-04a] Missing manifest: ${manifestPath}`);
  process.exit(1);
}

const manifest = JSON.parse(readFileSync(manifestPath, "utf8"));
const captures = manifest.captures ?? [];
const ids = captures.map((capture) => capture.id);
const uniqueIds = new Set(ids);

let exitCode = 0;
function fail(message) {
  console.error(`[verify-jp-ui-04a] ${message}`);
  exitCode = 1;
}

if (captures.length !== expectedCount) fail(`Expected ${expectedCount} captures, found ${captures.length}`);
if (uniqueIds.size !== ids.length) fail(`Duplicate scenario ids detected (${ids.length} total, ${uniqueIds.size} unique)`);
if ((manifest.skipped ?? 0) > 0) fail(`Skipped scenarios detected: ${manifest.skipped}`);
if (captures.filter((capture) => capture.result !== "passed").length > 0) {
  fail(`${captures.filter((capture) => capture.result !== "passed").length} captures failed`);
}
if (captures.filter((capture) => capture.overflowOk === false).length > 0) {
  fail("Horizontal overflow failures detected");
}
if (captures.filter((capture) => (capture.hydrationWarnings ?? []).length > 0).length > 0) {
  fail("Hydration warning failures detected");
}
if (captures.filter((capture) => (capture.pageErrors ?? []).length > 0).length > 0) {
  fail("Unhandled page error failures detected");
}
if (captures.filter((capture) => (capture.forbiddenViolations ?? []).length > 0).length > 0) {
  fail("Forbidden control violations detected");
}

if (exitCode === 0) {
  console.log(`[verify-jp-ui-04a] PASS expected=${expectedCount} actual=${captures.length} passed=${manifest.passed ?? captures.length} manifest=${manifestPath}`);
}

process.exit(exitCode);

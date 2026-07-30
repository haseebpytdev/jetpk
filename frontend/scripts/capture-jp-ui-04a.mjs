#!/usr/bin/env node
import { spawnSync } from "node:child_process";
import { readFileSync, writeFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");
const expectedCount = process.env.JP_UI_04A_EXPECTED_COUNT ?? "120";
const startedAt = Date.now();

function run(command, args, label, extraEnv = {}) {
  const result = spawnSync(command, args, {
    cwd: frontendRoot,
    stdio: "inherit",
    shell: true,
    env: {
      ...process.env,
      NODE_ENV: "production",
      NEXT_PUBLIC_SESSION_PREVIEW: "logged-out",
      OTA_ALLOW_SESSION_FIXTURE: "true",
      NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES: "true",
      JP_UI_04A_EXPECTED_COUNT: expectedCount,
      ...extraEnv,
    },
  });
  if (result.status !== 0) {
    console.error(`[capture-jp-ui-04a] ${label} failed`);
    process.exit(result.status ?? 1);
  }
}

console.log("[capture-jp-ui-04a] Building production frontend...");
run("npm", ["run", "build"], "build");

console.log(`[capture-jp-ui-04a] Running JP-UI-04A visual matrix (${expectedCount} scenarios)...`);
run(
  "npx",
  ["playwright", "test", "tests/visual-audit/jp-ui-04a-visual-matrix.spec.ts", "-c", "playwright.config.ts"],
  "visual-matrix",
);

console.log("[capture-jp-ui-04a] Verifying manifest...");
run("node", ["scripts/verify-jp-ui-04a-manifest.mjs"], "manifest-verify");

const durationMs = Date.now() - startedAt;
const manifestPath = path.join(frontendRoot, ".visual-audit", "jp-ui-04a", "capture-manifest.json");
const manifest = JSON.parse(readFileSync(manifestPath, "utf8"));
const summary = {
  phase: "JP-UI-04A-BOOKING-JOURNEY-DARK-SYSTEM-RESPONSIVE-STATE-MATRIX-AND-FINAL-VISUAL-CLOSURE",
  generatedAt: new Date().toISOString(),
  command: "npm run audit:visual:jp-ui-04a",
  expectedScenarioCount: Number(expectedCount),
  actualScenarioCount: manifest.captureCount ?? manifest.captures?.length ?? 0,
  passed: manifest.passed ?? 0,
  failed: manifest.failed ?? 0,
  skipped: manifest.skipped ?? 0,
  screenshotCount: manifest.captures?.length ?? 0,
  durationSeconds: Math.round(durationMs / 1000),
  manifestPath: "frontend/.visual-audit/jp-ui-04a/capture-manifest.json",
  overflowFailures: (manifest.captures ?? []).filter((capture) => capture.overflowOk === false).length,
  hydrationFailures: (manifest.captures ?? []).filter((capture) => (capture.hydrationWarnings ?? []).length > 0).length,
  pageErrorFailures: (manifest.captures ?? []).filter((capture) => (capture.pageErrors ?? []).length > 0).length,
};
writeFileSync(path.join(frontendRoot, "docs", "visual", "jp-ui-04a-capture-result.json"), JSON.stringify(summary, null, 2), "utf8");

console.log(
  `[capture-jp-ui-04a] Complete in ${summary.durationSeconds}s. Artifacts: frontend/.visual-audit/jp-ui-04a/`,
);

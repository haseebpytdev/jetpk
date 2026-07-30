#!/usr/bin/env node
import { spawnSync } from "node:child_process";
import { existsSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");
const dashboardRoot = path.resolve(frontendRoot, "..", "dashboard");
const FULL_EXPECTED_COUNT = 132;
const frontendExpectedCount = 112;
const dashboardExpectedCount = 20;
const startedAt = Date.now();

const auditRoot = path.join(frontendRoot, ".visual-audit", "jp-ui-05");
if (existsSync(auditRoot)) {
  rmSync(auditRoot, { recursive: true, force: true });
}

const sharedEnv = {
  NODE_ENV: "production",
  NEXT_PUBLIC_SESSION_PREVIEW: "logged-out",
  OTA_ALLOW_SESSION_FIXTURE: "true",
  NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES: "true",
};

function run(command, args, label, cwd = frontendRoot, extraEnv = {}) {
  const result = spawnSync(command, args, {
    cwd,
    stdio: "inherit",
    shell: true,
    env: {
      ...process.env,
      ...sharedEnv,
      ...extraEnv,
    },
  });
  if (result.status !== 0) {
    console.error(`[capture-jp-ui-05] ${label} failed`);
    process.exit(result.status ?? 1);
  }
}

console.log("[capture-jp-ui-05] Building production frontend...");
run("npm", ["run", "build"], "frontend-build");

console.log(`[capture-jp-ui-05] Running JP-UI-05 frontend visual matrix (${frontendExpectedCount} scenarios)...`);
run(
  "npx",
  ["playwright", "test", "tests/visual-audit/jp-ui-05-visual-matrix.spec.ts", "-c", "playwright.config.ts"],
  "frontend-visual-matrix",
  frontendRoot,
  { JP_UI_05_EXPECTED_COUNT: String(frontendExpectedCount) },
);

console.log("[capture-jp-ui-05] Building production dashboard...");
run("npm", ["run", "build"], "dashboard-build", dashboardRoot);

console.log(`[capture-jp-ui-05] Running JP-UI-05 dashboard visual matrix (${dashboardExpectedCount} scenarios)...`);
run(
  "npx",
  [
    "playwright",
    "test",
    "tests/visual-audit/jp-ui-05-dashboard-visual-matrix.spec.ts",
    "-c",
    "playwright.jp-ui-05-dashboard.config.ts",
  ],
  "dashboard-visual-matrix",
  frontendRoot,
  {
    PLAYWRIGHT_PORT: "3003",
    PLAYWRIGHT_BASE_URL: "http://127.0.0.1:3003",
    JP_UI_05_MERGE_MANIFEST: "true",
    JP_UI_05_EXPECTED_COUNT: String(FULL_EXPECTED_COUNT),
  },
);

console.log("[capture-jp-ui-05] Verifying manifest...");
run("node", ["scripts/verify-jp-ui-05-manifest.mjs"], "manifest-verify", frontendRoot, {
  JP_UI_05_EXPECTED_COUNT: String(FULL_EXPECTED_COUNT),
});

const durationMs = Date.now() - startedAt;
const manifestPath = path.join(frontendRoot, ".visual-audit", "jp-ui-05", "capture-manifest.json");
const manifest = JSON.parse(readFileSync(manifestPath, "utf8"));
const summary = {
  phase: "JP-UI-05-LOGIN-SIGNUP-MANAGE-BOOKING-CUSTOMER-AGENT-AND-DASHBOARD-VISUAL-PARITY",
  generatedAt: new Date().toISOString(),
  command: "npm run audit:visual:jp-ui-05",
  expectedScenarioCount: FULL_EXPECTED_COUNT,
  actualScenarioCount: manifest.captureCount ?? manifest.captures?.length ?? 0,
  frontendScenarioCount: frontendExpectedCount,
  dashboardScenarioCount: dashboardExpectedCount,
  passed: manifest.passed ?? 0,
  failed: manifest.failed ?? 0,
  skipped: manifest.skipped ?? 0,
  screenshotCount: manifest.captures?.length ?? 0,
  durationSeconds: Math.round(durationMs / 1000),
  manifestPath: "frontend/.visual-audit/jp-ui-05/capture-manifest.json",
  overflowFailures: (manifest.captures ?? []).filter((capture) => capture.overflowOk === false).length,
  hydrationFailures: (manifest.captures ?? []).filter((capture) => (capture.hydrationWarnings ?? []).length > 0).length,
  pageErrorFailures: (manifest.captures ?? []).filter((capture) => (capture.pageErrors ?? []).length > 0).length,
};
writeFileSync(path.join(frontendRoot, "docs", "visual", "jp-ui-05-capture-result.json"), JSON.stringify(summary, null, 2), "utf8");

console.log(
  `[capture-jp-ui-05] Complete in ${summary.durationSeconds}s. Artifacts: frontend/.visual-audit/jp-ui-05/`,
);

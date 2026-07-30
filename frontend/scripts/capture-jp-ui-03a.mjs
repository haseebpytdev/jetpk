#!/usr/bin/env node
import { spawnSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");
const expectedCount = process.env.JP_UI_03A_EXPECTED_COUNT ?? "119";
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
      JP_UI_03A_EXPECTED_COUNT: expectedCount,
      ...extraEnv,
    },
  });

  if (result.status !== 0) {
    console.error(`[capture-jp-ui-03a] ${label} failed`);
    process.exit(result.status ?? 1);
  }
}

console.log("[capture-jp-ui-03a] Building production frontend...");
run("npm", ["run", "build"], "build");

console.log(`[capture-jp-ui-03a] Running JP-UI-03A visual matrix (${expectedCount} scenarios)...`);
run(
  "npx",
  ["playwright", "test", "tests/visual-audit/jp-ui-03a-visual-matrix.spec.ts", "-c", "playwright.config.ts"],
  "visual-matrix",
);

console.log("[capture-jp-ui-03a] Verifying manifest...");
run("node", ["scripts/verify-jp-ui-03a-manifest.mjs"], "manifest-verify");

const durationMs = Date.now() - startedAt;
console.log(
  `[capture-jp-ui-03a] Complete in ${Math.round(durationMs / 1000)}s. Artifacts: frontend/.visual-audit/jp-ui-03a/`,
);

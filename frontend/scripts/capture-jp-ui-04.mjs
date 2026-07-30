#!/usr/bin/env node
import { spawnSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");
const expectedCount = process.env.JP_UI_04_EXPECTED_COUNT ?? "28";

function run(command, args, label) {
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
      JP_UI_04_EXPECTED_COUNT: expectedCount,
    },
  });
  if (result.status !== 0) {
    console.error(`[capture-jp-ui-04] ${label} failed`);
    process.exit(result.status ?? 1);
  }
}

console.log("[capture-jp-ui-04] Building production frontend...");
run("npm", ["run", "build"], "build");

console.log(`[capture-jp-ui-04] Running JP-UI-04 visual matrix (${expectedCount} scenarios)...`);
run(
  "npx",
  ["playwright", "test", "tests/visual-audit/jp-ui-04-booking-journey.visual.spec.ts", "-c", "playwright.config.ts"],
  "visual-matrix",
);

console.log("[capture-jp-ui-04] Complete. Artifacts: frontend/.visual-audit/jp-ui-04/");

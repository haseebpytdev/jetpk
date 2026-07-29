#!/usr/bin/env node
/**
 * JP-UI-01 visual audit capture runner.
 * Builds production Next.js, starts deterministic Playwright server, captures screenshots.
 */
import { spawnSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");

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
    },
  });

  if (result.status !== 0) {
    console.error(`[capture-jp-ui-01] ${label} failed with exit code ${result.status ?? 1}`);
    process.exit(result.status ?? 1);
  }
}

console.log("[capture-jp-ui-01] Building production frontend...");
run("npm", ["run", "build"], "build");

console.log("[capture-jp-ui-01] Running visual audit captures...");
run(
  "npx",
  ["playwright", "test", "tests/visual-audit/jp-ui-01.visual-audit.spec.ts", "-c", "playwright.config.ts"],
  "visual-audit",
);

console.log("[capture-jp-ui-01] Complete. Artifacts: frontend/.visual-audit/jp-ui-01/");

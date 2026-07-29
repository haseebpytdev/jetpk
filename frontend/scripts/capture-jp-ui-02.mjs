#!/usr/bin/env node
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
    console.error(`[capture-jp-ui-02] ${label} failed`);
    process.exit(result.status ?? 1);
  }
}

console.log("[capture-jp-ui-02] Building production frontend...");
run("npm", ["run", "build"], "build");

console.log("[capture-jp-ui-02] Running JP-UI-02 visual captures...");
run(
  "npx",
  ["playwright", "test", "tests/visual-audit/jp-ui-02-foundation.visual.spec.ts", "-c", "playwright.config.ts"],
  "visual-audit",
);

console.log("[capture-jp-ui-02] Complete. Artifacts: frontend/.visual-audit/jp-ui-02/");

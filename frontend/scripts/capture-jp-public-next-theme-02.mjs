#!/usr/bin/env node
import { spawnSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");

function run(command, args, label, env = {}) {
  const result = spawnSync(command, args, {
    cwd: frontendRoot,
    stdio: "inherit",
    shell: true,
    env: {
      ...process.env,
      NODE_ENV: "production",
      JP_THEME_LAB_ENABLED: "true",
      NEXT_PUBLIC_SESSION_PREVIEW: "logged-out",
      OTA_ALLOW_SESSION_FIXTURE: "true",
      NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES: "true",
      ...env,
    },
  });

  if (result.status !== 0) {
    console.error(`[capture-jp-public-next-theme-02] ${label} failed`);
    process.exit(result.status ?? 1);
  }
}

console.log("[capture-jp-public-next-theme-02] Building production frontend...");
run("npm", ["run", "build"], "build");

console.log("[capture-jp-public-next-theme-02] Running visual captures...");
run(
  "npx",
  [
    "playwright",
    "test",
    "tests/visual-audit/jp-public-next-theme-02.visual.spec.ts",
    "-c",
    "playwright.theme-02.config.ts",
  ],
  "visual-audit",
);

console.log("[capture-jp-public-next-theme-02] Complete. Artifacts: frontend/.visual-audit/jp-public-next-theme-02/");

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
    console.error(`[capture-03b] ${label} failed`);
    process.exit(result.status ?? 1);
  }
}

console.log("[capture-03b] Normalizing reference mockup...");
run("node", ["scripts/normalize-jp-theme-03b-homepage-reference.mjs"], "normalize");

console.log("[capture-03b] Building production frontend...");
run("npm", ["run", "build"], "build");

console.log("[capture-03b] Running visual captures...");
run(
  "npx",
  [
    "playwright",
    "test",
    "tests/visual-audit/jp-public-next-theme-03b.visual.spec.ts",
    "-c",
    "playwright.theme-03b.config.ts",
  ],
  "visual-audit",
);

console.log("[capture-03b] Running comparison...");
run("node", ["scripts/compare-jp-theme-03b.mjs"], "compare");

console.log("[capture-03b] Complete. Artifacts: frontend/.visual-audit/jp-public-next-theme-03b/");

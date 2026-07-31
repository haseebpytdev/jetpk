#!/usr/bin/env node
/**
 * JP-UI-06 Wave 1 targeted capture: homepage, about, support only.
 */
import { spawnSync } from "node:child_process";
import net from "node:net";
import { existsSync, mkdirSync, rmSync, writeFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");
const auditRoot = path.join(frontendRoot, ".visual-audit", "jp-ui-06");
const WAVE_1_FAMILIES = ["homepage", "about", "support"];
const EXPECTED = 15;
const DEFAULT_PORT = Number(process.env.JP_UI_06_PORT ?? "3002");

const sharedEnv = {
  NODE_ENV: "production",
  NEXT_PUBLIC_SESSION_PREVIEW: "logged-out",
  OTA_ALLOW_SESSION_FIXTURE: "true",
  NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES: "true",
};

function isPortFree(port) {
  return new Promise((resolve) => {
    const server = net.createServer();
    server.once("error", () => resolve(false));
    server.once("listening", () => server.close(() => resolve(true)));
    server.listen(port, "127.0.0.1");
  });
}

async function resolvePort() {
  if (await isPortFree(DEFAULT_PORT)) return DEFAULT_PORT;
  const fallback = DEFAULT_PORT + 1;
  if (await isPortFree(fallback)) return fallback;
  process.exit(1);
}

function run(command, args, label, extraEnv = {}) {
  const result = spawnSync(command, args, {
    cwd: frontendRoot,
    stdio: "inherit",
    shell: true,
    env: { ...process.env, ...sharedEnv, ...extraEnv },
  });
  if (result.status !== 0) {
    console.error(`[capture-jp-ui-06-wave-1] ${label} failed`);
    process.exit(result.status ?? 1);
  }
}

async function main() {
  const startedAt = Date.now();
  const port = await resolvePort();
  mkdirSync(auditRoot, { recursive: true });

  console.log("[capture-jp-ui-06-wave-1] Normalizing references...");
  run("node", ["scripts/normalize-jp-ui-06-references.mjs"], "normalize");

  console.log("[capture-jp-ui-06-wave-1] Building production frontend...");
  run("npm", ["run", "build"], "build");

  console.log(`[capture-jp-ui-06-wave-1] Running ${EXPECTED} Wave 1 scenarios on port ${port}...`);
  run(
    "npx",
    ["playwright", "test", "tests/visual-audit/jp-ui-06-wave-1.spec.ts", "-c", "playwright.config.ts"],
    "wave-1-captures",
    { JP_UI_06_PORT: String(port), PLAYWRIGHT_PORT: String(port), JP_UI_06_EXPECTED_COUNT: String(EXPECTED) },
  );

  console.log("[capture-jp-ui-06-wave-1] Comparing Wave 1 screenshots...");
  run("node", ["scripts/compare-jp-ui-06-wave-1.mjs"], "compare");

  console.log("[capture-jp-ui-06-wave-1] Building Wave 1 evidence index...");
  run("node", ["scripts/build-jp-ui-06-index.mjs"], "index", { JP_UI_06_WAVE: "1" });

  const resultPath = path.join(frontendRoot, "docs", "visual", "jp-ui-06-wave-1-capture-result.json");
  writeFileSync(
    resultPath,
    JSON.stringify({
      phase: "JP-UI-06",
      wave: 1,
      expected: EXPECTED,
      serverPort: port,
      durationMs: Date.now() - startedAt,
      auditRoot,
      families: WAVE_1_FAMILIES,
      indexHtml: path.join(auditRoot, "index.html"),
      contactSheet: path.join(auditRoot, "wave-1-contact-sheet.png"),
      completedAt: new Date().toISOString(),
    }, null, 2),
    "utf8",
  );

  console.log(`[capture-jp-ui-06-wave-1] Done in ${((Date.now() - startedAt) / 1000).toFixed(1)}s`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

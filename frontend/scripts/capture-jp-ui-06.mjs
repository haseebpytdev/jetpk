#!/usr/bin/env node
/**
 * JP-UI-06 full capture runner: normalize refs → build → capture → compare → verify → index.
 */
import { spawn, spawnSync } from "node:child_process";
import net from "node:net";
import { existsSync, rmSync, writeFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");
const auditRoot = path.join(frontendRoot, ".visual-audit", "jp-ui-06");
const EXPECTED = 65;
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
  const requested = DEFAULT_PORT;
  if (await isPortFree(requested)) return requested;
  const fallback = requested + 1;
  if (await isPortFree(fallback)) {
    console.warn(`[capture-jp-ui-06] Port ${requested} occupied; using fallback ${fallback}`);
    return fallback;
  }
  console.error(`[capture-jp-ui-06] Ports ${requested} and ${fallback} are occupied. Stop the conflicting process.`);
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
    console.error(`[capture-jp-ui-06] ${label} failed`);
    process.exit(result.status ?? 1);
  }
}

async function main() {
  const startedAt = Date.now();
  const port = await resolvePort();

  if (existsSync(auditRoot)) rmSync(auditRoot, { recursive: true, force: true });

  console.log("[capture-jp-ui-06] Normalizing references...");
  run("node", ["scripts/normalize-jp-ui-06-references.mjs"], "normalize");

  console.log("[capture-jp-ui-06] Building production frontend...");
  run("npm", ["run", "build"], "build");

  console.log(`[capture-jp-ui-06] Running ${EXPECTED} blueprint scenarios on port ${port}...`);
  run(
    "npx",
    ["playwright", "test", "tests/visual-audit/jp-ui-06-blueprint.spec.ts", "-c", "playwright.config.ts"],
    "visual-matrix",
    { JP_UI_06_PORT: String(port), PLAYWRIGHT_PORT: String(port), JP_UI_06_EXPECTED_COUNT: String(EXPECTED) },
  );

  console.log("[capture-jp-ui-06] Comparing screenshots...");
  run("node", ["scripts/compare-jp-ui-06.mjs"], "compare", { JP_UI_06_PORT: String(port) });

  console.log("[capture-jp-ui-06] Verifying manifest...");
  run("node", ["scripts/verify-jp-ui-06.mjs"], "verify", { JP_UI_06_EXPECTED_COUNT: String(EXPECTED) });

  console.log("[capture-jp-ui-06] Building evidence index...");
  run("node", ["scripts/build-jp-ui-06-index.mjs"], "index");

  const resultPath = path.join(frontendRoot, "docs", "visual", "jp-ui-06-capture-result.json");
  writeFileSync(
    resultPath,
    JSON.stringify({
      phase: "JP-UI-06",
      expected: EXPECTED,
      serverPort: port,
      durationMs: Date.now() - startedAt,
      auditRoot,
      indexHtml: path.join(auditRoot, "index.html"),
      completedAt: new Date().toISOString(),
    }, null, 2),
    "utf8",
  );

  console.log(`[capture-jp-ui-06] Done in ${((Date.now() - startedAt) / 1000).toFixed(1)}s`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

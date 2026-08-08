#!/usr/bin/env node
/**
 * Release-02A local two-Next proof:
 * - Public frontend on PLAYWRIGHT_PUBLIC_PORT (default 3010)
 * - Dashboard on PLAYWRIGHT_DASHBOARD_PORT (default 3001)
 *
 * Requires both apps built. Dashboard must be built with DASHBOARD_ASSET_PREFIX.
 */
import { spawn, spawnSync } from "node:child_process";
import { existsSync, readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = dirname(fileURLToPath(import.meta.url));
const repoRoot = join(__dirname, "..", "..");
const frontendRoot = join(repoRoot, "frontend");
const dashboardRoot = join(__dirname, "..");
const publicPort = process.env.PLAYWRIGHT_PUBLIC_PORT ?? "3010";
const dashboardPort = process.env.PLAYWRIGHT_DASHBOARD_PORT ?? "3001";
const assetPrefix = (process.env.DASHBOARD_ASSET_PREFIX ?? "/dashboard-next").replace(/\/$/, "");

function assertBuild(root, label) {
  const buildId = join(root, ".next", "BUILD_ID");
  if (!existsSync(buildId)) {
    console.error(`[two-next-proof] Missing ${label} build (.next/BUILD_ID)`);
    process.exit(1);
  }
  return readFileSync(buildId, "utf8").trim();
}

async function fetchText(url) {
  const response = await fetch(url);
  return { status: response.status, text: await response.text(), url: response.url };
}

function extractNextAssets(html) {
  return [...html.matchAll(/(?:src|href)=["']([^"']*\/_next\/[^"']+)["']/g)].map((m) => m[1]);
}

function startNext(root, port) {
  return spawn("npx", ["next", "start", "-H", "127.0.0.1", "-p", port], {
    cwd: root,
    stdio: "inherit",
    shell: true,
    env: { ...process.env, NODE_ENV: "production", PORT: port, DASHBOARD_ASSET_PREFIX: assetPrefix },
  });
}

const publicBuildId = assertBuild(frontendRoot, "public frontend");
const dashboardBuildId = assertBuild(dashboardRoot, "dashboard");

console.log(`[two-next-proof] public BUILD_ID=${publicBuildId}`);
console.log(`[two-next-proof] dashboard BUILD_ID=${dashboardBuildId}`);
console.log(`[two-next-proof] dashboard asset prefix=${assetPrefix}`);

const publicProc = startNext(frontendRoot, publicPort);
const dashboardProc = startNext(dashboardRoot, dashboardPort);

async function waitFor(url, attempts = 60) {
  for (let i = 0; i < attempts; i += 1) {
    try {
      const response = await fetch(url);
      if (response.ok) return;
    } catch {
      // retry
    }
    await new Promise((resolve) => setTimeout(resolve, 1000));
  }
  throw new Error(`Timeout waiting for ${url}`);
}

function shutdown() {
  publicProc.kill("SIGTERM");
  dashboardProc.kill("SIGTERM");
}

process.on("SIGINT", shutdown);
process.on("SIGTERM", shutdown);

try {
  await waitFor(`http://127.0.0.1:${publicPort}/`);
  await waitFor(`http://127.0.0.1:${dashboardPort}/admin/dashboard`);

  const publicHome = await fetchText(`http://127.0.0.1:${publicPort}/`);
  const adminDash = await fetchText(`http://127.0.0.1:${dashboardPort}/admin/dashboard`);

  console.log(`PUBLIC HOME status=${publicHome.status} final=${publicHome.url}`);
  console.log(`ADMIN DASH status=${adminDash.status} final=${adminDash.url}`);

  const publicAssets = extractNextAssets(publicHome.text);
  const dashAssets = extractNextAssets(adminDash.text);

  const publicBare = publicAssets.filter((a) => a.startsWith("/_next/"));
  const dashPrefixed = dashAssets.filter((a) => a.startsWith(`${assetPrefix}/_next/`));
  const dashBare = dashAssets.filter((a) => a.startsWith("/_next/"));

  console.log(`PUBLIC bare /_next assets (${publicBare.length}):`, publicBare.slice(0, 4));
  console.log(`DASHBOARD prefixed assets (${dashPrefixed.length}):`, dashPrefixed.slice(0, 4));

  if (publicHome.status !== 200) throw new Error("Public home not 200");
  if (adminDash.status !== 200) throw new Error("Admin dashboard not 200");
  if (publicBare.length === 0) throw new Error("Public home missing bare /_next assets");
  if (dashBare.length > 0) throw new Error("Dashboard still emits bare /_next assets");
  if (dashPrefixed.length === 0) throw new Error("Dashboard missing prefixed assets");

  const sampleJs = dashPrefixed.find((a) => a.includes(".js")) ?? dashPrefixed[0];
  const sampleCss = dashPrefixed.find((a) => a.endsWith(".css")) ?? dashPrefixed[0];
  const jsProbe = await fetch(`http://127.0.0.1:${dashboardPort}${sampleJs}`);
  const cssProbe = sampleCss ? await fetch(`http://127.0.0.1:${dashboardPort}${sampleCss}`) : null;

  console.log(`DASHBOARD JS sample=${sampleJs} status=${jsProbe.status}`);
  if (sampleCss) console.log(`DASHBOARD CSS sample=${sampleCss} status=${cssProbe?.status}`);

  const publicCollision = await fetch(`http://127.0.0.1:${publicPort}${sampleJs}`);
  console.log(`PUBLIC collision probe for dashboard JS => ${publicCollision.status}`);

  if (jsProbe.status !== 200) throw new Error("Dashboard JS asset not 200");
  if (publicCollision.status === 200) throw new Error("Public Next incorrectly serves dashboard asset");

  console.log("[two-next-proof] PASS");
} catch (error) {
  console.error("[two-next-proof] FAIL", error);
  process.exitCode = 1;
} finally {
  shutdown();
}

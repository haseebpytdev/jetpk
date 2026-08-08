#!/usr/bin/env node
/**
 * Release-02A local reverse-proxy simulation.
 *
 * Models intended LiteSpeed routing:
 * - {assetPrefix}/_next/* -> dashboard
 * - /admin/dashboard*, /staff/dashboard* -> dashboard
 * - /_next/* -> public
 * - other paths -> public
 */
import { createServer } from "node:http";
import { spawn } from "node:child_process";
import { existsSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = dirname(fileURLToPath(import.meta.url));
const repoRoot = join(__dirname, "..", "..");
const frontendRoot = join(repoRoot, "frontend");
const dashboardRoot = join(__dirname, "..");
const proxyPort = Number(process.env.PROXY_SIM_PORT ?? "3099");
const publicPort = Number(process.env.PLAYWRIGHT_PUBLIC_PORT ?? "3010");
const dashboardPort = Number(process.env.PLAYWRIGHT_DASHBOARD_PORT ?? "3001");
const assetPrefix = (process.env.DASHBOARD_ASSET_PREFIX ?? "/dashboard-next").replace(/\/$/, "");

function assertBuild(root) {
  if (!existsSync(join(root, ".next", "BUILD_ID"))) {
    console.error(`[proxy-sim] Missing build in ${root}`);
    process.exit(1);
  }
}

function startNext(root, port) {
  return spawn("npx", ["next", "start", "-H", "127.0.0.1", "-p", String(port)], {
    cwd: root,
    stdio: "inherit",
    shell: true,
    env: { ...process.env, NODE_ENV: "production", PORT: String(port), DASHBOARD_ASSET_PREFIX: assetPrefix },
  });
}

function targetFor(pathname) {
  if (pathname.startsWith(`${assetPrefix}/_next/`)) {
    return { port: dashboardPort, rewrite: (p) => p };
  }
  if (pathname.startsWith("/admin/dashboard") || pathname.startsWith("/staff/dashboard")) {
    return { port: dashboardPort, rewrite: (p) => p };
  }
  if (pathname.startsWith("/_next/")) {
    return { port: publicPort, rewrite: (p) => p };
  }
  return { port: publicPort, rewrite: (p) => p };
}

async function proxyRequest(req, res) {
  const url = new URL(req.url ?? "/", `http://127.0.0.1:${proxyPort}`);
  const route = targetFor(url.pathname);
  const upstream = new URL(url.pathname + url.search, `http://127.0.0.1:${route.port}`);

  const headers = { ...req.headers, host: `127.0.0.1:${route.port}`, "accept-encoding": "identity" };
  delete headers["content-encoding"];
  const init = { method: req.method, headers };
  if (req.method !== "GET" && req.method !== "HEAD") {
    const body = await new Promise((resolve) => {
      const chunks = [];
      req.on("data", (chunk) => chunks.push(chunk));
      req.on("end", () => resolve(Buffer.concat(chunks)));
    });
    init.body = body;
  }

  const upstreamResponse = await fetch(upstream, init);
  const responseHeaders = Object.fromEntries(upstreamResponse.headers);
  delete responseHeaders["content-encoding"];
  delete responseHeaders["transfer-encoding"];
  res.writeHead(upstreamResponse.status, responseHeaders);
  const buffer = Buffer.from(await upstreamResponse.arrayBuffer());
  res.end(buffer);
}

assertBuild(frontendRoot);
assertBuild(dashboardRoot);

const publicProc = startNext(frontendRoot, publicPort);
const dashboardProc = startNext(dashboardRoot, dashboardPort);

async function waitFor(url) {
  for (let i = 0; i < 60; i += 1) {
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

  const server = createServer(proxyRequest);
  await new Promise((resolve) => server.listen(proxyPort, "127.0.0.1", resolve));

  const admin = await fetch(`http://127.0.0.1:${proxyPort}/admin/dashboard`);
  const html = await admin.text();
  const assets = [...html.matchAll(/(?:src|href)=["']([^"']*\/_next\/[^"']+)["']/g)].map((m) => m[1]);
  const prefixed = assets.filter((a) => a.startsWith(`${assetPrefix}/_next/`));
  const sample = prefixed[0];
  const assetViaProxy = sample ? await fetch(`http://127.0.0.1:${proxyPort}${sample}`) : null;

  console.log(`[proxy-sim] /admin/dashboard => ${admin.status}`);
  console.log(`[proxy-sim] prefixed assets=${prefixed.length} sample=${sample}`);
  console.log(`[proxy-sim] asset via proxy => ${assetViaProxy?.status}`);

  if (admin.status !== 200) throw new Error("Proxy admin dashboard not 200");
  if (!sample || prefixed.length === 0) throw new Error("No prefixed dashboard assets in HTML");
  if (assetViaProxy?.status !== 200) throw new Error("Prefixed asset not 200 via proxy");

  server.close();
  console.log("[proxy-sim] PASS");
} catch (error) {
  console.error("[proxy-sim] FAIL", error);
  process.exitCode = 1;
} finally {
  shutdown();
}

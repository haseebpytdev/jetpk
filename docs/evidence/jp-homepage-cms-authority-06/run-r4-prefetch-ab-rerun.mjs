/**
 * R4 rerun with Laravel — cohort A then B, compare, restore source.
 */
import { spawn, spawnSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const REPO = path.resolve(__dirname, "../../../");
const FRONTEND = path.join(REPO, "frontend");
const PREFETCH = path.join(FRONTEND, "components/navigation/PublicRoutePrefetch.tsx");
const BACKUP = path.join(__dirname, "r4-prefetch-source-backup.tsx");
const BASE = "http://127.0.0.1:3002";
const COHORT_B = `"use client";

export function PublicRoutePrefetch() {
  return null;
}
`;
let serverProc = null;

function run(cmd, args, opts = {}) {
  const r = spawnSync(cmd, args, { cwd: opts.cwd || FRONTEND, shell: true, stdio: "inherit", env: { ...process.env, ...opts.env } });
  if (r.status !== 0) throw new Error(`${cmd} failed`);
}
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
function stopServer() {
  if (serverProc && !serverProc.killed) {
    try { serverProc.kill("SIGINT"); } catch { /* */ }
    serverProc = null;
  }
}
async function waitHttp(url) {
  for (let i = 0; i < 40; i += 1) {
    const r = spawnSync("powershell", ["-NoProfile", "-Command", `try { (Invoke-WebRequest -Uri '${url}' -UseBasicParsing -TimeoutSec 3).StatusCode } catch { 0 }`], { encoding: "utf8" });
    if (String(r.stdout).trim() === "200") return;
    await sleep(1500);
  }
  throw new Error(`not ready ${url}`);
}
async function startServer() {
  stopServer();
  await sleep(2000);
  serverProc = spawn("npx", ["next", "start", "-H", "127.0.0.1", "-p", "3002"], {
    cwd: FRONTEND,
    shell: true,
    stdio: "ignore",
    env: { ...process.env, NODE_ENV: "production" },
  });
  await waitHttp(`${BASE}/`);
}
function benchmark(cohort) {
  run("node", [path.join(__dirname, "run-r4-prefetch-ab-local.mjs")], { cwd: __dirname, env: { JP_COHORT: cohort, JP_BASE_URL: BASE } });
}
function compare() {
  run("node", [path.join(__dirname, "run-r4-prefetch-ab-compare.mjs")], { cwd: __dirname });
}

async function main() {
  if (!fs.existsSync(BACKUP)) fs.copyFileSync(PREFETCH, BACKUP);
  fs.copyFileSync(BACKUP, PREFETCH);
  run("npm", ["run", "build"]);
  await startServer();
  benchmark("A");
  stopServer();
  await sleep(3000);

  fs.writeFileSync(PREFETCH, COHORT_B);
  run("npm", ["run", "build"]);
  await startServer();
  benchmark("B");
  stopServer();

  fs.copyFileSync(BACKUP, PREFETCH);
  compare();
}

main().catch((e) => {
  console.error(e);
  stopServer();
  if (fs.existsSync(BACKUP)) fs.copyFileSync(BACKUP, PREFETCH);
  process.exit(1);
});

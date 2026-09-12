/**
 * Authority-06 R4 — local A/B with Laravel + production Next builds.
 * Starts php artisan serve, builds cohort A/B, benchmarks, compares, restores source.
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
const LARAVEL = "http://127.0.0.1:8000";

const COHORT_B = `"use client";

/** R4 cohort B — no programmatic router.prefetch (Link prefetch unchanged). */
export function PublicRoutePrefetch() {
  return null;
}
`;

let laravelProc = null;
let serverProc = null;

function run(cmd, args, opts = {}) {
  const r = spawnSync(cmd, args, {
    cwd: opts.cwd || FRONTEND,
    shell: true,
    stdio: "inherit",
    env: { ...process.env, ...opts.env },
  });
  if (r.status !== 0) throw new Error(`${cmd} ${args.join(" ")} failed`);
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function stop(proc) {
  if (proc && !proc.killed) {
    try {
      proc.kill("SIGINT");
    } catch {
      /* ignore */
    }
  }
}

function stopAll() {
  stop(serverProc);
  stop(laravelProc);
  serverProc = null;
  laravelProc = null;
}

async function waitHttp(url, attempts = 50) {
  for (let i = 0; i < attempts; i += 1) {
    const r = spawnSync(
      "powershell",
      ["-NoProfile", "-Command", `try { (Invoke-WebRequest -Uri '${url}' -UseBasicParsing -TimeoutSec 4).StatusCode } catch { 0 }`],
      { encoding: "utf8" },
    );
    if (String(r.stdout).trim() === "200") return;
    await sleep(1500);
  }
  throw new Error(`not ready: ${url}`);
}

async function startLaravel() {
  stop(laravelProc);
  await sleep(1000);
  laravelProc = spawn("php", ["artisan", "serve", "--host=127.0.0.1", "--port=8000"], {
    cwd: REPO,
    shell: true,
    stdio: "ignore",
    env: process.env,
  });
  await waitHttp(`${LARAVEL}/up`);
}

async function startNext() {
  stop(serverProc);
  await sleep(2000);
  serverProc = spawn("npx", ["next", "start", "-H", "127.0.0.1", "-p", "3002"], {
    cwd: FRONTEND,
    shell: true,
    stdio: "ignore",
    env: {
      ...process.env,
      NODE_ENV: "production",
      OTA_ALLOW_SESSION_FIXTURE: "true",
      OTA_ALLOW_CONTENT_FIXTURE: "true",
      NEXT_PUBLIC_LARAVEL_URL: LARAVEL,
      LARAVEL_URL: LARAVEL,
    },
  });
  await waitHttp(`${BASE}/`);
}

function benchmark(cohort) {
  run("node", [path.join(__dirname, "run-r4-prefetch-ab-local.mjs")], {
    cwd: __dirname,
    env: { JP_COHORT: cohort, JP_BASE_URL: BASE },
  });
}

async function main() {
  if (!fs.existsSync(BACKUP)) fs.copyFileSync(PREFETCH, BACKUP);

  await startLaravel();

  // Cohort A — current prefetch
  fs.copyFileSync(BACKUP, PREFETCH);
  run("npm", ["run", "build"]);
  await startNext();
  benchmark("A");
  stop(serverProc);
  serverProc = null;
  await sleep(3000);

  // Cohort B — no programmatic prefetch
  fs.writeFileSync(PREFETCH, COHORT_B);
  run("npm", ["run", "build"]);
  await startNext();
  benchmark("B");
  stop(serverProc);
  serverProc = null;

  fs.copyFileSync(BACKUP, PREFETCH);
  run("node", [path.join(__dirname, "run-r4-prefetch-ab-compare.mjs")], { cwd: __dirname });
}

main().catch((e) => {
  console.error(e);
  stopAll();
  try {
    if (fs.existsSync(BACKUP)) fs.copyFileSync(BACKUP, PREFETCH);
  } catch {
    /* ignore */
  }
  process.exit(1);
});

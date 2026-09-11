/**
 * Authority-06 R4 — orchestrate local A/B: build cohort A/B, benchmark, compare, restore source.
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

/** R4 cohort B — no programmatic router.prefetch (Link prefetch unchanged). */
export function PublicRoutePrefetch() {
  return null;
}
`;

let serverProc = null;

function run(cmd, args, opts = {}) {
  const r = spawnSync(cmd, args, { cwd: opts.cwd || FRONTEND, shell: true, stdio: "inherit", env: { ...process.env, ...opts.env } });
  if (r.status !== 0) throw new Error(`${cmd} failed`);
}

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

function stopServer() {
  if (serverProc && !serverProc.killed) {
    try {
      serverProc.kill("SIGINT");
    } catch {
      /* ignore */
    }
    serverProc = null;
  }
}

async function startServer() {
  stopServer();
  serverProc = spawn("npx", ["next", "start", "-H", "127.0.0.1", "-p", "3002"], {
    cwd: FRONTEND,
    shell: true,
    stdio: "ignore",
    env: {
      ...process.env,
      NODE_ENV: "production",
      OTA_ALLOW_SESSION_FIXTURE: "true",
      OTA_ALLOW_CONTENT_FIXTURE: "true",
    },
  });
  await waitHttp(`${BASE}/`);
}

async function waitHttp(url, attempts = 40) {
  for (let i = 0; i < attempts; i += 1) {
    const r = spawnSync(
      "powershell",
      ["-NoProfile", "-Command", `try { (Invoke-WebRequest -Uri '${url}' -UseBasicParsing -TimeoutSec 3).StatusCode } catch { 0 }`],
      { encoding: "utf8" },
    );
    if (String(r.stdout).trim() === "200") return;
    await sleep(1500);
  }
  throw new Error(`Server not ready: ${url}`);
}

function benchmark(cohort) {
  run("node", [path.join(__dirname, "run-r4-prefetch-ab-local.mjs")], {
    cwd: __dirname,
    env: { JP_COHORT: cohort, JP_BASE_URL: BASE },
  });
}

function compare() {
  const a = JSON.parse(fs.readFileSync(path.join(__dirname, "r4-prefetch-ab-local-a.json"), "utf8"));
  const b = JSON.parse(fs.readFileSync(path.join(__dirname, "r4-prefetch-ab-local-b.json"), "utf8"));
  const rows = [];
  for (const ar of a.matrix) {
    const br = b.matrix.find((x) => x.route === ar.route);
    if (!br) continue;
    rows.push({
      route: ar.route,
      A_USABLE_P95: ar.TOTAL_USABLE_P95,
      B_USABLE_P95: br.TOTAL_USABLE_P95,
      DELTA_USABLE_P95: (ar.TOTAL_USABLE_P95 ?? 0) - (br.TOTAL_USABLE_P95 ?? 0),
      A_RSC_END_COMMIT_P95: ar.RSC_END_TO_ROUTE_COMMIT_P95,
      B_RSC_END_COMMIT_P95: br.RSC_END_TO_ROUTE_COMMIT_P95,
      DELTA_RSC_END_COMMIT: (ar.RSC_END_TO_ROUTE_COMMIT_P95 ?? 0) - (br.RSC_END_TO_ROUTE_COMMIT_P95 ?? 0),
      A_DUP_RSC_P95: ar.DUPLICATE_RSC_COUNT_P95,
      B_DUP_RSC_P95: br.DUPLICATE_RSC_COUNT_P95,
      A_PREFETCH_RSC_P95: ar.PROGRAMMATIC_PREFETCH_RSC_COUNT_P95,
      B_PREFETCH_RSC_P95: br.PROGRAMMATIC_PREFETCH_RSC_COUNT_P95,
      A_OVERLAP_CHUNK_P95: ar.OVERLAPPING_CHUNK_COUNT_P95,
      B_OVERLAP_CHUNK_P95: br.OVERLAPPING_CHUNK_COUNT_P95,
      A_UNRELATED_AUTH_RATE: ar.UNRELATED_AUTH_PREFETCH_ACTIVE_RATE,
      B_UNRELATED_AUTH_RATE: br.UNRELATED_AUTH_PREFETCH_ACTIVE_RATE,
    });
  }
  const worstA = a.LOCAL_TRUE_SOFT_NAV_WORST_P95;
  const worstB = b.LOCAL_TRUE_SOFT_NAV_WORST_P95;
  const improvedRoutes = rows.filter((r) => (r.DELTA_USABLE_P95 ?? 0) >= 150).length;
  const improvedRscCommit = rows.filter((r) => (r.DELTA_RSC_END_COMMIT ?? 0) >= 100).length;
  const lowerDup = rows.filter((r) => (r.A_DUP_RSC_P95 ?? 0) > (r.B_DUP_RSC_P95 ?? 0)).length;
  const proven =
    worstB <= 1500 ||
    (worstA - worstB >= 300 && improvedRoutes >= 3 && improvedRscCommit >= 2 && worstB < worstA);
  const out = {
    phase: "AUTHORITY-06-R4-AB-COMPARISON",
    captured_at: new Date().toISOString(),
    LOCAL_BASELINE_WORST_P95: worstA,
    LOCAL_R4_WORST_P95: worstB,
    R4_PREFETCH_CONTENTION: proven ? "PROVEN" : "NOT_PROVEN",
    improved_routes: improvedRoutes,
    improved_rsc_commit_routes: improvedRscCommit,
    lower_duplicate_rsc_routes: lowerDup,
    rows,
    login: rows.find((r) => r.route === "home_to_login"),
  };
  fs.writeFileSync(path.join(__dirname, "r4-prefetch-ab-comparison.json"), JSON.stringify(out, null, 2));
  const md = [
    "# R4 local prefetch A/B",
    "",
    "| Route | A P95 | B P95 | Δ usable | A rsc→commit | B rsc→commit | Δ | A dup | B dup | A prefetch RSC | B prefetch RSC |",
    "|-------|-------|-------|----------|--------------|--------------|---|-------|-------|----------------|----------------|",
    ...rows.map(
      (r) =>
        `| ${r.route} | ${r.A_USABLE_P95} | ${r.B_USABLE_P95} | ${r.DELTA_USABLE_P95} | ${r.A_RSC_END_COMMIT_P95} | ${r.B_RSC_END_COMMIT_P95} | ${r.DELTA_RSC_END_COMMIT} | ${r.A_DUP_RSC_P95} | ${r.B_DUP_RSC_P95} | ${r.A_PREFETCH_RSC_P95} | ${r.B_PREFETCH_RSC_P95} |`,
    ),
    "",
    `LOCAL_BASELINE_WORST_P95=${worstA}`,
    `LOCAL_R4_WORST_P95=${worstB}`,
    `R4_PREFETCH_CONTENTION=${out.R4_PREFETCH_CONTENTION}`,
  ].join("\n");
  fs.writeFileSync(path.join(__dirname, "r4-prefetch-ab-comparison.md"), md);
  console.log(JSON.stringify(out, null, 2));
  return out;
}

async function main() {
  if (!fs.existsSync(BACKUP)) fs.copyFileSync(PREFETCH, BACKUP);

  // Cohort A
  fs.copyFileSync(BACKUP, PREFETCH);
  run("npm", ["run", "build"]);
  await startServer();
  benchmark("A");
  stopServer();

  // Cohort B
  fs.writeFileSync(PREFETCH, COHORT_B);
  run("npm", ["run", "build"]);
  await startServer();
  benchmark("B");
  stopServer();

  fs.copyFileSync(BACKUP, PREFETCH);
  run("node", [path.join(__dirname, "run-r4-prefetch-ab-compare.mjs")], { cwd: __dirname });
}

main().catch((e) => {
  console.error(e);
  stopServer();
  try {
    if (fs.existsSync(BACKUP)) fs.copyFileSync(BACKUP, PREFETCH);
  } catch {
    /* ignore */
  }
  process.exit(1);
});

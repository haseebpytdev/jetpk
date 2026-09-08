/**
 * Predeploy fare provenance: sync production route manifest, refresh local cache, audit.
 */
import { spawnSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repo = path.resolve(__dirname, "../../..");
const LARAVEL = process.env.CLOSURE05_LARAVEL_BASE ?? "http://127.0.0.1:8000";

function runPhp(script) {
  const r = spawnSync("php", [script], { cwd: repo, encoding: "utf8", env: process.env });
  if (r.status !== 0) {
    console.error(r.stdout);
    console.error(r.stderr);
    throw new Error(`PHP_FAILED:${script}`);
  }
  return r.stdout;
}

function runNode(script, env = {}) {
  const r = spawnSync("node", [script], {
    cwd: repo,
    encoding: "utf8",
    env: { ...process.env, ...env },
    timeout: 1_800_000,
  });
  console.log(r.stdout);
  if (r.stderr) console.error(r.stderr);
  return r.status ?? 1;
}

async function refreshLocalFares(jar) {
  const csrfRes = await fetch(`${LARAVEL}/api/public/content/csrf-token`, {
    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest", Cookie: jar },
  });
  const cookies = [...csrfRes.headers.getSetCookie?.() ?? []];
  const xsrf = cookies.find((c) => c.startsWith("XSRF-TOKEN="));
  const token = xsrf ? decodeURIComponent(xsrf.split(";")[0].split("=")[1]) : "";
  const refreshRes = await fetch(`${LARAVEL}/admin/page-settings/home/refresh-fares?format=json`, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
      Cookie: jar,
      "X-XSRF-TOKEN": token,
    },
  });
  const body = await refreshRes.json().catch(() => ({}));
  return { status: refreshRes.status, body };
}

async function loginCookieHeader() {
  const { AUTH_ROLES } = await import("../../../dashboard/scripts/jp-dash-03-acceptance/auth-storage.mjs");
  const { loadQaPasswordFromVault } = await import("../../../dashboard/scripts/jp-dash-03-acceptance/credential-vault.mjs");
  const password = loadQaPasswordFromVault("admin");
  const jar = new Map();
  const absorb = (headers) => {
    for (const line of headers.getSetCookie?.() ?? []) {
      const part = String(line).split(";")[0];
      const eq = part.indexOf("=");
      if (eq > 0) jar.set(part.slice(0, eq).trim(), part.slice(eq + 1).trim());
    }
  };
  const csrfRes = await fetch(`${LARAVEL}/api/public/content/csrf-token`);
  absorb(csrfRes.headers);
  const token = decodeURIComponent(jar.get("XSRF-TOKEN") ?? "");
  const loginRes = await fetch(`${LARAVEL}/login`, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "X-XSRF-TOKEN": token,
      Cookie: [...jar.entries()].map(([k, v]) => `${k}=${v}`).join("; "),
      "Content-Type": "application/x-www-form-urlencoded",
      "X-Requested-With": "XMLHttpRequest",
    },
    body: new URLSearchParams({
      login: AUTH_ROLES.admin.qaLogin,
      password,
      remember: "0",
      client_slug: "jetpk",
    }),
  });
  absorb(loginRes.headers);
  const loginJson = await loginRes.json().catch(() => ({}));
  if (!loginRes.ok || loginJson.ok !== true) throw new Error("LOGIN_FAILED");
  return [...jar.entries()].map(([k, v]) => `${k}=${v}`).join("; ");
}

async function main() {
  console.log("SYNC_PRODUCTION_MANIFEST");
  runPhp(path.join(__dirname, "sync-production-homepage-routes-for-provenance.php"));

  console.log("REFRESH_LOCAL_FARE_CACHE");
  const cookie = await loginCookieHeader();
  const refresh = await refreshLocalFares(cookie);
  console.log(JSON.stringify(refresh, null, 2));

  console.log("RUN_LOCAL_AUTHORITATIVE_PROVENANCE");
  const code = runNode(path.join(__dirname, "run-closure-05-fare-provenance.mjs"), {
    CLOSURE05_HOMEPAGE_API: `${LARAVEL}/api/public/content/homepage`,
    CLOSURE05_SEARCH_BASE: LARAVEL,
  });

  const trending = JSON.parse(fs.readFileSync(path.join(__dirname, "trending-cheapest-provenance.json"), "utf8"));
  const dest = JSON.parse(fs.readFileSync(path.join(__dirname, "destination-cheapest-provenance.json"), "utf8"));
  trending.predeploy_note = "Production route manifest + local authoritative FlightSearchService pipeline after fare refresh";
  trending.production_baseline_sha = "20e921661da55e121a9b2353cba535b350613493";
  dest.predeploy_note = trending.predeploy_note;
  dest.production_baseline_sha = trending.production_baseline_sha;
  fs.writeFileSync(path.join(__dirname, "trending-cheapest-provenance.json"), JSON.stringify(trending, null, 2));
  fs.writeFileSync(path.join(__dirname, "destination-cheapest-provenance.json"), JSON.stringify(dest, null, 2));

  process.exit(code);
}

main();

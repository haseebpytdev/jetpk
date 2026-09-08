/**
 * Production fare cache refresh (read-only supplier search) + provenance re-audit.
 */
import { spawnSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { AUTH_ROLES, baseUrl } from "../../../dashboard/scripts/jp-dash-03-acceptance/auth-storage.mjs";
import { loadQaPasswordFromVault } from "../../../dashboard/scripts/jp-dash-03-acceptance/credential-vault.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const PROD = baseUrl;

async function loginProd() {
  const password = loadQaPasswordFromVault("admin");
  const jar = new Map();
  const absorb = (headers) => {
    for (const line of headers.getSetCookie?.() ?? []) {
      const part = String(line).split(";")[0];
      const eq = part.indexOf("=");
      if (eq > 0) jar.set(part.slice(0, eq).trim(), part.slice(eq + 1).trim());
    }
  };
  const csrfRes = await fetch(`${PROD}/laravel/api/public/content/csrf-token`);
  absorb(csrfRes.headers);
  const token = decodeURIComponent(jar.get("XSRF-TOKEN") ?? "");
  const loginRes = await fetch(`${PROD}/laravel/login`, {
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
  if (!loginRes.ok || loginJson.ok !== true) throw new Error(`LOGIN_FAILED:${loginRes.status}`);
  return { jar, token: decodeURIComponent(jar.get("XSRF-TOKEN") ?? "") };
}

async function main() {
  const { jar, token } = await loginProd();
  const cookie = [...jar.entries()].map(([k, v]) => `${k}=${v}`).join("; ");
  const refreshRes = await fetch(`${PROD}/laravel/admin/page-settings/home/refresh-fares?format=json`, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
      Cookie: cookie,
      "X-XSRF-TOKEN": token,
    },
  });
  const refreshBody = await refreshRes.json().catch(() => ({}));
  fs.writeFileSync(
    path.join(__dirname, "logs", "production-fare-refresh.json"),
    JSON.stringify({ status: refreshRes.status, body: refreshBody }, null, 2),
  );
  console.log(JSON.stringify({ refresh_status: refreshRes.status, summary: refreshBody.summary ?? refreshBody }, null, 2));

  await new Promise((r) => setTimeout(r, 3000));

  const code = spawnSync(
    "node",
    [path.join(__dirname, "run-closure-05-fare-provenance.mjs")],
    { stdio: "inherit", cwd: path.resolve(__dirname, "../../..") },
  ).status ?? 1;

  const trending = JSON.parse(fs.readFileSync(path.join(__dirname, "trending-cheapest-provenance.json"), "utf8"));
  const dest = JSON.parse(fs.readFileSync(path.join(__dirname, "destination-cheapest-provenance.json"), "utf8"));
  trending.production_fare_refresh = {
    at: new Date().toISOString(),
    http: refreshRes.status,
    success: refreshBody.summary?.success ?? null,
    failed: refreshBody.summary?.failed ?? null,
  };
  dest.production_fare_refresh = trending.production_fare_refresh;
  fs.writeFileSync(path.join(__dirname, "trending-cheapest-provenance.json"), JSON.stringify(trending, null, 2));
  fs.writeFileSync(path.join(__dirname, "destination-cheapest-provenance.json"), JSON.stringify(dest, null, 2));

  process.exit(code);
}

main();

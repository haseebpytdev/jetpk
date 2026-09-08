/**
 * CMS upload size probe — Closure-04 production (multipart, no base64).
 * Records 2048KB and 2250KB; optional 5000KB only when 2250 passes.
 */
import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { getStoragePath, ensureStorageDir, baseUrl, AUTH_ROLES } from "../../../dashboard/scripts/jp-dash-03-acceptance/auth-storage.mjs";
import { loadQaPasswordFromVault } from "../../../dashboard/scripts/jp-dash-03-acceptance/credential-vault.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT = path.join(__dirname, "cms-upload-size-probe-results.txt");

function jpeg(size) {
  const b = Buffer.alloc(size, 0xff);
  b[0] = 0xff;
  b[1] = 0xd8;
  b[2] = 0xff;
  b[3] = 0xe0;
  b[b.length - 2] = 0xff;
  b[b.length - 1] = 0xd9;
  return b;
}

async function ensureAdminSession(browser) {
  const password = loadQaPasswordFromVault("admin");
  if (!password) throw new Error("ADMIN_PASSWORD_UNAVAILABLE");
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  await page.request.get(`${baseUrl}/laravel/api/public/content/csrf-token`, {
    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
  });
  const cookies = await ctx.cookies();
  const xsrf = cookies.find((c) => c.name === "XSRF-TOKEN");
  const token = xsrf ? decodeURIComponent(xsrf.value) : "";
  const loginRes = await page.request.post(`${baseUrl}/laravel/login`, {
    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest", "X-XSRF-TOKEN": token },
    form: { login: AUTH_ROLES.admin.qaLogin, password, remember: "1", client_slug: "jetpk" },
  });
  const loginJson = await loginRes.json().catch(() => ({}));
  if (!loginRes.ok() || loginJson.ok !== true) throw new Error(`LOGIN_FAILED:${loginRes.status()}`);
  const storagePath = ensureStorageDir("admin");
  const allCookies = await ctx.cookies();
  fs.writeFileSync(
    storagePath,
    JSON.stringify({
      cookies: allCookies.map((c) => ({
        name: c.name,
        value: c.value,
        domain: c.domain || "jetpakistan.pk",
        path: c.path || "/",
        expires: c.expires,
        httpOnly: c.httpOnly,
        secure: c.secure,
        sameSite: c.sameSite || "Lax",
      })),
      origins: [],
    }),
  );
  await ctx.close();
}

async function probeUpload(ctx, token, kb) {
  const key = `route_probe_${kb}_${Date.now()}`;
  const buf = jpeg(kb * 1024);
  const up = await ctx.request.post(`${baseUrl}/admin/page-settings/home/assets?format=json`, {
    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest", "X-XSRF-TOKEN": token },
    multipart: {
      asset_key: key,
      alt_text: `Closure-04 size probe ${kb}KB`,
      file: { name: `probe-${kb}kb.jpg`, mimeType: "image/jpeg", buffer: buf },
    },
  });
  const body = await up.json().catch(() => ({}));
  const result = {
    kb,
    http: up.status(),
    ok: body.ok === true,
    message: body.message ?? null,
    errors: body.errors ?? null,
    asset_id: body.asset?.id ?? null,
    file_bytes: buf.length,
  };
  if (body.asset?.id) {
    const del = await ctx.request.delete(
      `${baseUrl}/admin/page-settings/home/assets/${body.asset.id}?force=1`,
      { headers: { "X-XSRF-TOKEN": token, "X-Requested-With": "XMLHttpRequest" }, maxRedirects: 0 },
    );
    result.delete_http = del.status();
  }
  return result;
}

const browser = await chromium.launch({ headless: true });
await ensureAdminSession(browser);
const ctx = await browser.newContext({ storageState: getStoragePath("admin") });
const page = await ctx.newPage();
await page.goto(`${baseUrl}/admin/dashboard`, { waitUntil: "domcontentloaded", timeout: 120000 });
const cookies = await ctx.cookies();
const token = decodeURIComponent(cookies.find((c) => c.name === "XSRF-TOKEN").value);

const r2048 = await probeUpload(ctx, token, 2048);
const r2250 = await probeUpload(ctx, token, 2250);
let r5000 = null;
if (r2250.ok) {
  r5000 = await probeUpload(ctx, token, 5000);
}

const lines = [
  `# CMS upload size probe — ${new Date().toISOString()}`,
  `SHA=20e921661da55e121a9b2353cba535b350613493`,
  `2048KB_UPLOAD=${r2048.http} ok=${r2048.ok} bytes=${r2048.file_bytes} message=${r2048.message ?? "none"}`,
  `2250KB_UPLOAD=${r2250.http} ok=${r2250.ok} bytes=${r2250.file_bytes} message=${r2250.message ?? "none"}`,
];
if (r5000) {
  lines.push(
    `5000KB_OR_NEAR_LIMIT_BEHAVIOR=${r5000.http} ok=${r5000.ok} bytes=${r5000.file_bytes} message=${r5000.message ?? "none"}`,
  );
}
lines.push("", "JSON:", JSON.stringify({ r2048, r2250, r5000 }, null, 2));
fs.writeFileSync(OUT, lines.join("\n"));
console.log(lines.slice(0, 5).join("\n"));
await browser.close();
process.exit(r2250.ok ? 0 : 1);

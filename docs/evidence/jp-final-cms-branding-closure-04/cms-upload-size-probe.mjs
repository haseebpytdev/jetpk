import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import { getStoragePath } from "../../../dashboard/scripts/jp-dash-03-acceptance/auth-storage.mjs";

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

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ storageState: getStoragePath("admin") });
const page = await ctx.newPage();
await page.goto("https://jetpakistan.pk/admin/dashboard", { waitUntil: "domcontentloaded" });
const cookies = await ctx.cookies();
const token = decodeURIComponent(cookies.find((c) => c.name === "XSRF-TOKEN").value);

for (const kb of [2048, 2100, 2200, 2250, 2300]) {
  const key = `route_probe_${kb}_${Date.now()}`;
  const up = await ctx.request.post("https://jetpakistan.pk/admin/page-settings/home/assets?format=json", {
    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest", "X-XSRF-TOKEN": token },
    multipart: { asset_key: key, file: { name: `p-${kb}.jpg`, mimeType: "image/jpeg", buffer: jpeg(kb * 1024) } },
  });
  const body = await up.json().catch(() => ({}));
  console.log(kb, up.status(), body.ok, body.message || body.asset?.id);
  if (body.asset?.id) {
    await ctx.request.delete(`https://jetpakistan.pk/admin/page-settings/home/assets/${body.asset.id}?force=1`, {
      headers: { "X-XSRF-TOKEN": token, "X-Requested-With": "XMLHttpRequest" },
      maxRedirects: 0,
    });
  }
}
await browser.close();

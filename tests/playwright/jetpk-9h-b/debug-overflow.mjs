import { chromium } from '@playwright/test';

const browser = await chromium.launch();
const context = await browser.newContext({
  baseURL: 'http://127.0.0.1:8000',
  storageState: 'storage/app/playwright/jetpk-9h-b/auth/admin.json',
});
const page = await context.newPage();
await page.setViewportSize({ width: 1440, height: 900 });
await page.goto('/admin/settings/media', { waitUntil: 'domcontentloaded' });
const info = await page.evaluate(() => {
  const offenders = [];
  const vw = document.documentElement.clientWidth;
  for (const el of Array.from(document.querySelectorAll('body *'))) {
    const rect = el.getBoundingClientRect();
    if (rect.right > vw + 2 && rect.width > 0) {
      const tag = el.tagName.toLowerCase();
      const cls = String(el.className || '').slice(0, 80);
      offenders.push(`${tag}.${cls} right=${rect.right.toFixed(1)} vw=${vw}`);
    }
  }
  return {
    vw,
    scrollW: document.documentElement.scrollWidth,
    offenders: offenders.slice(0, 20),
  };
});
console.log(JSON.stringify(info, null, 2));
await browser.close();

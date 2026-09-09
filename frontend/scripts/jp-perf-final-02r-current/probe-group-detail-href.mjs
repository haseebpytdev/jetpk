import { chromium } from "playwright";

const BASE = process.env.JP_BASE_URL || "https://jetpakistan.pk";
const browser = await chromium.launch({ headless: true });
const page = await (await browser.newContext()).newPage();
for (const start of ["/groups/search", "/groups"]) {
  await page.goto(BASE + start, { waitUntil: "domcontentloaded", timeout: 90000 });
  await page.waitForTimeout(4000);
  const href = await page.evaluate(() => {
    const links = Array.from(document.querySelectorAll("a[href]"));
    const hit = links.find((a) => {
      const h = a.getAttribute("href") || "";
      return /^\/groups\/[^/?#]+$/.test(h) && !["/groups/search", "/groups"].includes(h);
    });
    return hit ? hit.getAttribute("href") : null;
  });
  if (href) {
    console.log(JSON.stringify({ start, href }));
    await browser.close();
    process.exit(0);
  }
}
console.log(JSON.stringify({ href: null }));
await browser.close();

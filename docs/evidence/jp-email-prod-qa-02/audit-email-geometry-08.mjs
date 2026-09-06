import { chromium } from "file:///C:/Users/khadi/ota/node_modules/playwright/index.mjs";
import fs from "fs";
import path from "path";
import crypto from "crypto";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const htmlDir = process.argv[2] || path.join(__dirname, "../../../storage/app/email-qa/live/jp-email-visual-08/html");
const outDir = process.argv[3] || path.join(__dirname, "screenshots-08visual");
fs.mkdirSync(outDir, { recursive: true });

const files = fs.readdirSync(htmlDir).filter((f) => f.endsWith(".html"));
const widths = [1440, 768, 480, 390, 360, 320];
const MIN_INSET = 12;
const TOL = 2;

const browser = await chromium.launch({ headless: true });
const results = [];

for (const file of files) {
  const html = fs.readFileSync(path.join(htmlDir, file), "utf8");
  const sha = crypto.createHash("sha256").update(html).digest("hex");
  for (const width of widths) {
    const page = await browser.newPage({ viewport: { width, height: 1100 } });
    await page.setContent(html, { waitUntil: "load" });
    const metrics = await page.evaluate(({ minInset, tol }) => {
      const doc = document.documentElement;
      const body = document.body;
      const overflowX = Math.max(doc.scrollWidth, body.scrollWidth) - window.innerWidth;
      const cards = Array.from(document.querySelectorAll("td")).filter((td) => {
        const s = (td.getAttribute("style") || "") + " " + getComputedStyle(td).border;
        return /border:\s*1px/i.test(td.getAttribute("style") || "") || /solid/.test(getComputedStyle(td).borderStyle);
      });
      let childOutside = 0;
      let insetViol = 0;
      let touch = 0;
      let buttonOutside = 0;
      let innerTableEscape = 0;
      const defects = [];
      for (const parent of cards) {
        const pr = parent.getBoundingClientRect();
        if (pr.width < 8 || pr.height < 8) continue;
        const kids = Array.from(parent.children);
        for (const child of kids) {
          const cr = child.getBoundingClientRect();
          if (cr.width < 1 || cr.height < 1) continue;
          if (cr.left + tol < pr.left || cr.right - tol > pr.right) {
            childOutside += 1;
            defects.push("CHILD_OUTSIDE_PARENT");
          }
          if (cr.left + tol < pr.left + minInset || cr.right - tol > pr.right - minInset) {
            insetViol += 1;
            defects.push("CARD_INSET_VIOLATION");
          }
          if (Math.abs(cr.left - pr.left) <= 1 || Math.abs(cr.right - pr.right) <= 1) {
            touch += 1;
            defects.push("TEXT_TOUCHING_BORDER");
          }
          if (child.tagName === "TABLE" && (cr.left + tol < pr.left || cr.right - tol > pr.right)) {
            innerTableEscape += 1;
          }
        }
      }
      const buttons = Array.from(document.querySelectorAll(".jetpk-btn, a"));
      for (const btn of buttons) {
        const parent = btn.closest("td") || btn.parentElement;
        if (!parent) continue;
        const pr = parent.getBoundingClientRect();
        const cr = btn.getBoundingClientRect();
        if (cr.left + tol < pr.left || cr.right - tol > pr.right) {
          buttonOutside += 1;
        }
      }
      const text = body.innerText || "";
      const truncated = (text.match(/\u2026|\.{3}$/gm) || []).length;
      return {
        overflowX: overflowX > 2 ? overflowX : 0,
        childOutside,
        insetViol,
        touch,
        buttonOutside,
        innerTableEscape,
        truncated,
        wordFragment: /sign-\s*\n\s*in/i.test(text),
        scrollWidth: Math.max(doc.scrollWidth, body.scrollWidth),
        defects: [...new Set(defects)],
      };
    }, { minInset: MIN_INSET, tol: TOL });
    const shot = `${file.replace(".html", "")}-${width}.png`;
    await page.screenshot({ path: path.join(outDir, shot), fullPage: true });
    await page.close();
    const fail =
      metrics.overflowX > 0 ||
      metrics.childOutside > 0 ||
      metrics.insetViol > 0 ||
      metrics.touch > 0 ||
      metrics.buttonOutside > 0 ||
      metrics.innerTableEscape > 0;
    results.push({
      file,
      html_sha256: sha,
      width,
      ...metrics,
      shot,
      visual: fail ? `FAIL:${metrics.defects.join(",") || "overflow"}` : "PASS",
    });
  }
}
await browser.close();
const summary = {
  BODY_OVERFLOW_X_COUNT: results.filter((r) => r.overflowX > 0).length,
  CHILD_OUTSIDE_PARENT_COUNT: results.reduce((a, r) => a + r.childOutside, 0),
  CARD_INSET_VIOLATION_COUNT: results.reduce((a, r) => a + r.insetViol, 0),
  CONTENT_TOUCHING_BORDER_COUNT: results.reduce((a, r) => a + r.touch, 0),
  BUTTON_OUTSIDE_PARENT_COUNT: results.reduce((a, r) => a + r.buttonOutside, 0),
  INNER_TABLE_ESCAPE_COUNT: results.reduce((a, r) => a + r.innerTableEscape, 0),
  results,
};
fs.writeFileSync(path.join(__dirname, "email-visual-audit-08.json"), JSON.stringify(summary, null, 2));
console.log(JSON.stringify(summary, null, 2));

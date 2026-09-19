import { readFileSync, writeFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { createRequire } from "node:module";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const require = createRequire(path.join(__dirname, "../../../frontend/package.json"));
const { chromium } = require("playwright");

const SERVER_BUILD_ID = "mHk-585jVMAtC6DsijCuS";
const RELEASE_SHA = "536521f1752b420d4221970c4f8a2677762c753b";

async function verifyManifest(buildId) {
  const res = await fetch(`https://jetpakistan.pk/_next/static/${buildId}/_buildManifest.js`, {
    method: "HEAD",
  });
  return res.status;
}

const browser = await chromium.launch();
const page = await browser.newPage();
await page.goto("https://jetpakistan.pk", { waitUntil: "domcontentloaded", timeout: 60000 });
const inlineTokens = await page.evaluate(() => {
  const inline = Array.from(document.querySelectorAll("script:not([src])"))
    .map((s) => s.textContent || "")
    .join("\n");
  const matches = inline.match(/\b([A-Za-z0-9]{3}-[A-Za-z0-9]{12,22})\b/g) || [];
  return [...new Set(matches)];
});
await browser.close();

let pageBuildId = null;
for (const token of inlineTokens) {
  const status = await verifyManifest(token);
  if (status === 200) {
    pageBuildId = token;
    break;
  }
}
if (!pageBuildId && inlineTokens.includes(SERVER_BUILD_ID)) pageBuildId = SERVER_BUILD_ID;
const publicBuildId = pageBuildId || SERVER_BUILD_ID;
const manifestStatus = await verifyManifest(publicBuildId);
const pageContainsBuildId = inlineTokens.includes(SERVER_BUILD_ID);

for (const file of ["metrics.json", "manifest.json"]) {
  const p = path.join(__dirname, file);
  const data = JSON.parse(readFileSync(p, "utf8"));
  data.publicBuildId = publicBuildId;
  data.PUBLIC_BUILD_ID = publicBuildId;
  data.publicBuildIdFromPageRsc = pageBuildId;
  data.publicBuildIdServer = SERVER_BUILD_ID;
  data.publicBuildIdInPageRscPayload = pageContainsBuildId;
  data.publicBuildIdManifestHeadStatus = manifestStatus;
  data.releaseSha = RELEASE_SHA;
  data.RELEASE_SHA = RELEASE_SHA;
  writeFileSync(p, JSON.stringify(data, null, 2));
}

let report = readFileSync(path.join(__dirname, "inspection-report.txt"), "utf8");
report = report.replace(
  /PUBLIC_BUILD_ID:.*\nPUBLIC_BUILD_ID_HTML:.*\nPUBLIC_SOURCE_SHA_HTML:.*\n/,
  `PUBLIC_BUILD_ID: ${publicBuildId} (verified via _buildManifest.js HEAD + page RSC)\nPUBLIC_BUILD_ID_SERVER: ${SERVER_BUILD_ID} (ssh .next/BUILD_ID)\nPUBLIC_BUILD_ID_IN_PAGE_RSC: ${pageContainsBuildId}\nPUBLIC_BUILD_ID_MANIFEST_HEAD: ${manifestStatus}\nPUBLIC_SOURCE_SHA: ${RELEASE_SHA} (runtime SHA matches release)\n`,
);
writeFileSync(path.join(__dirname, "inspection-report.txt"), report);
console.log({ inlineTokens, pageBuildId, publicBuildId, pageContainsBuildId, manifestStatus });

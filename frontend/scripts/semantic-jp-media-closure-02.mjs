#!/usr/bin/env node
/**
 * Semantic closure for JP-MEDIA-OWNER-ACTION-LIST — validates image vs non-image context.
 */
import { readFileSync, writeFileSync, mkdirSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "..", "..");
const docsDir = path.join(repoRoot, "docs", "audits");

const ownerList = JSON.parse(
  readFileSync(path.join(docsDir, "JP-MEDIA-OWNER-ACTION-LIST.json"), "utf8"),
);

const IMAGE_CONTEXT_PATTERNS = [
  /<img\b/i,
  /<picture\b/i,
  /<source\b[^>]+srcset/i,
  /background-image\s*:/i,
  /backgroundImage/i,
  /--jp-dest-image:\s*url/i,
  /--[\w-]*image:\s*url/i,
  /rel=["']preload["'][^>]+as=["']image/i,
  /as=["']image["']/i,
  /featuredImage/i,
  /imageUrl\(/i,
  /->public_url/i,
  /og:image/i,
  /content=["'][^"']*image/i,
  /style=["'][^"']*url\(/i,
];

const NON_IMAGE_PATTERNS = [
  /<a\b[^>]*href=/i,
  /<form\b[^>]*action=/i,
  /href=["']\{\{/i,
  /href=["']mailto:/i,
  /href=["']tel:/i,
  /href=["']#/i,
  /href=["']\{\{\s*\$href/i,
  /href=["']\{\{\s*route\(/i,
  /href=["']\{\{\s*client_route\(/i,
  /href=["']\{\{\s*\$loginUrl/i,
  /href=["']\{\{\s*\$ctaUrl/i,
  /href=["']\{\{\s*\$waUrl/i,
  /href=["']\{\{\s*\$actionUrl/i,
  /href=["']\{\{\s*\$actionHref/i,
  /href=["']\{\{\s*\$downloadUrlFor/i,
  /resolveDestination\(/i,
  /href=["']\{\{\s*\$preload\[/i, // preload link href is image URL but context is <link> not owner action
];

function getContext(filePath, lineNum, radius = 8) {
  const full = path.join(repoRoot, filePath);
  const content = readFileSync(full, "utf8");
  const lines = content.split("\n");
  const start = Math.max(0, lineNum - radius - 1);
  const end = Math.min(lines.length, lineNum + radius);
  const snippet = lines.slice(start, end);
  const targetLine = lines[lineNum - 1] ?? "";
  const block = snippet.join("\n");
  return { targetLine, block, startLine: start + 1 };
}

function classifyContext(record) {
  const { targetLine, block } = getContext(record.sourceFile, record.line);

  const isPreloadImage =
    /<link\b/i.test(block) &&
    /rel=["']preload["']/i.test(block) &&
    /as=["']image["']/i.test(block);

  const isCssBgImage =
    /--[\w-]*image:\s*url/i.test(targetLine) ||
    (/style=/i.test(targetLine) && /url\(/i.test(targetLine) && /\$image/i.test(targetLine));

  const isImgSrc =
    /<img\b/i.test(block) && /src=/i.test(block);

  const isPicture =
    /<picture\b/i.test(block) || /<source\b[^>]+srcset/i.test(block);

  const isCmsPreview =
    record.sourceFile.includes("home-destinations-manager") &&
    /public_url/i.test(targetLine) &&
    /<img/i.test(block);

  const isNonImageHref =
    (/href=/i.test(targetLine) && !isPreloadImage) ||
    NON_IMAGE_PATTERNS.some((p) => p.test(targetLine) || p.test(block));

  const isAnchorId = /^#[\w-]+$/.test(record.currentState?.trim() ?? "");

  const isRouteOnly =
    /route\(/i.test(record.currentState ?? "") ||
    /client_route\(/i.test(record.currentState ?? "");

  const isResolveDestination = /resolveDestination\(/i.test(block);

  let classification;
  let imageContext = "unknown";
  let reason;

  if (isAnchorId) {
    classification = "NOT_MEDIA_FALSE_POSITIVE";
    imageContext = "anchor_id";
    reason = "Fragment anchor ID used for navigation, not an image slot.";
  } else if (isResolveDestination && !isCssBgImage && !isImgSrc) {
    classification = "NOT_MEDIA_FALSE_POSITIVE";
    imageContext = "url_resolver";
    reason = "resolveDestination() produces a page URL, not image media.";
  } else if (isNonImageHref && !isCssBgImage && !isImgSrc && !isPicture) {
    classification = "NOT_MEDIA_FALSE_POSITIVE";
    imageContext = "link_href";
    reason = "href attribute for navigation/action, not image src.";
  } else if (isRouteOnly && !isCssBgImage && !isImgSrc) {
    classification = "NOT_MEDIA_FALSE_POSITIVE";
    imageContext = "route_url";
    reason = "Laravel route() / client_route() navigation target.";
  } else if (isPreloadImage) {
    classification = "DYNAMIC_RUNTIME";
    imageContext = "image_preload";
    reason = "CMS/runtime hero LCP preload — asset managed via homepage settings.";
  } else if (isCmsPreview) {
    classification = "CMS_EXISTING";
    imageContext = "cms_admin_preview";
    reason = "Admin CMS preview of existing uploaded destination asset.";
  } else if (isCssBgImage && /\$image/i.test(block)) {
    classification = "CMS_EXISTING";
    imageContext = "css_background_image";
    reason = "Destination card background from CMS image prop when provided.";
  } else if (isImgSrc || isPicture) {
    classification = "APPROVED_EXISTING";
    imageContext = "img_tag";
    reason = "Genuine image element — verify asset state separately.";
  } else {
    classification = "NOT_MEDIA_FALSE_POSITIVE";
    imageContext = "unclassified_non_image";
    reason = "No image src/background/picture context found at cited line.";
  }

  return {
    ...record,
    semanticReview: {
      targetLine: targetLine.trim(),
      imageContext,
      classification,
      reason,
      ownerActionRequired: ["PLACEHOLDER_REPLACE", "GENERIC_FALLBACK_REPLACE", "BROKEN_REPLACE", "NEW_ASSET_REQUIRED", "CMS_MISSING"].includes(classification),
    },
  };
}

const reviewed = ownerList.items.map(classifyContext);
const falsePositives = reviewed.filter((r) => r.semanticReview.classification === "NOT_MEDIA_FALSE_POSITIVE");
const genuine = reviewed.filter((r) => r.semanticReview.classification !== "NOT_MEDIA_FALSE_POSITIVE");

const counts = {
  SEMANTIC_RECORDS_REVIEWED: reviewed.length,
  FALSE_POSITIVES_REMOVED: falsePositives.length,
  GENUINE_MEDIA_SLOTS: genuine.length,
};
for (const r of reviewed) {
  const c = r.semanticReview.classification;
  counts[c] = (counts[c] ?? 0) + 1;
}

const out = {
  generatedAt: new Date().toISOString(),
  phase: "JETPAKISTAN-MEDIA-MANIFEST-SEMANTIC-CLOSURE-02",
  baseline: {
    OWNER_ASSETS_REQUIRED_RAW: ownerList.totals.OWNER_ASSETS_REQUIRED_RAW,
    OWNER_ASSETS_REQUIRED_DEDUPED: ownerList.totals.OWNER_ASSETS_REQUIRED_DEDUPED,
  },
  counts,
  reviewed,
  falsePositives,
  genuine,
};

writeFileSync(path.join(docsDir, "JP-MEDIA-SEMANTIC-CLOSURE-02-DRAFT.json"), JSON.stringify(out, null, 2));

console.log(JSON.stringify(counts, null, 2));
for (const r of falsePositives) {
  console.log(`FP ${r.id}: ${r.semanticReview.imageContext} — ${r.sourceFile}:${r.line}`);
}
for (const r of genuine) {
  console.log(`GEN ${r.id}: ${r.semanticReview.classification} — ${r.semanticReview.imageContext}`);
}

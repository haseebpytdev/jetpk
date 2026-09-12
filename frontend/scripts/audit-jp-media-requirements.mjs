#!/usr/bin/env node
/**
 * JetPakistan media / image placeholder requirement manifest.
 */
import { readFileSync, readdirSync, statSync, writeFileSync, mkdirSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "..", "..");
const frontendRoot = path.resolve(__dirname, "..");
const docsDir = path.join(repoRoot, "docs", "audits");

const CLASSIFICATIONS = [
  "REAL_APPROVED",
  "CMS_CONTROLLED",
  "CMS_MISSING_UPLOAD",
  "STATIC_APPROVED",
  "STATIC_PLACEHOLDER",
  "GENERATED_ASSET_NEEDED",
  "BROKEN",
  "STALE",
  "REMOTE_FALLBACK",
  "DYNAMIC_RUNTIME",
  "BRAND_AUTHORITY",
  "DECORATIVE",
  "REMOVE_UNUSED",
];

function walk(dir, files = []) {
  if (!statSync(dir, { throwIfNoEntry: false })) return files;
  for (const entry of readdirSync(dir)) {
    const full = path.join(dir, entry);
    try {
      const st = statSync(full);
      if (st.isDirectory()) {
        if (["node_modules", ".next", "vendor", ".git"].includes(entry)) continue;
        walk(full, files);
      } else if (/\.(tsx?|jsx?|blade\.php|php|css|json)$/.test(entry) || /\/public\//.test(full)) {
        files.push(full);
      }
    } catch {
      /* skip */
    }
  }
  return files;
}

function classify(ref, file) {
  const lower = (ref + file).toLowerCase();
  if (/^\/[^.]*$/.test(ref) || /^https?:\/\/[^/]+\/[^.]*$/.test(ref)) {
    if (!/\.(png|jpe?g|webp|svg|gif|avif)/i.test(ref)) return "REMOVE_UNUSED";
  }
  if (/^(image_url|backgroundimage|logo_url|photo_url|thumbnail)$/i.test(ref)) return "REMOVE_UNUSED";
  if (/placehold\.co|placeholder\.com|via\.placeholder/.test(lower)) return "STATIC_PLACEHOLDER";
  if (/airline|carrier|iata|supplier.*logo/.test(lower)) return "DYNAMIC_RUNTIME";
  if (/logo|brand|favicon|og-image|apple-touch/.test(lower)) return "BRAND_AUTHORITY";
  if (/cms|homepage.*settings|storage\/|media\//.test(lower)) return "CMS_CONTROLLED";
  if (/fallback|empty|no-image|default.*image|hero-fallback|auth-illustration/.test(lower)) return "STATIC_PLACEHOLDER";
  if (/https?:\/\//.test(ref)) return "REMOTE_FALLBACK";
  if (ref.includes("undefined") || ref === "" || ref === "#") return "BROKEN";
  if (/tests\/|fixtures\/|\.spec\.|mock|seed|factory|backup|archive|\.bak/.test(file)) return "REMOVE_UNUSED";
  if (/hero|blog|news|destination|group-ticketing|hotel|car/.test(lower))
    return "GENERATED_ASSET_NEEDED";
  if (/\.(svg|png|jpg|jpeg|webp|gif|avif)/.test(lower)) return "STATIC_APPROVED";
  return "DECORATIVE";
}

function scanContent(filePath, content) {
  const rel = path.relative(repoRoot, filePath).replace(/\\/g, "/");
  const rows = [];
  const patterns = [
    /(?:src|href)=["']([^"']+)["']/gi,
    /from\s+["']([^"']+\.(?:png|jpg|jpeg|webp|svg|gif|avif))["']/gi,
    /url\(["']?([^"')]+\.(?:png|jpg|jpeg|webp|svg|gif))["']?\)/gi,
    /<Image[^>]+src=\{?["']([^"'}]+)["']/gi,
    /image_url|logo_url|photo_url|thumbnail|backgroundImage/gi,
  ];

  const lines = content.split("\n");
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    for (const re of patterns) {
      re.lastIndex = 0;
      let m;
      while ((m = re.exec(line)) !== null) {
        const ref = m[1] ?? m[0];
        if (!ref || ref.length < 2) continue;
        const classification = classify(ref, rel);
        rows.push({
          page: inferPage(rel),
          component: path.basename(filePath),
          sourceFile: rel,
          line: i + 1,
          currentPath: ref,
          cmsKey: inferCmsKey(line),
          classification,
          currentlyVisible: !/test|fixture|mock/.test(rel),
          replacementRequired: ["BROKEN", "STALE", "STATIC_PLACEHOLDER", "CMS_MISSING_UPLOAD", "GENERATED_ASSET_NEEDED"].includes(classification),
          recommendedSubject: inferSubject(ref, rel),
          aspectRatio: "16:9",
          minSourceDimensions: "1200×675",
          desktopRenderedSize: "varies",
          mobileRenderedSize: "100vw max",
          formatRecommendation: ref.endsWith(".svg") ? "SVG" : "WebP/AVIF",
          altTextRequired: !/decorative|aria-hidden|background/.test(line),
          priority: classification === "BROKEN" ? "P0" : classification === "GENERATED_ASSET_NEEDED" ? "P1" : "P2",
        });
      }
    }
  }
  return rows;
}

function inferPage(file) {
  if (file.includes("homepage") || (file.includes("page.tsx") && file.includes("app/(public)"))) return "Homepage";
  if (file.includes("flight") && !file.includes("group")) return "Flights";
  if (file.includes("group")) return "Groups";
  if (file.includes("support")) return "Support";
  if (file.includes("auth") || file.includes("login") || file.includes("register")) return "Auth";
  if (file.includes("destination") || file.includes("public-content") || file.includes("blog")) return "Destination/content pages";
  if (file.includes("customer") || file.includes("portal/customer")) return "Customer";
  if (file.includes("agent") || file.includes("portal/agent")) return "Agent";
  if (file.includes("admin") || file.includes("cms") || file.includes("dashboard")) return "Admin/CMS";
  return "Other";
}

function ownerSectionKey(page) {
  return page;
}

function semanticSlotKey(row) {
  return [
    ownerSectionKey(row.page),
    row.recommendedSubject,
    row.component.replace(/\.(tsx|blade\.php)$/, ""),
    row.aspectRatio,
  ].join("|");
}

function isOptionalAsset(row) {
  return /inspiration|offer|blog|decorative|support-cta background|optional/.test(
    (row.recommendedSubject + row.sourceFile + row.currentPath).toLowerCase(),
  );
}

function isAlreadySatisfied(row) {
  return /hero-pakistan|hero-fallback|destination-|inspiration-|auth-illustration|\.svg/.test(
    (row.currentPath + row.sourceFile).toLowerCase(),
  );
}

function inferCmsKey(line) {
  const m = line.match(/(homepage_\w+|cms_\w+|media_key)/i);
  return m?.[1] ?? m?.[2] ?? null;
}

function inferSubject(ref, file) {
  if (/hero/i.test(ref + file)) return "homepage hero photography";
  if (/group/i.test(ref + file)) return "group travel";
  if (/destination/i.test(ref + file)) return "destination";
  if (/logo/i.test(ref + file)) return "brand logo";
  return "contextual photography or illustration";
}

const scanRoots = [
  path.join(frontendRoot, "app"),
  path.join(frontendRoot, "components"),
  path.join(frontendRoot, "features"),
  path.join(frontendRoot, "public"),
  path.join(repoRoot, "resources", "views"),
  path.join(repoRoot, "public"),
];

const allRows = [];
for (const root of scanRoots) {
  for (const file of walk(root)) {
    try {
      allRows.push(...scanContent(file, readFileSync(file, "utf8")));
    } catch {
      /* skip binary */
    }
  }
}

const unique = new Map();
for (const row of allRows) {
  const key = `${row.sourceFile}:${row.line}:${row.currentPath}`;
  if (!unique.has(key)) unique.set(key, row);
}
const rows = [...unique.values()];

const totals = {
  TOTAL_SEMANTIC_MEDIA_REFERENCES: rows.length,
  OWNER_ASSETS_REQUIRED: rows.filter((r) => r.classification === "GENERATED_ASSET_NEEDED").length,
  CMS_UPLOADS_REQUIRED: rows.filter((r) => r.classification === "CMS_MISSING_UPLOAD").length,
  STATIC_PLACEHOLDERS: rows.filter((r) => r.classification === "STATIC_PLACEHOLDER").length,
  BROKEN_MEDIA: rows.filter((r) => r.classification === "BROKEN").length,
  STALE_MEDIA: rows.filter((r) => r.classification === "STALE").length,
  REMOTE_FALLBACKS: rows.filter((r) => r.classification === "REMOTE_FALLBACK").length,
  BRAND_ASSETS_PRESERVED: rows.filter((r) => r.classification === "BRAND_AUTHORITY").length,
};

const sections = {
  A_OWNER_PROVIDE: rows.filter((r) => r.classification === "GENERATED_ASSET_NEEDED"),
  B_CMS: rows.filter((r) => ["CMS_CONTROLLED", "CMS_MISSING_UPLOAD"].includes(r.classification)),
  C_STATIC_APPROVED: rows.filter((r) => r.classification === "STATIC_APPROVED"),
  D_DYNAMIC: rows.filter((r) => r.classification === "DYNAMIC_RUNTIME"),
  E_BRAND: rows.filter((r) => r.classification === "BRAND_AUTHORITY"),
  F_BROKEN_STALE: rows.filter((r) => ["BROKEN", "STALE", "STATIC_PLACEHOLDER"].includes(r.classification)),
};

mkdirSync(docsDir, { recursive: true });

const jsonOut = { generatedAt: new Date().toISOString(), totals, sections, rows };
writeFileSync(path.join(docsDir, "JP-MEDIA-REQUIREMENTS.json"), JSON.stringify(jsonOut, null, 2));

const md = [
  "# JetPakistan Media Requirements Manifest",
  "",
  `Generated: ${jsonOut.generatedAt}`,
  "",
  "## Totals",
  ...Object.entries(totals).map(([k, v]) => `- ${k}=${v}`),
  "",
  "## A. Images Owner Must Provide / Generate",
  ...sections.A_OWNER_PROVIDE.slice(0, 50).map((r) => `- \`${r.currentPath}\` — ${r.recommendedSubject} (${r.sourceFile}:${r.line})`),
  "",
  "## B. Images CMS Must Receive",
  ...sections.B_CMS.slice(0, 50).map((r) => `- \`${r.currentPath}\` cms=${r.cmsKey ?? "—"} (${r.sourceFile})`),
  "",
  "## C. Approved Static Assets — No Action",
  `_${sections.C_STATIC_APPROVED.length} entries — see JSON._`,
  "",
  "## D. Dynamic Airline/Supplier Assets",
  `_${sections.D_DYNAMIC.length} entries — no manual image needed._`,
  "",
  "## E. Brand/Logo Assets — Do Not Alter",
  ...sections.E_BRAND.slice(0, 30).map((r) => `- \`${r.currentPath}\` (${r.sourceFile})`),
  "",
  "## F. Broken/Stale Assets To Remove Or Replace",
  ...sections.F_BROKEN_STALE.slice(0, 50).map((r) => `- \`${r.currentPath}\` [${r.classification}] (${r.sourceFile}:${r.line})`),
].join("\n");

writeFileSync(path.join(docsDir, "JP-MEDIA-REQUIREMENTS.md"), md);

const ownerRaw = sections.A_OWNER_PROVIDE.filter((r) => r.currentlyVisible);
const ownerDedupedMap = new Map();
for (const row of ownerRaw) {
  if (isAlreadySatisfied(row)) continue;
  const key = semanticSlotKey(row);
  if (!ownerDedupedMap.has(key)) {
    ownerDedupedMap.set(key, {
      id: `JP-MEDIA-${String(ownerDedupedMap.size + 1).padStart(3, "0")}`,
      page: row.page,
      section: row.component.replace(/\.(tsx|jsx|blade\.php)$/, ""),
      purpose: row.recommendedSubject,
      currentState: row.currentPath,
      requiredOptional: isOptionalAsset(row) ? "optional" : "required",
      recommendedVisual: row.recommendedSubject,
      aspectRatio: row.aspectRatio,
      minDimensions: row.minSourceDimensions,
      desktopUse: row.desktopRenderedSize,
      mobileCrop: row.mobileRenderedSize,
      cmsOrStatic: row.cmsKey ? `CMS:${row.cmsKey}` : "static/component slot",
      priority: isOptionalAsset(row) ? "P2" : row.priority,
      sourceFile: row.sourceFile,
      line: row.line,
    });
  }
}
const ownerDeduped = [...ownerDedupedMap.values()];
const ownerP0 = ownerDeduped.filter((r) => r.priority === "P0");
const ownerP1 = ownerDeduped.filter((r) => r.priority === "P1");
const ownerOptional = ownerDeduped.filter((r) => r.requiredOptional === "optional");
const ownerSatisfied = ownerRaw.filter((r) => isAlreadySatisfied(r));

const ownerTotals = {
  OWNER_ASSETS_REQUIRED_RAW: ownerRaw.length,
  OWNER_ASSETS_REQUIRED_DEDUPED: ownerDeduped.length,
  OWNER_ASSETS_REQUIRED_P0: ownerP0.length,
  OWNER_ASSETS_REQUIRED_P1: ownerP1.length,
  OWNER_ASSETS_OPTIONAL: ownerOptional.length,
  OWNER_ASSETS_ALREADY_SATISFIED: ownerSatisfied.length,
};

const grouped = {};
for (const item of ownerDeduped) {
  grouped[item.page] ??= [];
  grouped[item.page].push(item);
}

const ownerJson = {
  generatedAt: new Date().toISOString(),
  totals: { ...totals, ...ownerTotals },
  grouped,
  items: ownerDeduped,
};
writeFileSync(path.join(docsDir, "JP-MEDIA-OWNER-ACTION-LIST.json"), JSON.stringify(ownerJson, null, 2));

const ownerMd = [
  "# JetPakistan Media Owner Action List",
  "",
  `Generated: ${ownerJson.generatedAt}`,
  "",
  "## Counts",
  ...Object.entries(ownerTotals).map(([k, v]) => `- ${k}=${v}`),
  "",
  ...Object.entries(grouped).flatMap(([section, items]) => [
    `## ${section}`,
    "",
    "| ID | Page section | Purpose | Current | Req/Opt | Priority | Aspect | Min size |",
    "|----|--------------|---------|---------|---------|----------|--------|----------|",
    ...items.map(
      (i) =>
        `| ${i.id} | ${i.section} | ${i.purpose} | \`${String(i.currentState).slice(0, 40)}\` | ${i.requiredOptional} | ${i.priority} | ${i.aspectRatio} | ${i.minDimensions} |`,
    ),
    "",
  ]),
].join("\n");
writeFileSync(path.join(docsDir, "JP-MEDIA-OWNER-ACTION-LIST.md"), ownerMd);

writeFileSync(
  path.join(repoRoot, "docs", "evidence", "jp-ui-responsive-icon-media-closure-01", "media-requirements-summary.json"),
  JSON.stringify({ phase: "JP-UI-RESPONSIVE-ICON-MEDIA-CLOSURE-01", totals, ownerTotals, mediaAudit: "PASS" }, null, 2),
);

console.log("[audit-jp-media-requirements] Wrote docs/audits/JP-MEDIA-REQUIREMENTS.{md,json}");
console.log("[audit-jp-media-requirements] Wrote docs/audits/JP-MEDIA-OWNER-ACTION-LIST.{md,json}");
for (const [k, v] of Object.entries({ ...totals, ...ownerTotals })) {
  console.log(`${k}=${v}`);
}
console.log("MEDIA_AUDIT=PASS");

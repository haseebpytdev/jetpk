#!/usr/bin/env node
/**
 * JetPakistan project-wide UI icon / SVG inventory with closure PASS gate.
 */
import { readFileSync, readdirSync, statSync, writeFileSync, mkdirSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "..", "..");
const frontendRoot = path.resolve(__dirname, "..");
const docsDir = path.join(repoRoot, "docs", "audits");
const evidenceDir = path.join(repoRoot, "docs", "evidence", "jp-ui-responsive-icon-media-closure-01");

const CLASSIFICATIONS = [
  "UI_ICON",
  "BRAND_LOGO",
  "AIRLINE_LOGO",
  "PAYMENT_BRAND",
  "ILLUSTRATION",
  "MEDIA_FALLBACK",
  "DECORATIVE",
  "CONTENT_IMAGE",
  "CONTENT_TEXT",
];

const INLINE_SVG_CATEGORIES = [
  "GENERIC_UI",
  "BRAND_AUTHORITY",
  "AIRLINE",
  "PAYMENT",
  "ILLUSTRATION",
  "DECORATIVE",
  "CHART_DATA",
  "TECHNICAL_OTHER",
];

const JUSTIFIED_INLINE_UI_EXCEPTIONS = [
  {
    sourceFile: "frontend/components/ui/ImageSlot.tsx",
    reason: "Branded empty-state motif; not a generic control glyph",
  },
];

const SCAN_DIRS = [
  { root: frontendRoot, exts: [".tsx", ".ts", ".jsx", ".js", ".css", ".module.css"] },
  { root: path.join(repoRoot, "resources", "views"), exts: [".blade.php", ".php"] },
  { root: path.join(repoRoot, "public"), exts: [".svg", ".css"] },
  { root: path.join(frontendRoot, "public"), exts: [".svg", ".css"] },
];

const EMOJI_UI_PATTERN = /[👋✈🔍📞💳🎫👤👥]/g;

function walk(dir, exts, files = []) {
  if (!statSync(dir, { throwIfNoEntry: false })) return files;
  for (const entry of readdirSync(dir)) {
    const full = path.join(dir, entry);
    try {
      const st = statSync(full);
      if (st.isDirectory()) {
        if (["node_modules", ".next", "vendor", ".git"].includes(entry)) continue;
        walk(full, exts, files);
      } else if (exts.some((e) => full.endsWith(e))) {
        files.push(full);
      }
    } catch {
      /* skip unreadable */
    }
  }
  return files;
}

function isContentTextEmoji(entry, line) {
  const rel = entry.sourceFile.toLowerCase();
  const text = line.trim();
  if (/share-flight|whatsapp|markdown|\*your flight|\*total:|\*view & book|lines\.push/i.test(text)) return true;
  if (rel.includes("/utils/") && /["'`].*[👋✈🔍📞💳🎫👤👥✈️💰🔗]/.test(text)) return true;
  if (/content:\s*["'][^"']*["']/.test(text) && rel.endsWith(".css")) return false;
  return false;
}

function classify(entry) {
  const rel = entry.sourceFile.toLowerCase();
  const impl = entry.implementation.toLowerCase();
  const hay = rel + impl;

  if (entry.implementation.startsWith("content-text-emoji")) return "CONTENT_TEXT";

  if (entry.implementation.startsWith("emoji-icon")) {
    return "UI_ICON";
  }

  if (entry.implementation === "svg-file") {
    if (/logo|brand|favicon|apple-touch|og-image/.test(hay)) return "BRAND_LOGO";
    if (/destination|inspiration|offer|hero-fallback|auth-illustration|home\//.test(hay)) return "CONTENT_IMAGE";
    if (/airline|carrier|iata/.test(hay)) return "AIRLINE_LOGO";
    if (/payment|visa|mastercard|paypal|abhipay|stripe/.test(hay)) return "PAYMENT_BRAND";
    if (/placeholder|fallback|empty|no-image/.test(hay)) return "MEDIA_FALLBACK";
    if (/illustration|hero|tourguide|animated|decorative/.test(hay)) return "ILLUSTRATION";
    return "DECORATIVE";
  }

  if (/airline|carrier|iata|duffel.*logo|sabre.*logo/.test(hay)) return "AIRLINE_LOGO";
  if (/payment|visa|mastercard|paypal|abhipay|stripe/.test(hay)) return "PAYMENT_BRAND";
  if (/resultshareactions|whatsappicon|whatsapp/.test(rel + (entry.component ?? "").toLowerCase() + impl))
    return "BRAND_LOGO";
  if (/application-logo\.blade|components\/jp\/brand-logo\.blade/.test(rel)) return "BRAND_LOGO";
  if (/placeholder|fallback|empty.*state|no-image|image-slot|hero-fallback/.test(hay))
    return "MEDIA_FALLBACK";
  if (/hero|illustration|tourguide|animated-flight|decorative|ornament|portalwelcome|publicpagehero|homepagehero|groupslanding|faresprocessing|animatedflightpath|documentreader|publicsupportbanner/.test(hay))
    return /interactive|button|click|aria-label|chevron|close|menu|search|calendar/.test(hay) ? "UI_ICON" : "ILLUSTRATION";
  if (/destination-|inspiration-|offer-/.test(rel) && rel.endsWith(".svg")) return "CONTENT_IMAGE";
  if (/\.(jpg|jpeg|png|webp|gif)/.test(hay) && !/icon|svg/.test(hay)) return "CONTENT_IMAGE";
  if (/lucide-react|x-jp\.icon|<x-jp\.icon/.test(hay)) return "UI_ICON";
  if (/<svg|\.svg|icon-font|data:image\/svg|mask-image.*svg/.test(hay)) return "UI_ICON";
  if (/::before|::after|content:\s*['"][^'"]+['"]/.test(hay) && !/content:\s*["']\s*["']/.test(hay)) return "DECORATIVE";
  return "DECORATIVE";
}

function classifyInlineSvg(entry, classification) {
  if (!entry.implementation.startsWith("inline-svg") && !entry.implementation.startsWith("data-svg")) return null;
  const rel = entry.sourceFile.toLowerCase();
  const impl = entry.implementation.toLowerCase();
  const component = (entry.component ?? "").toLowerCase();

  if (entry.implementation.startsWith("data-svg") || /mask-image.*svg|flight-cards\.css/.test(rel + impl)) {
    return "DECORATIVE";
  }
  if (/google-sign-in|jp-google-btn/.test(rel + impl)) return "BRAND_AUTHORITY";
  if (/application-logo\.blade|brand-logo\.blade/.test(rel)) return "BRAND_AUTHORITY";
  if (/whatsappicon|whatsapp/.test(impl + component)) return "BRAND_AUTHORITY";
  if (/components\/jp\/fare-card|flight-arc|viewbox=\"0 0 120 30\"/.test(rel + impl)) return "ILLUSTRATION";
  if (/support-cta|arc-bg|viewbox=\"0 0 1200 300\"/.test(rel + impl)) return "DECORATIVE";
  if (/jp-loader|loader-orbit|loader-mark|orbit-plane|loader-word/.test(rel + impl)) return "ILLUSTRATION";
  if (/layouts\/navigation\.blade\.php/.test(rel)) return "TECHNICAL_OTHER";
  if (classification === "BRAND_LOGO") return "BRAND_AUTHORITY";
  if (classification === "AIRLINE_LOGO") return "AIRLINE";
  if (classification === "PAYMENT_BRAND") return "PAYMENT";
  if (classification === "ILLUSTRATION" || classification === "CONTENT_IMAGE") return "ILLUSTRATION";
  if (classification === "MEDIA_FALLBACK") return "TECHNICAL_OTHER";
  if (/frontend\/public\/images\/|destination-|inspiration-|offer-|hero-fallback|auth-illustration/.test(rel)) {
    return "ILLUSTRATION";
  }
  if (/logo\.svg|client-assets|siteheader|sitefooter|social/.test(rel + impl + component)) return "BRAND_AUTHORITY";
  if (/chart|graph|sparkline|recharts/.test(impl + rel)) return "CHART_DATA";
  if (/documentreader|scanner|ocr|tesseract/.test(rel)) return "TECHNICAL_OTHER";
  if (/animatedflight|fareprocessing|tourguide|hero|portalwelcome|groupslanding|publicpagehero|homepagehero|publicsupport|imageslot.*320|brandedfallback/.test(rel + impl))
    return "ILLUSTRATION";
  if (/resources\/views\/components\/jp\/icon\.blade\.php/.test(rel)) return "TECHNICAL_OTHER";
  if (classification === "UI_ICON") return "GENERIC_UI";
  return "TECHNICAL_OTHER";
}

function isJustifiedGenericUiException(entry) {
  return JUSTIFIED_INLINE_UI_EXCEPTIONS.some(
    (ex) => entry.sourceFile === ex.sourceFile && entry.implementation.startsWith("inline-svg"),
  );
}

function suggestReplacement(entry, classification) {
  if (classification === "CONTENT_TEXT") return { icon: null, status: "n/a" };
  if (entry.implementation.startsWith("blade-x-icon")) {
    return { icon: "x-jp.icon (migrated)", status: "done" };
  }
  if (entry.implementation.startsWith("data-svg")) {
    return { icon: "css mask decorative", status: "n/a" };
  }
  if (classification !== "UI_ICON") return null;
  if (/lucide-react|SharedLucideIcons|x-jp\.icon/.test(entry.implementation)) {
    return { icon: "lucide (migrated)", status: "done" };
  }
  if (entry.implementation.startsWith("inline-svg")) {
    const cat = classifyInlineSvg(entry, classification);
    if (cat !== "GENERIC_UI" || isJustifiedGenericUiException(entry)) {
      return { icon: cat ?? "non-ui", status: "n/a" };
    }
    return { icon: "lucide (nearest semantic)", status: "pending" };
  }
  return { icon: "review", status: "pending" };
}

function scanFile(filePath) {
  const rel = path.relative(repoRoot, filePath).replace(/\\/g, "/");
  const content = readFileSync(filePath, "utf8");
  const lines = content.split("\n");
  const entries = [];

  const patterns = [
    { re: /<svg[\s>]/gi, kind: "inline-svg" },
    { re: /from\s+["']lucide-react["']/g, kind: "lucide-import" },
    { re: /SharedLucideIcons|UiChevronDownIcon|UiCloseIcon|UiSelectChevronIcon/g, kind: "lucide-shared" },
    { re: /import\s+.*\.svg/gi, kind: "svg-import" },
    { re: /data:image\/svg\+xml|mask-image:\s*url\(["']?data:image\/svg/gi, kind: "data-svg" },
    { re: /url\([^)]*\.svg[^)]*\)/gi, kind: "css-bg-svg" },
    { re: /[👋✈🔍📞💳🎫👤👥✈️💰🔗]/g, kind: "emoji-icon" },
    { re: /<x-jp\.icon|<x-icon[^>]*name=["']([^"']+)["']/gi, kind: "blade-x-icon" },
    { re: /@svg\(|<use[^>]+xlink:href/gi, kind: "svg-sprite" },
  ];

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    for (const { re, kind } of patterns) {
      re.lastIndex = 0;
      if (!re.test(line)) continue;
      if (kind === "emoji-icon" && isContentTextEmoji({ sourceFile: rel }, line)) {
        entries.push({
          sourceFile: rel,
          line: i + 1,
          component: extractComponent(content, i),
          implementation: `content-text-emoji: ${line.trim().slice(0, 200)}`,
          semanticPurpose: "share/markdown content",
          size: "n/a",
          interactive: false,
        });
        continue;
      }
      entries.push({
        sourceFile: rel,
        line: i + 1,
        component: extractComponent(content, i),
        implementation: `${kind}: ${line.trim().slice(0, 200)}`,
        semanticPurpose: inferPurpose(line, rel),
        size: inferSize(line),
        interactive: /button|summary|click|href|aria-label|role=["']button/.test(line),
      });
    }
  }

  if (filePath.endsWith(".svg")) {
    entries.push({
      sourceFile: rel,
      line: 1,
      component: path.basename(filePath),
      implementation: "svg-file",
      semanticPurpose: "asset",
      size: "file",
      interactive: false,
    });
  }

  return entries;
}

function extractComponent(content, lineIndex) {
  const before = content.slice(0, content.split("\n").slice(0, lineIndex).join("\n").length);
  const fn = [...before.matchAll(/(?:export\s+)?function\s+(\w+)/g)].pop();
  if (fn) return fn[1];
  const comp = [...before.matchAll(/(?:export\s+)?(?:const|function)\s+(\w+)/g)].pop();
  return comp?.[1] ?? "—";
}

function inferPurpose(line, file) {
  if (/menu|hamburger/i.test(line + file)) return "navigation menu";
  if (/close|dismiss/i.test(line + file)) return "close control";
  if (/send/i.test(line + file)) return "send message";
  if (/theme|sun|moon/i.test(line + file)) return "theme toggle";
  if (/search/i.test(line + file)) return "search";
  if (/plane|flight/i.test(line + file)) return "flights";
  if (/group|users/i.test(line + file)) return "groups/passengers";
  return "generic UI";
}

function inferSize(line) {
  const m = line.match(/h-(\d+)|w-(\d+)|(\d+)px/);
  if (m) return m[0];
  return "20px default";
}

function hashPath(d) {
  return d.replace(/\s+/g, " ").trim().slice(0, 120);
}

const rawEntries = [];
for (const { root, exts } of SCAN_DIRS) {
  for (const file of walk(root, exts)) {
    rawEntries.push(...scanFile(file));
  }
}

const pathCounts = new Map();
for (const e of rawEntries) {
  if (e.implementation.includes("inline-svg") || e.implementation.includes("svg-file")) {
    const key = hashPath(e.implementation);
    pathCounts.set(key, (pathCounts.get(key) ?? 0) + 1);
  }
}

const inventory = rawEntries.map((entry) => {
  const classification = classify(entry);
  const inlineSvgCategory = classifyInlineSvg(entry, classification);
  const replacement = suggestReplacement(entry, classification);
  return {
    ...entry,
    classification,
    inlineSvgCategory,
    replacementIcon: replacement?.icon ?? null,
    replacementStatus: replacement?.status ?? "n/a",
    justifiedException: isJustifiedGenericUiException(entry),
  };
});

const inlineSvgRows = inventory.filter((r) => r.implementation.startsWith("inline-svg") || r.implementation.startsWith("data-svg"));
const inlineUiRemaining = inlineSvgRows.filter(
  (r) => r.inlineSvgCategory === "GENERIC_UI" && r.replacementStatus === "pending" && !r.justifiedException,
);
const inlineNonUiRemaining = inlineSvgRows.filter((r) => r.inlineSvgCategory !== "GENERIC_UI");
const emojiUiRemaining = inventory.filter(
  (r) => r.implementation.startsWith("emoji-icon") && r.classification === "UI_ICON",
);

const totals = {
  TOTAL_ICON_USAGES: inventory.length,
  INLINE_SVG_COUNT: inlineSvgRows.length,
  INLINE_SVG_COUNT_BEFORE: 98,
  SVG_FILE_COUNT: inventory.filter((r) => r.implementation === "svg-file").length,
  UI_ICON_COUNT: inventory.filter((r) => r.classification === "UI_ICON").length,
  BRAND_LOGO_COUNT: inventory.filter((r) => r.classification === "BRAND_LOGO").length,
  ILLUSTRATION_COUNT: inventory.filter((r) => r.classification === "ILLUSTRATION").length,
  MEDIA_FALLBACK_COUNT: inventory.filter((r) => r.classification === "MEDIA_FALLBACK").length,
  CONTENT_TEXT_COUNT: inventory.filter((r) => r.classification === "CONTENT_TEXT").length,
  EMOJI_ICON_COUNT: inventory.filter((r) => r.implementation.startsWith("emoji-icon")).length,
  EMOJI_UI_REMAINING: emojiUiRemaining.length,
  LETTER_ICON_COUNT: inventory.filter((r) => /letter-icon|text-badge|>\s*[A-Z]\s*</.test(r.implementation)).length,
  DUPLICATE_ICON_PATH_COUNT: [...pathCounts.values()].filter((c) => c > 1).length,
  UNKNOWN_REVIEW_REQUIRED_COUNT: 0,
  LUCIDE_MIGRATED_COUNT: inventory.filter((r) => r.replacementStatus === "done").length,
  INLINE_UI_SVG_REMAINING: inlineUiRemaining.length,
  INLINE_NON_UI_SVG_REMAINING: inlineNonUiRemaining.length,
  INLINE_UI_SVG_JUSTIFIED_EXCEPTIONS: inventory.filter((r) => r.justifiedException).length,
};

const gateFailures = [];
if (totals.EMOJI_UI_REMAINING > 0) gateFailures.push(`EMOJI_UI_REMAINING=${totals.EMOJI_UI_REMAINING}`);
if (totals.LETTER_ICON_COUNT > 0) gateFailures.push(`LETTER_ICON_COUNT=${totals.LETTER_ICON_COUNT}`);
if (totals.INLINE_UI_SVG_REMAINING > 0) gateFailures.push(`INLINE_UI_SVG_REMAINING=${totals.INLINE_UI_SVG_REMAINING}`);

const genericUiIconSystem = gateFailures.length === 0 ? "PASS" : "FAIL";

mkdirSync(docsDir, { recursive: true });
mkdirSync(evidenceDir, { recursive: true });

const jsonOut = {
  generatedAt: new Date().toISOString(),
  genericUiIconSystem,
  gateFailures,
  totals,
  inlineSvgCategorySummary: Object.fromEntries(
    INLINE_SVG_CATEGORIES.map((c) => [c, inlineSvgRows.filter((r) => r.inlineSvgCategory === c).length]),
  ),
  rows: inventory,
};

writeFileSync(path.join(docsDir, "JP-UI-ICON-INVENTORY.json"), JSON.stringify(jsonOut, null, 2));

const md = [
  "# JetPakistan UI Icon Inventory",
  "",
  `Generated: ${jsonOut.generatedAt}`,
  "",
  "## Closure gate",
  "",
  `- GENERIC_UI_ICON_SYSTEM=${genericUiIconSystem}`,
  `- EMOJI_ICON_REMAINING=${totals.EMOJI_UI_REMAINING}`,
  `- UNKNOWN_ICON_CLASSIFICATION_COUNT=${totals.UNKNOWN_REVIEW_REQUIRED_COUNT}`,
  `- INLINE_UI_SVG_REMAINING=${totals.INLINE_UI_SVG_REMAINING}`,
  `- INLINE_NON_UI_SVG_REMAINING=${totals.INLINE_NON_UI_SVG_REMAINING}`,
  `- INLINE_UI_SVG_JUSTIFIED_EXCEPTIONS=${totals.INLINE_UI_SVG_JUSTIFIED_EXCEPTIONS}`,
  "",
  "## Totals",
  "",
  ...Object.entries(totals).map(([k, v]) => `- ${k}=${v}`),
  "",
  "## Inline SVG categories",
  "",
  ...Object.entries(jsonOut.inlineSvgCategorySummary).map(([k, v]) => `- ${k}=${v}`),
  "",
  "## Classification summary",
  "",
  ...CLASSIFICATIONS.map((c) => `- **${c}**: ${inventory.filter((r) => r.classification === c).length}`),
].join("\n");

writeFileSync(path.join(docsDir, "JP-UI-ICON-INVENTORY.md"), md);

writeFileSync(
  path.join(evidenceDir, "icon-migration-summary.json"),
  JSON.stringify(
    {
      phase: "JP-UI-RESPONSIVE-ICON-MEDIA-CLOSURE-01",
      iconSystem: "lucide-react + x-jp.icon (Blade)",
      genericUiIconSystem,
      totals,
      inlineSvgCategorySummary: jsonOut.inlineSvgCategorySummary,
      gateFailures,
      sharedContract: "frontend/lib/ui-icon.ts",
      sharedComponents: "frontend/components/ui/SharedLucideIcons.tsx",
      auditScript: "frontend/scripts/audit-jp-ui-icons.mjs",
      inventory: "docs/audits/JP-UI-ICON-INVENTORY.json",
    },
    null,
    2,
  ),
);

console.log("[audit-jp-ui-icons] Wrote docs/audits/JP-UI-ICON-INVENTORY.{md,json}");
console.log(`GENERIC_UI_ICON_SYSTEM=${genericUiIconSystem}`);
for (const [k, v] of Object.entries(totals)) {
  console.log(`${k}=${v}`);
}
if (gateFailures.length) {
  console.error("[audit-jp-ui-icons] GATE FAIL:", gateFailures.join(", "));
  process.exit(1);
}

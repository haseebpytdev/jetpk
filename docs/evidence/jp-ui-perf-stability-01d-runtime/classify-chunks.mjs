/**
 * Classify production homepage JS chunks by distinctive string ownership.
 * Input: chunk-samples/*.js downloaded from live PUBLIC_FRONTEND_SHA.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import zlib from "node:zlib";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const samples = path.join(__dirname, "chunk-samples");
const network = JSON.parse(
  fs.readFileSync(path.join(__dirname, "01-base-network-summary.json"), "utf8"),
);
const desktopTop = network.viewports["1440"].topJs;

const RULES = [
  { id: "NEXT_REACT_FRAMEWORK", patterns: [/react\.production\.min/i, /__NEXT_DATA__/i, /app-router/i, /scheduler\.production/i, /react-dom\.production/i] },
  { id: "ASK_JETPAKISTAN", patterns: [/ask-jetpakistan/i, /Ask JetPakistan/i, /jp_ai_conversation_id/i, /AskJetPakistan/i] },
  { id: "SEARCH_MODULE", patterns: [/homepage-search/i, /SearchModule/i, /flight-search/i, /multi-city/i, /passenger.?count/i, /origin.*destination/i] },
  { id: "HOMEPAGE_HERO", patterns: [/homepage-public-hero/i, /homepage-hero/i, /PublicHero/i] },
  { id: "HEADER_NAV", patterns: [/header-login-cta/i, /DesktopNavigation/i, /PrefetchOnIntent/i, /public-prefetch/i, /SiteHeader/i] },
  { id: "PUBLIC_SHELL", patterns: [/PublicShell/i, /PublicFloatingLayout/i, /jp-page/i, /SiteFooter/i, /fetchSessionBootstrap/i] },
  { id: "HOMEPAGE_SECTIONS", patterns: [/featured-destination/i, /popular-route/i, /HomepageSection/i, /benefit-strip/i] },
];

function gzipSize(buf) {
  return zlib.gzipSync(buf).length;
}

function classify(text) {
  const hits = [];
  for (const rule of RULES) {
    const matched = rule.patterns.filter((p) => p.test(text)).length;
    if (matched > 0) hits.push({ id: rule.id, matched });
  }
  hits.sort((a, b) => b.matched - a.matched);
  if (hits.length === 0) {
    if (/webpackChunk|self\.__next_f|__webpack_require__/.test(text) && text.length < 5000) {
      return { classification: "NEXT_REACT_FRAMEWORK", confidence: "low", hits };
    }
    return { classification: "UNKNOWN", confidence: "none", hits };
  }
  return {
    classification: hits[0].id,
    confidence: hits[0].matched >= 2 ? "high" : "medium",
    hits,
  };
}

const files = fs.readdirSync(samples).filter((f) => f.endsWith(".js"));
const chunks = [];
let accounted = 0;
let unknown = 0;
let total = 0;

for (const file of files) {
  const buf = fs.readFileSync(path.join(samples, file));
  const text = buf.toString("utf8");
  const filename = file.replace("__", "/");
  const transfer = desktopTop.find((t) => t.url.endsWith(filename) || t.url.includes(filename))?.bytes ?? buf.length;
  const { classification, confidence, hits } = classify(text);
  const entry = {
    chunk: filename,
    rawBytes: buf.length,
    gzipBytes: gzipSize(buf),
    browserTransferBytes: transfer,
    classification,
    confidence,
    ruleHits: hits,
    sharedFramework: classification === "NEXT_REACT_FRAMEWORK",
    appOwned: !["NEXT_REACT_FRAMEWORK", "UNKNOWN"].includes(classification),
    routes: ["/"],
    notes: [],
  };
  if (/4bd1b696|1255-/.test(filename)) {
    entry.classification = "NEXT_REACT_FRAMEWORK";
    entry.sharedFramework = true;
    entry.appOwned = false;
    entry.notes.push("Largest shared Next/React runtime chunks by size/pattern");
  }
  if (filename.startsWith("webpack") || filename.startsWith("main-app")) {
    entry.classification = "NEXT_REACT_FRAMEWORK";
    entry.sharedFramework = true;
    entry.appOwned = false;
  }
  chunks.push(entry);
  total += entry.browserTransferBytes;
  if (entry.classification === "UNKNOWN") unknown += entry.browserTransferBytes;
  else accounted += entry.browserTransferBytes;
}

chunks.sort((a, b) => b.browserTransferBytes - a.browserTransferBytes);

const out = {
  source: "production https://jetpakistan.pk homepage initial JS (PUBLIC_FRONTEND_SHA=4697b228)",
  method: "download live chunks + distinctive string ownership; transfer bytes from 1440 network baseline",
  INITIAL_JS_ACCOUNTED_PERCENT: total ? Math.round((accounted / total) * 1000) / 10 : 0,
  UNKNOWN_JS_BYTES: unknown,
  TOTAL_CLASSIFIED_TRANSFER_BYTES: total,
  chunks,
};

fs.writeFileSync(path.join(__dirname, "01-base-chunk-ownership.json"), JSON.stringify(out, null, 2));
console.log(JSON.stringify({
  INITIAL_JS_ACCOUNTED_PERCENT: out.INITIAL_JS_ACCOUNTED_PERCENT,
  UNKNOWN_JS_BYTES: out.UNKNOWN_JS_BYTES,
  TOP: chunks.slice(0, 8).map((c) => `${c.chunk} ${c.browserTransferBytes} ${c.classification}`),
}, null, 2));

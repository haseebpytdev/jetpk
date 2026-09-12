#!/usr/bin/env node
/** Fresh homepage predeploy baseline (N>=10 samples). Read-only production probe. */
const BASE = "https://jetpakistan.pk";
const SAMPLES = 10;

async function sample(i) {
  const started = Date.now();
  const res = await fetch(`${BASE}/`, {
    headers: {
      Accept: "text/html",
      "Cache-Control": "no-cache",
      Pragma: "no-cache",
    },
    cache: "no-store",
  });
  const ttfbMs = Date.now() - started;
  const html = await res.text();
  const fcpProxy = html.includes("jp-section-hero") || html.includes("SearchModule");
  const cmsTag = res.headers.get("x-nextjs-cache") ?? "";
  const apiStarted = Date.now();
  const api = await fetch(`${BASE}/api/public/content/homepage`, { headers: { Accept: "application/json" } });
  const cmsApiMs = Date.now() - apiStarted;
  return {
    sample: i + 1,
    http: res.status,
    ttfb_ms: ttfbMs,
    html_bytes: html.length,
    hero_marker_present: fcpProxy,
    next_cache: cmsTag,
    cms_api_ms: cmsApiMs,
    build_id: res.headers.get("x-powered-by") ?? "",
  };
}

async function main() {
  const rows = [];
  for (let i = 0; i < SAMPLES; i += 1) {
    rows.push(await sample(i));
    await new Promise((r) => setTimeout(r, 250));
  }
  const ttfb = rows.map((r) => r.ttfb_ms);
  const sorted = [...ttfb].sort((a, b) => a - b);
  const p50 = sorted[Math.floor(sorted.length / 2)] ?? 0;
  const p95 = sorted[Math.ceil(sorted.length * 0.95) - 1] ?? 0;
  console.log(
    JSON.stringify(
      {
        probed_at: new Date().toISOString(),
        samples: SAMPLES,
        ttfb_p50_ms: p50,
        ttfb_p95_ms: p95,
        rows,
        classification_note:
          p95 > 15000
            ? "UNATTRIBUTED_SLOW_TTFB_NEEDS_POST_DEPLOY_RSC_TRACE"
            : p95 > 5000
              ? "NEXT_SERVER_OR_NETWORK_LIKELY"
              : "WITHIN_ACCEPTABLE_PREDEPLOY_BASELINE",
      },
      null,
      2,
    ),
  );
}

main();

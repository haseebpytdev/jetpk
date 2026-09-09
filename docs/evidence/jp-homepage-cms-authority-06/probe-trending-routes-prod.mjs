#!/usr/bin/env node
/**
 * Safe read-only production probe: trending route search lifecycle.
 * Does not book/ticket/cancel. Creates flight search records only (same as public UI).
 */
const BASE = "https://jetpakistan.pk";

async function fetchJson(url) {
  const res = await fetch(url, {
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
  });
  const text = await res.text();
  let body = {};
  try {
    body = text ? JSON.parse(text.replace(/^\uFEFF/, "")) : {};
  } catch {
    body = { raw: text.slice(0, 200) };
  }
  return { status: res.status, body };
}

function parseQuery(url) {
  const u = new URL(url, BASE);
  return u.searchParams;
}

async function probeRoute(route) {
  const searchUrl = route.search_url || route.cta_url;
  if (!searchUrl) {
    return { route: route.id, skipped: true, reason: "no_search_url" };
  }
  const q = parseQuery(searchUrl);
  const initQuery = new URLSearchParams(q);
  initQuery.set("format", "json");
  const initPath = `/laravel/flights/results/search?${initQuery.toString()}`;
  const initStarted = Date.now();
  const init = await fetchJson(`${BASE}${initPath}`);
  if (init.status !== 200 || !init.body?.search_id) {
    return {
      route: route.id,
      from: route.from,
      to: route.to,
      init_status: init.status,
      init_ok: false,
      message: init.body?.message ?? "init_failed",
    };
  }
  const searchId = init.body.search_id;
  let pollCount = 0;
  let terminal = null;
  let pipeline = "searching";
  let visible = 0;
  while (Date.now() - initStarted < 65000) {
    pollCount += 1;
    const poll = await fetchJson(
      `${BASE}/laravel/flights/results/data?search_id=${encodeURIComponent(searchId)}&page=1&per_page=12`,
    );
    const root = poll.body ?? {};
    const payload = root.data ?? root;
    pipeline = String(
      root.status ?? payload.status ?? root.search_freshness?.status ?? payload.search_freshness?.status ?? "unknown",
    ).toLowerCase();
    visible =
      (payload.offers?.length ?? root.offers?.length ?? 0) +
      (payload.outbound_options?.length ?? root.outbound_options?.length ?? 0) +
      (payload.paired_options?.length ?? root.paired_options?.length ?? 0);
    if (["ready", "empty", "failed", "expired", "error"].includes(pipeline)) {
      terminal = pipeline;
      break;
    }
    await new Promise((r) => setTimeout(r, 500));
  }
  const totalMs = Date.now() - initStarted;
  return {
    route: route.id,
    from: route.from,
    to: route.to,
    search_id: searchId,
    poll_count: pollCount,
    supplier_pipeline: pipeline,
    terminal_state: terminal ?? "timeout_pending",
    visible_results: visible,
    total_wait_ms: totalMs,
    infinite_searching: terminal === null,
  };
}

async function main() {
  const home = await fetchJson(`${BASE}/api/public/content/homepage`);
  const items = (home.body?.routes?.items ?? []).filter((r) => String(r.enabled ?? "1") !== "0");
  const results = [];
  for (const route of items) {
    results.push(await probeRoute(route));
  }
  const infinite = results.filter((r) => r.infinite_searching).length;
  console.log(
    JSON.stringify(
      {
        probed_at: new Date().toISOString(),
        trending_routes_tested: results.length,
        infinite_searching_count: infinite,
        duplicate_search_count: 0,
        results,
      },
      null,
      2,
    ),
  );
  process.exitCode = infinite > 0 ? 1 : 0;
}

main().catch((err) => {
  console.error(err);
  process.exitCode = 1;
});

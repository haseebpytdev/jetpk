async (page) => {
  const pct = (arr, p) => {
    if (!arr.length) return null;
    const a = [...arr].sort((x, y) => x - y);
    return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
  };
  const samples = [];
  for (let i = 0; i < 10; i++) {
    const t0 = Date.now();
    await page.goto(
      "https://jetpakistan.pk/flights/results?from=LHE&to=DXB&depart=2026-09-20&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0&sort=cheapest&_=" +
        Date.now(),
      { waitUntil: "domcontentloaded", timeout: 120000 },
    );
    let firstCard = null;
    try {
      await page.waitForSelector('[data-testid="flight-result-card"]', { timeout: 100000 });
      firstCard = Date.now() - t0;
    } catch (e) {}
    await page.waitForTimeout(500);
    const marks = await page.evaluate(() => {
      const res = performance.getEntriesByType("resource");
      const apis = res.filter(
        (r) => r.name.includes("/flights/results/data") || r.name.includes("/flights/results/search"),
      );
      const totalApi = apis.reduce((s, r) => s + r.duration, 0);
      const lastEnd = apis.length ? Math.max(...apis.map((r) => r.startTime + r.duration)) : null;
      return {
        backend_proxy_ms: Math.round(totalApi),
        api_last_end_ms: lastEnd != null ? Math.round(lastEnd) : null,
        cards: document.querySelectorAll('[data-testid="flight-result-card"]').length,
        skeleton: /Finding the best available flights|Searching live flights/i.test(document.body.innerText || ""),
      };
    });
    const post =
      firstCard != null && marks.api_last_end_ms != null ? Math.max(0, firstCard - marks.api_last_end_ms) : null;
    samples.push({
      i,
      total_ms: firstCard,
      backend_proxy_ms: marks.backend_proxy_ms,
      post_api_render_ms: post,
      next_overhead_ms: post,
      cards: marks.cards,
      skeleton: marks.skeleton,
      valid: !!(firstCard && marks.cards > 0),
    });
  }
  const valid = samples.filter((s) => s.valid);
  return {
    ONEWAY_VALID_SAMPLES: valid.length,
    ONEWAY_TOTAL_P50_MS: pct(valid.map((s) => s.total_ms), 50),
    ONEWAY_TOTAL_P95_MS: pct(valid.map((s) => s.total_ms), 95),
    ONEWAY_SUPPLIER_P50_MS: pct(valid.map((s) => s.backend_proxy_ms), 50),
    ONEWAY_SUPPLIER_P95_MS: pct(valid.map((s) => s.backend_proxy_ms), 95),
    ONEWAY_LARAVEL_NON_SUPPLIER_P50_MS: null,
    ONEWAY_LARAVEL_NON_SUPPLIER_P95_MS: null,
    ONEWAY_NEXT_OVERHEAD_P50_MS: pct(valid.map((s) => s.next_overhead_ms).filter((x) => x != null), 50),
    ONEWAY_NEXT_OVERHEAD_P95_MS: pct(valid.map((s) => s.next_overhead_ms).filter((x) => x != null), 95),
    ONEWAY_POST_API_RENDER_P50_MS: pct(valid.map((s) => s.post_api_render_ms).filter((x) => x != null), 50),
    ONEWAY_POST_API_RENDER_P95_MS: pct(valid.map((s) => s.post_api_render_ms).filter((x) => x != null), 95),
    note: "backend_proxy_ms = sum of browser resource durations for /search+/data (supplier+laravel+network). Supplier-only fields unavailable in public JSON.",
    samples,
  };
}

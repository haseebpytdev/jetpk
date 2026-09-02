async (page) => {
  const pct = (arr, p) => {
    if (!arr.length) return null;
    const a = [...arr].sort((x, y) => x - y);
    return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
  };

  const depart = "2026-09-20";
  const samples = [];

  for (let i = 0; i < 10; i++) {
    const t0 = Date.now();
    const url =
      "https://jetpakistan.pk/flights/results?from=LHE&to=DXB&depart=" +
      depart +
      "&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0&sort=cheapest&_=" +
      Date.now();

    const apiTimings = [];
    const onResp = async (resp) => {
      try {
        const u = resp.url();
        if (!u.includes("/flights/results/")) return;
        const timing = resp.request().timing ? resp.request().timing() : null;
        const start = Date.now();
        let body = null;
        try {
          body = await resp.json();
        } catch (e) {
          body = null;
        }
        const end = Date.now();
        apiTimings.push({
          url: u.replace("https://jetpakistan.pk", ""),
          status: resp.status(),
          wait_ms: end - start,
          supplier_ms:
            body && (body.supplier_ms || body.timings?.supplier_ms || body.meta?.supplier_ms || null),
          laravel_ms: body && (body.laravel_ms || body.timings?.laravel_ms || null),
          pipeline: body && (body.status || body.pipeline_status || body.search_status || null),
          offers:
            body &&
            ((body.offers && body.offers.length) ||
              (body.outbound_options && body.outbound_options.length) ||
              (body.paired_options && body.paired_options.length) ||
              body.total ||
              0),
        });
      } catch (e) {}
    };
    page.on("response", onResp);

    await page.goto(url, { waitUntil: "domcontentloaded", timeout: 120000 });

    let firstUseful = null;
    let apiComplete = null;
    try {
      await page.waitForFunction(
        () => {
          const t = document.body && document.body.innerText ? document.body.innerText : "";
          return (
            /PKR|Select|Book|Cheapest|flight/i.test(t) &&
            !/Searching live flights|Finding the best|Initializing/i.test(t)
          ) || document.querySelectorAll("[data-testid*=offer], [data-testid*=flight-card], [data-testid*=result]").length > 0;
        },
        { timeout: 90000 },
      );
      firstUseful = Date.now() - t0;
    } catch (e) {
      firstUseful = null;
    }

    // Wait briefly for pipeline settle
    await page.waitForTimeout(1500);
    const pageMarks = await page.evaluate(() => {
      const nav = performance.getEntriesByType("navigation")[0];
      const res = performance.getEntriesByType("resource");
      const dataCalls = res.filter((r) => r.name.includes("/flights/results/data") || r.name.includes("/flights/results/search"));
      const lastData = dataCalls.length ? dataCalls[dataCalls.length - 1] : null;
      const text = document.body.innerText || "";
      const skeleton = /Finding the best available flights|Searching live flights|Loading…/i.test(text);
      return {
        docTtfb: Math.round((nav && nav.responseStart) || 0),
        dataComplete: lastData ? Math.round(lastData.startTime + lastData.duration) : null,
        dataCount: dataCalls.length,
        hasResults: /PKR/.test(text),
        skeletonVisible: skeleton,
        cardish: document.querySelectorAll("[data-testid]").length,
      };
    });

    page.off("response", onResp);

    const dataApis = apiTimings.filter((a) => a.url.includes("/data") || a.url.includes("/search"));
    const total = firstUseful;
    const postApi =
      pageMarks.dataComplete != null && firstUseful != null
        ? Math.max(0, firstUseful - pageMarks.dataComplete)
        : null;

    samples.push({
      i,
      total_ms: total,
      post_api_render_ms: postApi,
      doc_ttfb_ms: pageMarks.docTtfb,
      data_complete_nav_ms: pageMarks.dataComplete,
      data_request_count: pageMarks.dataCount,
      has_results: pageMarks.hasResults,
      skeleton_at_end: pageMarks.skeletonVisible,
      api: dataApis.slice(0, 6),
      valid: !!(firstUseful && pageMarks.hasResults),
    });
  }

  const valid = samples.filter((s) => s.valid);
  const totals = valid.map((s) => s.total_ms);
  const posts = valid.map((s) => s.post_api_render_ms).filter((x) => x != null);
  return {
    summary: {
      ONEWAY_VALID_SAMPLES: valid.length,
      ONEWAY_TOTAL_P50_MS: pct(totals, 50),
      ONEWAY_TOTAL_P95_MS: pct(totals, 95),
      ONEWAY_POST_API_RENDER_P50_MS: pct(posts, 50),
      ONEWAY_POST_API_RENDER_P95_MS: pct(posts, 95),
      ONEWAY_SUPPLIER_P50_MS: null,
      ONEWAY_SUPPLIER_P95_MS: null,
      ONEWAY_LARAVEL_NON_SUPPLIER_P50_MS: null,
      ONEWAY_LARAVEL_NON_SUPPLIER_P95_MS: null,
      ONEWAY_NEXT_OVERHEAD_P50_MS: pct(posts, 50),
      ONEWAY_NEXT_OVERHEAD_P95_MS: pct(posts, 95),
      note: "Supplier/Laravel split from JSON body fields when present; else null. Next overhead approximated as post-API render.",
    },
    samples,
  };
}

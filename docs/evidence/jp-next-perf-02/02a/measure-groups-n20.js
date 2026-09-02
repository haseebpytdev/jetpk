async (page) => {
  const pct = (arr, p) => {
    if (!arr.length) return null;
    const a = [...arr].sort((x, y) => x - y);
    return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
  };

  const measureOnce = async (cold) => {
    const t0 = Date.now();
    const url =
      "https://jetpakistan.pk/groups/search?sector=ISB-SHJ" +
      (cold ? ("&jpAuditReset=1&_=" + Date.now()) : "");
    await page.goto(url, { waitUntil: "domcontentloaded", timeout: 90000 });
    let filterReady = null;
    try {
      await page.waitForFunction(
        () => {
          const t = document.body && document.body.innerText ? document.body.innerText : "";
          return /AIR SIAL|FLY JINNAH|AIR ARABIA/.test(t) && /SECTOR|Any sector|ISB-SHJ/.test(t);
        },
        { timeout: 60000 },
      );
      filterReady = Date.now() - t0;
    } catch (e) {
      filterReady = null;
    }
    let firstCard = null;
    try {
      await page.waitForFunction(
        () => /View details|Showing .* group/.test(document.body && document.body.innerText ? document.body.innerText : ""),
        { timeout: 60000 },
      );
      firstCard = Date.now() - t0;
    } catch (e) {
      firstCard = null;
    }
    const marks = await page.evaluate(() => {
      const nav = performance.getEntriesByType("navigation")[0];
      const res = performance.getEntriesByType("resource");
      const data = res.find((r) => r.name.includes("/groups/search/data"));
      const facets = res.find((r) => r.name.includes("/groups/search/facets"));
      const chunks = res
        .filter((r) => r.name.includes("/_next/static/chunks/"))
        .map((r) => ({
          name: r.name.split("/").pop(),
          ttfb: Math.round(r.responseStart - r.requestStart),
          dur: Math.round(r.duration),
          enc: r.encodedBodySize || 0,
        }))
        .filter((c) => c.dur > 1000 || c.ttfb > 1000);
      const html = document.documentElement.innerHTML;
      const buildMatch = html.match(/AJ9bvi6_QyxDfAP2TgofV|m-n0qXZkLHvCqrRPZ2lcx/);
      return {
        docTtfb: Math.round((nav && nav.responseStart) || 0),
        dcl: Math.round((nav && nav.domContentLoadedEventEnd) || 0),
        clientData: data ? Math.round(data.startTime + data.duration) : null,
        clientFacets: facets ? Math.round(facets.startTime + facets.duration) : null,
        slowChunks: chunks.slice(0, 10),
        cards: ((document.body.innerText || "").match(/View details/g) || []).length,
        build: buildMatch ? buildMatch[0] : null,
      };
    });
    return Object.assign(
      {
        cold_warm: cold ? "cold" : "warm",
        filter_ready_ms: filterReady,
        first_card_ms: firstCard,
      },
      marks,
    );
  };

  const samples = [];
  for (let i = 0; i < 10; i++) samples.push(await measureOnce(true));
  for (let i = 0; i < 10; i++) samples.push(await measureOnce(false));

  const cold = samples.filter((s) => s.cold_warm === "cold" && s.first_card_ms != null);
  const warm = samples.filter((s) => s.cold_warm === "warm" && s.first_card_ms != null);
  const cf = cold.map((s) => s.filter_ready_ms).filter((x) => x != null);
  const wf = warm.map((s) => s.filter_ready_ms).filter((x) => x != null);
  return {
    summary: {
      cold_n: cold.length,
      warm_n: warm.length,
      GROUP_FILTER_READY_COLD_P50_MS: pct(cf, 50),
      GROUP_FILTER_READY_COLD_P95_MS: pct(cf, 95),
      GROUP_FILTER_READY_WARM_P50_MS: pct(wf, 50),
      GROUP_FILTER_READY_WARM_P95_MS: pct(wf, 95),
      GROUP_FIRST_CARD_COLD_P50_MS: pct(
        cold.map((s) => s.first_card_ms),
        50,
      ),
      GROUP_FIRST_CARD_COLD_P95_MS: pct(
        cold.map((s) => s.first_card_ms),
        95,
      ),
      GROUP_FIRST_CARD_WARM_P50_MS: pct(
        warm.map((s) => s.first_card_ms),
        50,
      ),
      GROUP_FIRST_CARD_WARM_P95_MS: pct(
        warm.map((s) => s.first_card_ms),
        95,
      ),
    },
    samples,
  };
}

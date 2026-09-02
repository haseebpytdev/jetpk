async (page) => {
  const pct = (arr, p) => {
    if (!arr.length) return null;
    const a = [...arr].sort((x, y) => x - y);
    return a[Math.min(a.length - 1, Math.max(0, Math.ceil((p / 100) * a.length) - 1))];
  };

  const waitCards = async (timeout = 90000) => {
    await page.waitForSelector('[data-testid="flight-result-card"]', { timeout });
  };

  const measureSearch = async ({ url, n, label }) => {
    const samples = [];
    for (let i = 0; i < n; i++) {
      const t0 = Date.now();
      const hit = url.includes("?") ? "&" : "?";
      await page.goto(url + hit + "_=" + Date.now(), { waitUntil: "domcontentloaded", timeout: 120000 });
      let firstCard = null;
      let skeletonRegress = 0;
      try {
        await waitCards(100000);
        firstCard = Date.now() - t0;
        await page.waitForTimeout(800);
        const stillSkeleton = await page.evaluate(() =>
          /Finding the best available flights|Searching live flights…/i.test(document.body.innerText || ""),
        );
        const hasCards = await page.locator('[data-testid="flight-result-card"]').count();
        if (stillSkeleton && hasCards === 0) skeletonRegress = 1;
      } catch (e) {
        firstCard = null;
      }
      const marks = await page.evaluate(() => {
        const res = performance.getEntriesByType("resource");
        const apis = res.filter(
          (r) =>
            r.name.includes("/flights/results/data") ||
            r.name.includes("/flights/results/search") ||
            r.name.includes("/flights/return-options/data"),
        );
        const totalApi = apis.reduce((s, r) => s + r.duration, 0);
        const lastEnd = apis.length
          ? Math.max(...apis.map((r) => r.startTime + r.duration))
          : null;
        const slowChunks = res
          .filter((r) => r.name.includes("/_next/static/chunks/"))
          .map((r) => ({
            name: r.name.split("/").pop(),
            dur: Math.round(r.duration),
            ttfb: Math.round(r.responseStart - r.requestStart),
          }))
          .filter((c) => c.dur > 1000 || c.ttfb > 1000);
        return {
          api_total_duration_ms: Math.round(totalApi),
          api_last_end_ms: lastEnd != null ? Math.round(lastEnd) : null,
          api_count: apis.length,
          cards: document.querySelectorAll('[data-testid="flight-result-card"]').length,
          slowChunks: slowChunks.slice(0, 8),
          href: location.href,
        };
      });
      const post =
        firstCard != null && marks.api_last_end_ms != null
          ? Math.max(0, firstCard - marks.api_last_end_ms)
          : null;
      const nextOh =
        firstCard != null && marks.api_total_duration_ms != null
          ? Math.max(0, firstCard - marks.api_total_duration_ms)
          : post;
      samples.push({
        i,
        label,
        total_ms: firstCard,
        backend_proxy_ms: marks.api_total_duration_ms,
        post_api_render_ms: post,
        next_overhead_ms: nextOh,
        skeleton_regression: skeletonRegress,
        valid: !!(firstCard && marks.cards > 0),
        ...marks,
      });
    }
    const valid = samples.filter((s) => s.valid);
    return {
      label,
      valid_n: valid.length,
      TOTAL_P50: pct(
        valid.map((s) => s.total_ms),
        50,
      ),
      TOTAL_P95: pct(
        valid.map((s) => s.total_ms),
        95,
      ),
      BACKEND_P50: pct(
        valid.map((s) => s.backend_proxy_ms),
        50,
      ),
      BACKEND_P95: pct(
        valid.map((s) => s.backend_proxy_ms),
        95,
      ),
      POST_API_P50: pct(
        valid.map((s) => s.post_api_render_ms).filter((x) => x != null),
        50,
      ),
      POST_API_P95: pct(
        valid.map((s) => s.post_api_render_ms).filter((x) => x != null),
        95,
      ),
      NEXT_OH_P50: pct(
        valid.map((s) => s.next_overhead_ms).filter((x) => x != null),
        50,
      ),
      NEXT_OH_P95: pct(
        valid.map((s) => s.next_overhead_ms).filter((x) => x != null),
        95,
      ),
      SKELETON_REGRESSIONS: valid.reduce((s, x) => s + x.skeleton_regression, 0),
      samples,
    };
  };

  const oneway = await measureSearch({
    label: "oneway",
    n: 10,
    url: "https://jetpakistan.pk/flights/results?from=LHE&to=DXB&depart=2026-09-20&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0&sort=cheapest",
  });

  const paired = await measureSearch({
    label: "return_paired",
    n: 10,
    url: "https://jetpakistan.pk/flights/results?from=ISB&to=DXB&depart=2026-09-22&return_date=2026-09-29&trip_type=round_trip&cabin=economy&adults=1&children=0&infants=0&sort=cheapest&view=pair",
  });

  // Segmented: reuse last paired search_id when possible, else fresh
  let segmentedUrl =
    "https://jetpakistan.pk/flights/results?from=ISB&to=DXB&depart=2026-09-22&return_date=2026-09-29&trip_type=round_trip&cabin=economy&adults=1&children=0&infants=0&sort=cheapest&view=segmented";
  const lastHref = paired.samples.filter((s) => s.valid).slice(-1)[0];
  if (lastHref && lastHref.href && lastHref.href.includes("search_id=")) {
    segmentedUrl = lastHref.href.replace("view=pair", "view=segmented");
    if (!segmentedUrl.includes("view=")) segmentedUrl += "&view=segmented";
  }
  const segmented = await measureSearch({ label: "return_segmented", n: 10, url: segmentedUrl });

  // View switches on a live return page
  await page.goto(
    "https://jetpakistan.pk/flights/results?from=ISB&to=DXB&depart=2026-09-22&return_date=2026-09-29&trip_type=round_trip&cabin=economy&adults=1&children=0&infants=0&sort=cheapest&view=pair",
    { waitUntil: "domcontentloaded", timeout: 120000 },
  );
  await waitCards(100000);
  const switches = { pair_to_seg: [], seg_to_pair: [] };
  for (let i = 0; i < 20; i++) {
    const t0 = Date.now();
    // click Segmented if present else rewrite URL
    const segBtn = page.locator('[data-testid="return-view-switch"] button, [data-testid="return-view-switch"] a').filter({ hasText: /Segment/i }).first();
    if (await segBtn.count()) {
      await segBtn.click();
    } else {
      const u = new URL(page.url());
      u.searchParams.set("view", "segmented");
      await page.goto(u.toString(), { waitUntil: "domcontentloaded" });
    }
    await waitCards(60000);
    switches.pair_to_seg.push(Date.now() - t0);
    await page.waitForTimeout(200);

    const t1 = Date.now();
    const pairBtn = page.locator('[data-testid="return-view-switch"] button, [data-testid="return-view-switch"] a').filter({ hasText: /Pair/i }).first();
    if (await pairBtn.count()) {
      await pairBtn.click();
    } else {
      const u = new URL(page.url());
      u.searchParams.set("view", "pair");
      await page.goto(u.toString(), { waitUntil: "domcontentloaded" });
    }
    await waitCards(60000);
    switches.seg_to_pair.push(Date.now() - t1);
    await page.waitForTimeout(150);
  }

  // Local sort / filter on one-way results
  await page.goto(
    "https://jetpakistan.pk/flights/results?from=LHE&to=DXB&depart=2026-09-20&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0&sort=cheapest",
    { waitUntil: "domcontentloaded", timeout: 120000 },
  );
  await waitCards(100000);
  const sortTimes = [];
  const filterTimes = [];
  let readyToSkeleton = 0;
  for (let i = 0; i < 10; i++) {
    const beforeCards = await page.locator('[data-testid="flight-result-card"]').count();
    const t0 = Date.now();
    const sort = page.locator('[data-testid="sort-control"]');
    if (await sort.count()) {
      await sort.selectOption({ index: (i % 3) + 1 }).catch(async () => {
        await sort.click().catch(() => {});
      });
    }
    await page.waitForTimeout(50);
    await waitCards(30000).catch(() => {});
    sortTimes.push(Date.now() - t0);
    const midCards = await page.locator('[data-testid="flight-result-card"]').count();
    const skel = await page.evaluate(() =>
      /Finding the best available flights/i.test(document.body.innerText || ""),
    );
    if (skel && midCards === 0 && beforeCards > 0) readyToSkeleton += 1;
  }
  for (let i = 0; i < 10; i++) {
    const t0 = Date.now();
    const panel = page.locator('[data-testid="results-filter-panel"]');
    if (await panel.count()) {
      const cb = panel.locator('input[type="checkbox"]').first();
      if (await cb.count()) await cb.click({ force: true }).catch(() => {});
    }
    await page.waitForTimeout(50);
    await waitCards(30000).catch(() => {});
    filterTimes.push(Date.now() - t0);
  }

  // Nearby dates
  const nearby = [];
  for (let i = 0; i < 5; i++) {
    const t0 = Date.now();
    const next = page.locator('[data-testid="nearby-date-next"]');
    if (await next.count()) await next.click();
    await waitCards(90000).catch(() => {});
    nearby.push(Date.now() - t0);
  }

  // User action ack — groups landing submit
  const acks = [];
  await page.goto("https://jetpakistan.pk/groups", { waitUntil: "domcontentloaded" });
  for (let i = 0; i < 20; i++) {
    const t0 = performance.now ? Date.now() : Date.now();
    const start = Date.now();
    await page.evaluate(() => {
      const btn = document.querySelector('[data-testid="groups-landing-page"] button, form button');
      if (btn) btn.dispatchEvent(new MouseEvent("click", { bubbles: true }));
    });
    // measure until searching state or navigation start
    try {
      await page.waitForFunction(
        () => /Searching|Checking|Finding matching/i.test(document.body.innerText || "") || location.pathname.includes("/groups/search"),
        { timeout: 2000 },
      );
    } catch (e) {}
    acks.push(Date.now() - start);
    if (!page.url().includes("/groups")) await page.goto("https://jetpakistan.pk/groups", { waitUntil: "domcontentloaded" });
    await page.waitForTimeout(100);
  }

  return {
    oneway,
    paired,
    segmented,
    switches: {
      PAIR_TO_SEGMENTED_P50_MS: pct(switches.pair_to_seg, 50),
      PAIR_TO_SEGMENTED_P95_MS: pct(switches.pair_to_seg, 95),
      SEGMENTED_TO_PAIR_P50_MS: pct(switches.seg_to_pair, 50),
      SEGMENTED_TO_PAIR_P95_MS: pct(switches.seg_to_pair, 95),
      n: switches.pair_to_seg.length,
    },
    local: {
      LOCAL_SORT_P95_MS: pct(sortTimes, 95),
      LOCAL_FILTER_P95_MS: pct(filterTimes, 95),
      FILTER_OR_SORT_READY_TO_SKELETON_REGRESSIONS: readyToSkeleton,
      NEARBY_DATE_TOTAL_P50_MS: pct(nearby, 50),
      NEARBY_DATE_TOTAL_P95_MS: pct(nearby, 95),
    },
    ack: {
      USER_ACTION_TO_ACK_P50_MS: pct(acks, 50),
      USER_ACTION_TO_ACK_P95_MS: pct(acks, 95),
      n: acks.length,
      samples: acks,
    },
  };
}

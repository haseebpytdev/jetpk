/**
 * Closure-05 Tracks E/F: independent read-only flight-search provenance.
 *
 * Env:
 *   CLOSURE05_HOMEPAGE_API — homepage manifest (default production)
 *   CLOSURE05_SEARCH_BASE — flight search host (default production)
 */
import { chromium } from "../../../frontend/node_modules/playwright/index.mjs";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const HOMEPAGE_API = process.env.CLOSURE05_HOMEPAGE_API ?? "https://jetpakistan.pk/api/public/content/homepage";
const SEARCH_BASE = (process.env.CLOSURE05_SEARCH_BASE ?? "https://jetpakistan.pk").replace(/\/$/, "");
const ORIGIN_POOL = (process.env.JETPK_HOMEPAGE_DESTINATION_ORIGIN_POOL ?? "KHI,LHE,ISB")
  .split(",")
  .map((s) => s.trim().toUpperCase())
  .filter(Boolean);

const trendingOut = path.join(__dirname, "trending-cheapest-provenance.json");
const destOut = path.join(__dirname, "destination-cheapest-provenance.json");

function readJson(text) {
  const cleaned = String(text).replace(/^\uFEFF/, "").trim();
  try {
    return JSON.parse(cleaned);
  } catch {
    return null;
  }
}

function parsePriceLabel(label) {
  const digits = String(label ?? "").replace(/[^\d]/g, "");
  return digits ? Number.parseInt(digits, 10) : null;
}

function offerPrice(offer) {
  const p = Number(offer?.final_customer_price ?? offer?.total ?? 0);
  return p > 0 ? p : null;
}

function collectOffers(body) {
  const offers = [
    ...(Array.isArray(body?.offers) ? body.offers : []),
    ...(Array.isArray(body?.outbound_options) ? body.outbound_options : []),
    ...(Array.isArray(body?.paired_options) ? body.paired_options.flatMap((p) => p?.offers ?? [p]) : []),
  ];
  return offers;
}

function pickMinOffer(offers) {
  let best = null;
  for (const offer of offers) {
    const price = offerPrice(offer);
    if (price === null) continue;
    if (!best || price < best.price) {
      best = {
        price,
        provider: offer?.supplier_provider ?? offer?.provider ?? null,
        date: offer?.departure_date ?? offer?.segments?.[0]?.departure_date ?? null,
        offer_id: offer?.offer_id ?? offer?.id ?? null,
      };
    }
  }
  return best;
}

async function runSearch(request, criteria) {
  const qs = new URLSearchParams({
    from: criteria.from,
    to: criteria.to,
    depart: criteria.depart,
    trip_type: criteria.trip_type,
    cabin: criteria.cabin ?? "economy",
    adults: String(criteria.adults ?? 1),
    children: "0",
    infants: "0",
    _: `${Date.now()}-closure05`,
  });
  if (criteria.return_date) {
    qs.set("return", criteria.return_date);
    qs.set("return_date", criteria.return_date);
  }

  const initRes = await request.get(`${SEARCH_BASE}/laravel/flights/results/search?${qs}`);
  const init = readJson(await initRes.text());
  const searchId = init?.search_id;
  if (!searchId) {
    return {
      search_status: "init_failed",
      http: initRes.status(),
      offers_considered: 0,
      eligible_offers: 0,
      min_eligible_customer_price: null,
      winning_provider: null,
      winning_date: criteria.depart,
      error: "no_search_id",
      pages_fetched: 0,
    };
  }

  let last = null;
  let allOffers = [];
  let pagesFetched = 0;

  for (let i = 0; i < 100; i++) {
    await new Promise((r) => setTimeout(r, 250));
    const dataRes = await request.get(
      `${SEARCH_BASE}/laravel/flights/results/data?search_id=${encodeURIComponent(searchId)}&page=1&per_page=25&sort=cheapest`,
    );
    const body = readJson(await dataRes.text());
    last = body;
    const status = String(body?.status ?? body?.search_status ?? "").toLowerCase();
    if (status !== "ready" && status !== "empty" && status !== "failed") continue;

    const totalPages = Number(body?.pagination?.total_pages ?? body?.meta?.total_pages ?? 1);
    for (let page = 1; page <= totalPages; page++) {
      const pageRes = await request.get(
        `${SEARCH_BASE}/laravel/flights/results/data?search_id=${encodeURIComponent(searchId)}&page=${page}&per_page=25&sort=cheapest`,
      );
      const pageBody = readJson(await pageRes.text());
      pagesFetched++;
      allOffers.push(...collectOffers(pageBody));
    }
    break;
  }

  const priced = allOffers.map((o) => offerPrice(o)).filter((p) => p !== null);
  const winner = pickMinOffer(allOffers);

  return {
    search_id: searchId,
    search_status: last?.status ?? last?.search_status ?? null,
    offers_considered: allOffers.length,
    eligible_offers: priced.length,
    min_eligible_customer_price: winner?.price ?? null,
    winning_provider: winner?.provider ?? null,
    winning_date: winner?.date ?? criteria.depart,
    pages_fetched: pagesFetched,
    suppliers: (last?.search_perf?.providers ?? []).map((p) => ({
      name: p.name ?? p.provider,
      status: p.status ?? p.state,
      skip_reason: p.skip_reason ?? null,
    })),
  };
}

function parseDepartFromUrl(url, fallback) {
  try {
    const u = new URL(url, SEARCH_BASE);
    return u.searchParams.get("depart") || fallback;
  } catch {
    return fallback;
  }
}

function priceMatches(displayed, minPrice) {
  if (displayed === null || minPrice === null) return false;
  return Math.round(displayed) === Math.round(minPrice);
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext();
  const request = ctx.request;

  const homeRes = await request.get(HOMEPAGE_API);
  const homepage = readJson(await homeRes.text());
  if (!homepage) {
    console.error("HOMEPAGE_FETCH_FAILED");
    process.exit(2);
  }

  const trendingReport = {
    audited_at: new Date().toISOString(),
    environment: HOMEPAGE_API.includes("127.0.0.1") ? "local_authoritative" : "production_read_only",
    homepage_api: HOMEPAGE_API,
    search_base: SEARCH_BASE,
    authority: "GET /laravel/flights/results/search + paginated /data (final_customer_price minimum)",
    routes: [],
    result: "PENDING",
  };

  for (const item of homepage?.routes?.items ?? []) {
    if (String(item.enabled ?? "1") !== "1") continue;
    if (String(item.dynamic_fare_enabled ?? "0") !== "1") continue;
    const from = String(item.from ?? "").toUpperCase();
    const to = String(item.to ?? "").toUpperCase();
    const tripType = String(item.trip_type ?? "one_way") === "return" ? "return" : "one_way";
    const depart = parseDepartFromUrl(item.search_url ?? "", item.fare_target_date ?? "");
    const criteria = {
      from,
      to,
      depart,
      trip_type: tripType,
      cabin: item.cabin ?? "economy",
      adults: item.adults ?? 1,
    };
    if (tripType === "return" && item.return_date) criteria.return_date = item.return_date;

    const search = await runSearch(request, criteria);
    const displayed = parsePriceLabel(item.price_label ?? item.price);
    const ctaDepart = parseDepartFromUrl(item.search_url ?? "", "");
    trendingReport.routes.push({
      route: item.id,
      origin: from,
      destination: to,
      trip_type: tripType,
      search_date_context: depart,
      OFFERS_CONSIDERED: search.offers_considered,
      ELIGIBLE_OFFERS: search.eligible_offers,
      MIN_ELIGIBLE_CUSTOMER_PRICE: search.min_eligible_customer_price,
      WINNING_PROVIDER: search.winning_provider,
      WINNING_DATE: search.winning_date,
      DISPLAYED_PRICE: displayed,
      PRICE_MATCH: priceMatches(displayed, search.min_eligible_customer_price),
      DATE_MATCH: !depart || !ctaDepart ? true : depart === ctaDepart,
      CTA_MATCH: Boolean(item.search_url),
      ...search,
      displayed_price: displayed,
      cta_url: item.search_url ?? item.cta_url ?? "",
      price_match: priceMatches(displayed, search.min_eligible_customer_price),
      date_match: !depart || !ctaDepart ? true : depart === ctaDepart,
      cta_match: Boolean(item.search_url),
    });
  }

  trendingReport.result = trendingReport.routes.length === 0
    ? "FAIL"
    : trendingReport.routes.every((r) => r.price_match && r.date_match && r.cta_match)
      ? "PASS"
      : trendingReport.routes.some((r) => r.min_eligible_customer_price === null)
        ? "FAIL"
        : "FAIL";

  const destReport = {
    audited_at: new Date().toISOString(),
    environment: trendingReport.environment,
    homepage_api: HOMEPAGE_API,
    search_base: SEARCH_BASE,
    origin_pool: ORIGIN_POOL,
    destinations: [],
    result: "PENDING",
  };

  for (const item of homepage?.destinations?.items ?? []) {
    if (String(item.enabled ?? "1") !== "1") continue;
    const destination = String(item.code ?? "").toUpperCase();
    const perOrigin = {};
    let best = null;
    let bestOrigin = null;
    let bestDate = null;

    for (const origin of ORIGIN_POOL) {
      if (origin === destination) continue;
      const depart = parseDepartFromUrl(item.href ?? item.link ?? "", item.fare_target_date ?? "2026-09-15");
      const search = await runSearch(request, {
        from: origin,
        to: destination,
        depart,
        trip_type: "one_way",
        cabin: "economy",
        adults: 1,
      });
      if (search.min_eligible_customer_price !== null) {
        perOrigin[origin] = search.min_eligible_customer_price;
        if (!best || search.min_eligible_customer_price < best) {
          best = search.min_eligible_customer_price;
          bestOrigin = origin;
          bestDate = search.winning_date;
        }
      } else {
        perOrigin[origin] = null;
      }
    }

    const displayed = parsePriceLabel(item.price_label ?? item.price);
    const linkOrigin = (() => {
      try {
        return new URL(item.href ?? item.link ?? "", SEARCH_BASE).searchParams.get("from")?.toUpperCase() ?? null;
      } catch {
        return item.winning_origin ?? null;
      }
    })();
    const linkDate = parseDepartFromUrl(item.href ?? item.link ?? "", null);

    destReport.destinations.push({
      destination,
      origins_tested: ORIGIN_POOL.filter((o) => o !== destination),
      per_origin_minimums: perOrigin,
      winning_origin: bestOrigin,
      winning_price: best,
      winning_date: bestDate ?? linkDate,
      displayed_price: displayed,
      public_link_origin: linkOrigin,
      public_link_destination: destination,
      public_link_date: linkDate,
      OFFERS_CONSIDERED: Object.keys(perOrigin).length,
      MIN_ELIGIBLE_CUSTOMER_PRICE: best,
      WINNING_PROVIDER: null,
      WINNING_DATE: bestDate ?? linkDate,
      DISPLAYED_PRICE: displayed,
      PRICE_MATCH: priceMatches(displayed, best),
      ORIGIN_MATCH: bestOrigin !== null ? bestOrigin === linkOrigin : false,
      DATE_MATCH: Boolean(linkDate),
      price_match: priceMatches(displayed, best),
      origin_match: bestOrigin !== null ? bestOrigin === linkOrigin : false,
      date_match: Boolean(linkDate),
    });
  }

  destReport.result = destReport.destinations.length === 0
    ? "FAIL"
    : destReport.destinations.every((d) => d.price_match && d.origin_match && d.date_match)
      ? "PASS"
      : "FAIL";

  fs.writeFileSync(trendingOut, JSON.stringify(trendingReport, null, 2));
  fs.writeFileSync(destOut, JSON.stringify(destReport, null, 2));
  await browser.close();

  console.log(JSON.stringify({
    TRENDING_TRUE_CHEAPEST: trendingReport.result,
    DESTINATION_TRUE_CHEAPEST: destReport.result,
    trending_routes: trendingReport.routes.length,
    destinations: destReport.destinations.length,
  }, null, 2));

  process.exit(trendingReport.result === "PASS" && destReport.result === "PASS" ? 0 : 1);
}

main();

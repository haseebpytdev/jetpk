/**
 * JP-PERF-FINAL-02R — same-sample Traveler layer decomposition (read-only).
 * Separates supplier/network wait from JetPakistan-controlled application latency.
 */
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const IN = process.argv[2] || path.join(__dirname, "traveler-n30.json");
const OUT = path.join(__dirname, "traveler-classification-summary.json");

function pct(arr, p) {
  const a = arr.filter((n) => typeof n === "number" && Number.isFinite(n)).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.ceil((p / 100) * a.length) - 1)];
}

function num(v) {
  return typeof v === "number" && Number.isFinite(v) ? v : 0;
}

const raw = JSON.parse(fs.readFileSync(IN, "utf8"));
const samples = (raw.samples || []).filter((s) => s.valid);

const classified = samples.map((s) => {
  const wall = num(s.BOOK_NOW_TO_TRAVELER_READY_MS ?? s.total_ms);
  const ack = num(s.ACK_MS ?? s.ack_ms);
  const fare = num(s.SUPPLIER_FARE_MS ?? s.fare_ms ?? s.FARE_REVALIDATION_MS);
  const jpPre = num(s.JP_PRE_SUPPLIER_MS);
  const jpPostVal = num(s.JP_POST_SUPPLIER_VALIDATION_MS);
  const navShell = num(s.NAV_TO_SHELL_MS ?? s.shell_ms);
  const navApp = num(s.NAV_TO_SHELL_APP_MS ?? s.shell_app);
  const navExt = num(s.NAV_TO_SHELL_EXTERNAL_MS);
  const shellToFetch = num(s.SHELL_TO_PASSENGERS_REQUEST_MS);
  const fetchTotal = num(s.PASSENGERS_FETCH_MS ?? s.fetch_ms);
  const passServer = num(s.PASSENGERS_SERVER_MS);
  const passHold = num(s.PASSENGERS_HOLD_VALIDATE_MS ?? s.hold_validate_ms);
  const passDb = num(s.PASSENGERS_DB_MS);
  const passSer = num(s.PASSENGERS_SERIALIZE_MS);
  const passClient = num(s.PASSENGERS_CLIENT_PROCESS_MS ?? s.shell_app);
  const passLive = num(s.PASSENGERS_LIVE_SEARCH_MS);
  const render = num(s.SHELL_TO_USABLE_APP_MS);

  // Supplier in revalidation POST (Book Now)
  const revalSupplier = Math.max(0, fare - jpPre - jpPostVal);
  const revalJpPre = jpPre;
  const revalJpPost = jpPostVal;

  // Passengers: hold_validate is supplier-dominated when live_search null and hold >> db+serialize
  const passJpOverhead = Math.max(0, passDb + passSer + (passServer - passHold - passDb - passSer));
  const passSupplier = passHold > passJpOverhead * 2 ? passHold : Math.max(0, passHold - passJpOverhead);
  const passJpServer = passJpOverhead + Math.max(0, passServer - passHold - passDb - passSer);

  // Nav transport = shell minus measured app work
  const navTransport = Math.max(0, navShell - navApp);

  // Client book-now click/ack before supplier
  const bookNowClient = ack;

  // JP app-controlled (excludes supplier waits)
  const trueApp =
    bookNowClient +
    revalJpPre +
    revalJpPost +
    navApp +
    shellToFetch +
    passJpServer +
    passClient +
    render;

  // Supplier-controlled (mandatory authority waits)
  const trueSupplier = revalSupplier + passSupplier + passLive;

  // External transport (network outside JP origin processing)
  const external = Math.max(0, fetchTotal - passServer) + navExt + navTransport;

  const attributed = trueApp + trueSupplier + external;
  const unattributed = Math.max(0, wall - attributed);

  return {
    sample_id: s.sample_id,
    wall_ms: wall,
    BOOK_NOW_CLIENT: bookNowClient,
    REVALIDATION_JP_SERVER_PRE_SUPPLIER: revalJpPre,
    REVALIDATION_SUPPLIER_WAIT: revalSupplier,
    REVALIDATION_JP_SERVER_POST_SUPPLIER: revalJpPost,
    NAV_TRANSPORT: navTransport,
    NEXT_SHELL: navApp,
    SHELL_TO_PASSENGERS_REQUEST: shellToFetch,
    PASSENGERS_JP_SERVER_PRE_SUPPLIER: 0,
    PASSENGERS_SUPPLIER_WAIT: passSupplier,
    PASSENGERS_JP_SERVER_POST_SUPPLIER: passJpServer,
    CLIENT_RENDER_PROCESS: passClient + render,
    UNATTRIBUTED: unattributed,
    TRAVELER_TRUE_APP_MS: trueApp,
    TRAVELER_TRUE_SUPPLIER_MS: trueSupplier,
    TRAVELER_EXTERNAL_MS: external,
    source: s.PREVALIDATION_SOURCE ?? s.source,
  };
});

const pick = (k) => classified.map((c) => c[k]);
const maxUnattributed = Math.max(...pick("UNATTRIBUTED"), 0);
const sum = pick("UNATTRIBUTED").reduce((a, b) => a + b, 0);

const summary = {
  phase: "JP-PERF-FINAL-02R-CURRENT",
  source: path.basename(IN),
  TRAVELER_VALID_N: classified.length,
  TRAVELER_CLASSIFICATION_RECONCILED: maxUnattributed <= 500 ? "YES" : "PARTIAL",
  UNATTRIBUTED_P95_MS: pct(pick("UNATTRIBUTED"), 95),
  UNATTRIBUTED_MAX_MS: maxUnattributed,
  UNATTRIBUTED_TOTAL_MS: sum,
  TRAVELER_TRUE_APP_P50_MS: pct(pick("TRAVELER_TRUE_APP_MS"), 50),
  TRAVELER_TRUE_APP_P95_MS: pct(pick("TRAVELER_TRUE_APP_MS"), 95),
  TRAVELER_TRUE_SUPPLIER_P50_MS: pct(pick("TRAVELER_TRUE_SUPPLIER_MS"), 50),
  TRAVELER_TRUE_SUPPLIER_P95_MS: pct(pick("TRAVELER_TRUE_SUPPLIER_MS"), 95),
  TRAVELER_EXTERNAL_P95_MS: pct(pick("TRAVELER_EXTERNAL_MS"), 95),
  layer_p95: {
    REVALIDATION_SUPPLIER_WAIT: pct(pick("REVALIDATION_SUPPLIER_WAIT"), 95),
    PASSENGERS_SUPPLIER_WAIT: pct(pick("PASSENGERS_SUPPLIER_WAIT"), 95),
    NEXT_SHELL: pct(pick("NEXT_SHELL"), 95),
    CLIENT_RENDER_PROCESS: pct(pick("CLIENT_RENDER_PROCESS"), 95),
  },
  prior_misclassified: {
    APP_CONTROLLED_P95_MS: raw.APP_CONTROLLED_P95_MS,
    note: "Prior APP_CONTROLLED included supplier revalidation and passengers hold_validate",
  },
  fresh_cohort: raw.COHORT_FRESH_PREVALIDATION,
  samples: classified,
};

fs.writeFileSync(OUT, JSON.stringify(summary, null, 2));
console.log(
  JSON.stringify(
    {
      n: summary.TRAVELER_VALID_N,
      reconciled: summary.TRAVELER_CLASSIFICATION_RECONCILED,
      true_app_p95: summary.TRAVELER_TRUE_APP_P95_MS,
      true_supplier_p95: summary.TRAVELER_TRUE_SUPPLIER_P95_MS,
      unattributed_p95: summary.UNATTRIBUTED_P95_MS,
    },
    null,
    2,
  ),
);

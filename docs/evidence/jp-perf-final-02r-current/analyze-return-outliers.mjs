/**
 * Classify Return N=30 samples >4500ms using same-sample decomposition.
 * Input: docs/evidence/jp-perf-final-02r-current/return-n30.json
 */
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const IN = path.join(__dirname, "return-n30.json");
const OUT_MD = path.join(__dirname, "return-outliers.md");
const OUT_JSON = path.join(__dirname, "return-decomposition-summary.json");

function pct(arr, p) {
  const a = arr.filter((n) => typeof n === "number" && Number.isFinite(n)).sort((x, y) => x - y);
  if (!a.length) return null;
  return a[Math.min(a.length - 1, Math.ceil((p / 100) * a.length) - 1)];
}

const data = JSON.parse(fs.readFileSync(IN, "utf8"));
const valid = (data.samples || []).filter((s) => s.valid);

const decomposed = valid.map((s) => {
  const wall = s.TOTAL_CLICK_TO_FIRST_USEFUL_RETURN_MS || 0;
  const sp = s.search_perf || {};
  const preSupplier = s.TOTAL_PRE_SUPPLIER_MS ?? sp.TOTAL_PRE_SUPPLIER_MS ?? 0;
  const firstValidPair = s.FIRST_VALID_PAIR_AVAILABLE_MS ?? sp.FIRST_VALID_PAIR_MS ?? null;
  const pairPersist = s.FIRST_VALID_PAIR_PERSISTED_MS ?? sp.FIRST_VALID_PAIR_PERSISTED_MS ?? firstValidPair;
  const persistToPoll = s.PAIR_PERSIST_TO_POLL_READABLE_MS ?? sp.PAIR_PERSIST_TO_POLL_READABLE_MS ?? 0;
  const pollServer = sp.POLL_TOTAL_SERVER_MS ?? s.POLL_TOTAL_SERVER_MS ?? 0;
  const render = s.BROWSER_RENDER_MS ?? 0;
  const shell = s.loading_shell_ms ?? 0;
  const init = s.init_ms ?? 0;
  const supplierNetwork =
    typeof firstValidPair === "number" ? Math.max(0, firstValidPair - preSupplier) : null;
  const jpServerPostPair =
    typeof pairPersist === "number" && typeof firstValidPair === "number"
      ? Math.max(0, pairPersist - firstValidPair) + persistToPoll + pollServer
      : persistToPoll + pollServer;
  const jpClient = shell + init + render;
  const postSupplierToUsable =
    typeof wall === "number" && typeof firstValidPair === "number"
      ? Math.max(0, wall - firstValidPair)
      : null;
  const jpAppControlled = (jpServerPostPair || 0) + jpClient;
  const attributed =
    (preSupplier || 0) +
    (supplierNetwork || 0) +
    (jpServerPostPair || 0) +
    jpClient;
  const unattributed = Math.abs(wall - attributed);
  const dominant =
    (supplierNetwork || 0) >= (jpAppControlled || 0)
      ? "SUPPLIER_NETWORK"
      : postSupplierToUsable > 1000
        ? "JETPAKISTAN_POST_SUPPLIER"
        : "JETPAKISTAN_CLIENT/SERVER";
  return {
    sample_id: s.sample_id,
    wall_ms: wall,
    pre_supplier_ms: preSupplier,
    supplier_network_ms: supplierNetwork,
    first_valid_pair_ms: firstValidPair,
    post_supplier_to_usable_ms: postSupplierToUsable,
    jp_server_post_pair_ms: jpServerPostPair,
    jp_client_ms: jpClient,
    jp_app_controlled_ms: jpAppControlled,
    browser_render_ms: render,
    loading_shell_ms: shell,
    poll_total_server_ms: pollServer,
    unattributed_ms: unattributed,
    total_reconciled: unattributed <= Math.max(150, wall * 0.12) ? "YES" : "NO",
    dominant_cause: dominant,
    over_4500: wall > 4500,
  };
});

const over = decomposed.filter((d) => d.over_4500);
const pick = (k) => decomposed.map((d) => d[k]).filter((n) => typeof n === "number");

const summary = {
  RETURN_N: valid.length,
  RETURN_P50: data.RETURN_CLICK_TO_FIRST_USEFUL_P50_MS,
  RETURN_P95: data.RETURN_CLICK_TO_FIRST_USEFUL_P95_MS,
  RETURN_GATE: data.RETURN_CLICK_TO_FIRST_USEFUL_P95_MS <= 4500 ? "PASS" : "FAIL",
  RETURN_DUPLICATES: data.RETURN_DUPLICATE_FETCH_COUNT ?? 0,
  RETURN_APP_P95: pct(pick("jp_app_controlled_ms"), 95),
  RETURN_SUPPLIER_P95: pct(pick("supplier_network_ms"), 95),
  RETURN_EXTERNAL_P95: null,
  RETURN_POST_SUPPLIER_TO_USABLE_P95: pct(pick("post_supplier_to_usable_ms"), 95),
  RETURN_PRE_SUPPLIER_P95: pct(pick("pre_supplier_ms"), 95),
  RETURN_UNEXPLAINED_OUTLIERS: over.filter((d) => d.total_reconciled !== "YES").length,
  OVER_4500_COUNT: over.length,
  OVER_4500_DOMINANT_SUPPLIER: over.filter((d) => d.dominant_cause === "SUPPLIER_NETWORK").length,
  OVER_4500_DOMINANT_JP_POST: over.filter((d) => d.dominant_cause === "JETPAKISTAN_POST_SUPPLIER").length,
  OVER_4500_DOMINANT_JP_CLIENT: over.filter((d) =>
    d.dominant_cause.startsWith("JETPAKISTAN"),
  ).length,
  TOTAL_RECONCILED:
    decomposed.every((d) => d.total_reconciled === "YES") ? "YES" : "PARTIAL",
  outliers: over,
  all_samples: decomposed,
};

fs.writeFileSync(OUT_JSON, JSON.stringify(summary, null, 2));

const md = `# Return outlier classification — JP-PERF-FINAL-02R-CURRENT

Cohort: preserved \`return-n30.json\` (N=30, SHA256 B4BF43…)

## Aggregate decomposition (all 30 samples)

| Metric | ms |
|---|---|
| RETURN_P50 | ${summary.RETURN_P50} |
| RETURN_P95 | ${summary.RETURN_P95} |
| RETURN_GATE | ${summary.RETURN_GATE} |
| RETURN_SUPPLIER_P95 | ${summary.RETURN_SUPPLIER_P95} |
| RETURN_PRE_SUPPLIER_P95 | ${summary.RETURN_PRE_SUPPLIER_P95} |
| RETURN_POST_SUPPLIER_TO_USABLE_P95 | ${summary.RETURN_POST_SUPPLIER_TO_USABLE_P95} |
| RETURN_APP_P95 | ${summary.RETURN_APP_P95} |
| RETURN_UNEXPLAINED_OUTLIERS | ${summary.RETURN_UNEXPLAINED_OUTLIERS} |

## Samples >4500ms (n=${over.length})

| sample | wall | supplier | post-supplier→usable | JP app | dominant |
|---|---:|---:|---:|---:|---|
${over
  .map(
    (d) =>
      `| ${d.sample_id} | ${d.wall_ms} | ${Math.round(d.supplier_network_ms || 0)} | ${Math.round(d.post_supplier_to_usable_ms || 0)} | ${Math.round(d.jp_app_controlled_ms || 0)} | ${d.dominant_cause} |`,
  )
  .join("\n")}

## Conclusion

Absolute Return P95 **${summary.RETURN_P95}ms FAILS** ≤4500ms gate.
Over-4500ms samples: ${summary.OVER_4500_DOMINANT_SUPPLIER} supplier-dominant, ${summary.OVER_4500_DOMINANT_JP_POST} JP post-supplier, ${over.length - summary.OVER_4500_DOMINANT_SUPPLIER - summary.OVER_4500_DOMINANT_JP_POST} mixed/JP-client.
Do not code-fix until Traveler + soft-nav complete unless JP post-supplier >1000ms is proven systematic.
`;

fs.writeFileSync(OUT_MD, md);
console.log(JSON.stringify(summary, null, 2));

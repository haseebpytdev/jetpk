import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const a = JSON.parse(fs.readFileSync(path.join(__dirname, "r4-prefetch-ab-local-a.json"), "utf8"));
const b = JSON.parse(fs.readFileSync(path.join(__dirname, "r4-prefetch-ab-local-b.json"), "utf8"));
const rows = [];
for (const ar of a.matrix) {
  const br = b.matrix.find((x) => x.route === ar.route);
  if (!br) continue;
  rows.push({
    route: ar.route,
    A_USABLE_P95: ar.TOTAL_USABLE_P95,
    B_USABLE_P95: br.TOTAL_USABLE_P95,
    DELTA_USABLE_P95: (ar.TOTAL_USABLE_P95 ?? 0) - (br.TOTAL_USABLE_P95 ?? 0),
    A_RSC_END_COMMIT_P95: ar.RSC_END_TO_ROUTE_COMMIT_P95,
    B_RSC_END_COMMIT_P95: br.RSC_END_TO_ROUTE_COMMIT_P95,
    DELTA_RSC_END_COMMIT: (ar.RSC_END_TO_ROUTE_COMMIT_P95 ?? 0) - (br.RSC_END_TO_ROUTE_COMMIT_P95 ?? 0),
    A_DUP_RSC_P95: ar.DUPLICATE_RSC_COUNT_P95,
    B_DUP_RSC_P95: br.DUPLICATE_RSC_COUNT_P95,
    A_PREFETCH_RSC_P95: ar.PROGRAMMATIC_PREFETCH_RSC_COUNT_P95,
    B_PREFETCH_RSC_P95: br.PROGRAMMATIC_PREFETCH_RSC_COUNT_P95,
    A_OVERLAP_CHUNK_P95: ar.OVERLAPPING_CHUNK_COUNT_P95,
    B_OVERLAP_CHUNK_P95: br.OVERLAPPING_CHUNK_COUNT_P95,
  });
}
const worstA = a.LOCAL_TRUE_SOFT_NAV_WORST_P95;
const worstB = b.LOCAL_TRUE_SOFT_NAV_WORST_P95;
const improvedRoutes = rows.filter((r) => (r.DELTA_USABLE_P95 ?? 0) >= 150).length;
const improvedRscCommit = rows.filter((r) => (r.DELTA_RSC_END_COMMIT ?? 0) >= 100).length;
const validB = rows.every((r) => r.B_USABLE_P95 != null);
const proven =
  validB &&
  (worstB <= 1500 ||
    (worstA - worstB >= 200 && improvedRoutes >= 3 && improvedRscCommit >= 2 && worstB < worstA));
const out = {
  phase: "AUTHORITY-06-R4-AB-COMPARISON",
  captured_at: new Date().toISOString(),
  LOCAL_BASELINE_WORST_P95: worstA,
  LOCAL_R4_WORST_P95: worstB,
  R4_PREFETCH_CONTENTION: proven ? "PROVEN" : "NOT_PROVEN",
  improved_routes: improvedRoutes,
  improved_rsc_commit_routes: improvedRscCommit,
  rows,
};
fs.writeFileSync(path.join(__dirname, "r4-prefetch-ab-comparison.json"), JSON.stringify(out, null, 2));
fs.writeFileSync(
  path.join(__dirname, "r4-prefetch-ab-comparison.md"),
  `# R4 local prefetch A/B\n\n| Route | A P95 | B P95 | Δ | A rsc→commit | B rsc→commit | Δ | A dup | B dup |\n|-------|-------|-------|---|--------------|--------------|---|-------|-------|\n${rows.map((r) => `| ${r.route} | ${r.A_USABLE_P95} | ${r.B_USABLE_P95} | ${r.DELTA_USABLE_P95} | ${r.A_RSC_END_COMMIT_P95} | ${r.B_RSC_END_COMMIT_P95} | ${r.DELTA_RSC_END_COMMIT} | ${r.A_DUP_RSC_P95} | ${r.B_DUP_RSC_P95} |`).join("\n")}\n\nR4_PREFETCH_CONTENTION=${out.R4_PREFETCH_CONTENTION}\n`,
);
console.log(JSON.stringify(out, null, 2));
process.exit(proven ? 0 : 1);

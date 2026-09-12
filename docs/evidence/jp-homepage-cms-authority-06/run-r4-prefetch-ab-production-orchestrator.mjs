import { spawnSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

function run(cohort) {
  const r = spawnSync("node", [path.join(__dirname, "run-r4-prefetch-ab-production.mjs")], {
    cwd: __dirname,
    stdio: "inherit",
    env: { ...process.env, JP_COHORT: cohort },
  });
  if (r.status !== 0) throw new Error(`cohort ${cohort} failed`);
}

function compare() {
  const aPath = path.join(__dirname, "r4-prefetch-ab-production-a.json");
  const bPath = path.join(__dirname, "r4-prefetch-ab-production-b.json");
  fs.copyFileSync(aPath, path.join(__dirname, "r4-prefetch-ab-local-a.json"));
  fs.copyFileSync(bPath, path.join(__dirname, "r4-prefetch-ab-local-b.json"));
  const r = spawnSync("node", [path.join(__dirname, "run-r4-prefetch-ab-compare.mjs")], { cwd: __dirname, stdio: "inherit" });
  const cmp = JSON.parse(fs.readFileSync(path.join(__dirname, "r4-prefetch-ab-comparison.json"), "utf8"));
  cmp.mode = "production_intercept_simulation";
  cmp.note = "Cohort B aborts non-destination _rsc before click; diagnostic only, not deploy proof of source variant.";
  fs.writeFileSync(path.join(__dirname, "r4-prefetch-ab-comparison.json"), JSON.stringify(cmp, null, 2));
  process.exit(r.status ?? 1);
}

run("A");
run("B");
compare();

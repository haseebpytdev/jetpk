import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { spawnSync } from "node:child_process";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const baseline = JSON.parse(
  fs.readFileSync(path.join(repoRoot, "docs/evidence/jp-dash-final-11/DEEP-WAVE-BASELINE.json"), "utf8"),
);

spawnSync("node", [path.join(__dirname, "check-functional-cert-writers.mjs")], { stdio: "inherit" });

const ok = baseline.VERSION === 3;
console.log(`DEEP_WAVE_BASELINE_VERSION=${baseline.VERSION}`);
console.log(`WAVE_04_START_GATE=${ok ? "PASS" : "FAIL"}`);
if (!ok) {
  process.exit(1);
}

/**
 * Ensures production cert runners do not embed certified deploy SHAs in source.
 */
import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const certDir = path.resolve(__dirname, "../../scripts/jp-dashboard-prod-cert");
const targets = ["run-production-writes.mjs", "run-production-cert.mjs"];
const embeddedSha = /(?:productionSha|ENGINEERING_SHA)\s*[:=]\s*["'][0-9a-f]{40}["']/i;

for (const file of targets) {
  const source = fs.readFileSync(path.join(certDir, file), "utf8");
  assert.equal(
    embeddedSha.test(source),
    false,
    `${file} must not hardcode a production Git SHA; use JP_PRODUCTION_SHA via production-cert-env.mjs`,
  );
}

console.log("jp-dashboard-prod-cert-no-hardcoded-sha: PASS");

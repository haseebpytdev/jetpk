/**
 * Remove generated PR139 evidence fixtures from frontend/public/_evidence.
 */
import { existsSync, rmSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "..");
const evidenceRoot = path.join(frontendRoot, "public", "_evidence");
const fixtureDir = path.join(evidenceRoot, "pr139-responsive");

rmSync(fixtureDir, { recursive: true, force: true });
if (existsSync(evidenceRoot)) {
  try {
    rmSync(evidenceRoot, { recursive: true, force: true });
  } catch {
    // ignore non-empty / race
  }
}

console.log(`[pr139-fixtures] cleaned ${fixtureDir}`);
console.log(
  `[pr139-fixtures] present_after_cleanup=${existsSync(fixtureDir) ? "YES" : "NO"}`,
);

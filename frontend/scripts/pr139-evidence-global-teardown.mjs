import { spawnSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

export default async function globalTeardown() {
  const __dirname = path.dirname(fileURLToPath(import.meta.url));
  const cleanup = path.join(__dirname, "pr139-cleanup-evidence-fixtures.mjs");
  const result = spawnSync(process.execPath, [cleanup], { stdio: "inherit" });
  if (result.status !== 0) {
    throw new Error(`PR139 fixture cleanup failed with status ${result.status}`);
  }
}

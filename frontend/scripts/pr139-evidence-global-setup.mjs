import { spawnSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

export default async function globalSetup() {
  const __dirname = path.dirname(fileURLToPath(import.meta.url));
  const gen = path.join(__dirname, "pr139-generate-evidence-fixtures.mjs");
  const result = spawnSync(process.execPath, [gen], { stdio: "inherit" });
  if (result.status !== 0) {
    throw new Error(`PR139 fixture generation failed with status ${result.status}`);
  }
}

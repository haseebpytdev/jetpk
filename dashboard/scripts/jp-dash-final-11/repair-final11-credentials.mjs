/**
 * REPAIR CREDENTIAL — production QA password hash sync (mutates DB).
 * Use only when vault login is proven invalid.
 */
import { spawnSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");

console.log("AUTH_OPERATION=REPAIR_CREDENTIAL");
const result = spawnSync(
  "node",
  [path.join(__dirname, "bootstrap-final11-workstation-auth.mjs"), "--sync-production"],
  { cwd: repoRoot, encoding: "utf8", maxBuffer: 20 * 1024 * 1024 },
);
process.stdout.write(result.stdout || "");
process.stderr.write(result.stderr || "");
process.exit(result.status ?? 1);

import { spawnSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { loadQaPasswordFromVault } from "../../dashboard/scripts/jp-dash-03-acceptance/credential-vault.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const password = loadQaPasswordFromVault("admin");
if (!password) {
  console.error("PASSWORD_MISSING");
  process.exit(2);
}

const storageState = path.resolve(__dirname, "../../tmp/jp-ai-canary-admin-storage-state.json");

const env = {
  ...process.env,
  OTA_AUDIT_ALLOW_REMOTE: process.env.OTA_AUDIT_ALLOW_REMOTE ?? "1",
  JP_DASH_03_QA_ADMIN_PASSWORD: password,
  JP_CANARY_UAT_BASE: process.env.JP_CANARY_UAT_BASE ?? "https://jetpakistan.pk",
  JP_AI_CANARY_STORAGE_STATE: process.env.JP_AI_CANARY_STORAGE_STATE ?? storageState,
};

const result = spawnSync(
  "npx",
  ["playwright", "test", "--config=playwright.production-canary.config.ts"],
  { cwd: path.resolve(__dirname, ".."), env, stdio: "inherit", shell: true }
);

process.exit(result.status ?? 1);

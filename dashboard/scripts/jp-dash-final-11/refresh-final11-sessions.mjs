/**
 * REFRESH SESSION only — rebuild Playwright storage from vault password.
 * Does NOT run setup-production-qa / sync-password.
 */
import { spawnSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { FINAL11_PASSWORD_ENV_KEYS, loadFinal11Password } from "./final-11-credentials.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");

function buildChildEnv() {
  const env = { ...process.env };
  for (const [roleKey, envKey] of Object.entries(FINAL11_PASSWORD_ENV_KEYS)) {
    const pw = loadFinal11Password(roleKey);
    if (pw) {
      env[envKey] = pw;
    }
  }
  return env;
}

const onlyRole = process.env.JP_FINAL11_AUTH_ONLY?.trim();
const args = [path.join(__dirname, "build-auth-states.mjs")];
const env = buildChildEnv();
if (onlyRole) {
  env.JP_FINAL11_AUTH_ONLY = onlyRole;
}

console.log("SESSION_REFRESH_DB_MUTATIONS=0");
console.log("AUTH_OPERATION=REFRESH_SESSION");

const build = spawnSync("node", args, {
  cwd: repoRoot,
  encoding: "utf8",
  env,
  maxBuffer: 16 * 1024 * 1024,
});
process.stdout.write(build.stdout || "");
process.stderr.write(build.stderr || "");
process.exit(build.status ?? 1);

/**
 * Per-identity session refresh without DB password sync.
 */
import { spawnSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { probeRoleSession } from "./auth-session-probe.mjs";
import { FINAL11_AUTH_ROLES, storagePathForRole } from "./final-11-auth-roles.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");

export async function probeIdentitySession(browser, identityKey) {
  const role = FINAL11_AUTH_ROLES[identityKey];
  const storagePath = storagePathForRole(identityKey);
  const context = await browser.newContext({ storageState: storagePath });
  const status = await probeRoleSession(context, role);
  await context.close();
  return status;
}

export function refreshIdentitySession(identityKey) {
  const result = spawnSync("node", [path.join(__dirname, "refresh-final11-sessions.mjs")], {
    cwd: repoRoot,
    encoding: "utf8",
    env: { ...process.env, JP_FINAL11_AUTH_ONLY: identityKey },
    maxBuffer: 8 * 1024 * 1024,
  });
  return {
    ok: result.status === 0,
    out: `${result.stdout || ""}${result.stderr || ""}`,
  };
}

/**
 * @returns {Promise<"PASS"|string>}
 */
export async function ensureIdentitySession(browser, identityKey) {
  let status = await probeIdentitySession(browser, identityKey);
  if (status === "PASS") {
    return "PASS";
  }
  const refresh = refreshIdentitySession(identityKey);
  if (!refresh.ok) {
    return `REFRESH_FAILED:${status}`;
  }
  status = await probeIdentitySession(browser, identityKey);
  return status;
}

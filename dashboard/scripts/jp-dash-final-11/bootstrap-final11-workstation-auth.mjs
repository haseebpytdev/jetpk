/**
 * Ensure FINAL-11 QA passwords exist in workstation vault (generate if missing),
 * optionally sync to production QA users, then rebuild storage states.
 * For cookie expiry only, use refresh-final11-sessions.mjs (no --sync-production).
 * Never logs passwords.
 */
import { randomBytes } from "node:crypto";
import { spawnSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { loadPasswordFromCredTargets } from "../jp-dash-03-acceptance/credential-vault.mjs";
import {
  FINAL11_PASSWORD_ENV_KEYS,
  FINAL11_VAULT_TARGETS,
  loadFinal11Password,
} from "./final-11-credentials.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");

function writeCredTarget(vaultTarget, password) {
  const psScript = `
$ErrorActionPreference = 'Stop'
Add-Type @"
using System;
using System.Runtime.InteropServices;
public class JpCredWrite {
  [StructLayout(LayoutKind.Sequential, CharSet=CharSet.Unicode)]
  public struct NativeCredential {
    public int Flags; public int Type; public IntPtr TargetName; public IntPtr Comment;
    public System.Runtime.InteropServices.ComTypes.FILETIME LastWritten;
    public int CredentialBlobSize; public IntPtr CredentialBlob; public int Persist;
    public int Attribute; public IntPtr TargetAlias; public IntPtr UserName;
  }
  [DllImport("Advapi32.dll", CharSet=CharSet.Unicode, SetLastError=true)]
  public static extern bool CredWrite(ref NativeCredential userCredential, uint flags);
}
"@
$pw = @'
${password.replace(/'/g, "''")}
'@
$blob = [System.Text.Encoding]::Unicode.GetBytes($pw + [char]0)
$cred = New-Object JpCredWrite+NativeCredential
$cred.Type = 1
$cred.TargetName = [System.Runtime.InteropServices.Marshal]::StringToCoTaskMemUni('${vaultTarget.replace(/'/g, "''")}')
$cred.CredentialBlobSize = $blob.Length
$cred.CredentialBlob = [System.Runtime.InteropServices.Marshal]::AllocCoTaskMem($blob.Length)
[System.Runtime.InteropServices.Marshal]::Copy($blob, 0, $cred.CredentialBlob, $blob.Length)
$cred.Persist = 2
if (-not [JpCredWrite]::CredWrite([ref]$cred, 0)) { exit 1 }
`;
  const result = spawnSync("powershell", ["-NoProfile", "-NonInteractive", "-Command", psScript], {
    encoding: "utf8",
    windowsHide: true,
  });
  return result.status === 0;
}

function ensureRolePassword(roleKey) {
  const existing = loadFinal11Password(roleKey);
  if (existing) {
    return { roleKey, status: "present" };
  }
  const targets = FINAL11_VAULT_TARGETS[roleKey];
  if (!targets?.length) {
    return { roleKey, status: "missing_no_vault_target" };
  }
  const password = randomBytes(18).toString("base64url");
  const primaryTarget = targets.find((t) => !t.startsWith("LegacyGeneric")) ?? targets[0];
  if (!writeCredTarget(primaryTarget, password)) {
    return { roleKey, status: "vault_write_fail" };
  }
  if (!loadFinal11Password(roleKey)) {
    return { roleKey, status: "vault_verify_fail" };
  }
  return { roleKey, status: "created" };
}

function buildChildEnv() {
  /** @type {Record<string, string>} */
  const env = { ...process.env };
  for (const [roleKey, envKey] of Object.entries(FINAL11_PASSWORD_ENV_KEYS)) {
    const pw = loadFinal11Password(roleKey);
    if (pw) {
      env[envKey] = pw;
    }
  }
  return env;
}

const syncProduction = process.argv.includes("--sync-production");

for (const roleKey of Object.keys(FINAL11_PASSWORD_ENV_KEYS)) {
  const result = ensureRolePassword(roleKey);
  console.log(`FINAL11_SECRET_${roleKey}=${result.status}`);
  if (result.status === "vault_write_fail" || result.status === "vault_verify_fail") {
    process.exit(1);
  }
}

if (syncProduction) {
  const childEnv = buildChildEnv();
  childEnv.JP_FINAL11_EXECUTE = "1";
  childEnv.JP_EXPECTED_RUNTIME_SHA =
    childEnv.JP_EXPECTED_RUNTIME_SHA || "ab26661f0107d9bd8f9889dda1d59567d93be683";
  const setup = spawnSync("node", [path.join(__dirname, "setup-production-qa.mjs")], {
    cwd: repoRoot,
    encoding: "utf8",
    env: childEnv,
    maxBuffer: 16 * 1024 * 1024,
  });
  process.stdout.write(setup.stdout || "");
  process.stderr.write(setup.stderr || "");
  if (setup.status !== 0) {
    console.error("FINAL11_PRODUCTION_SYNC=FAIL");
    process.exit(setup.status ?? 1);
  }
  console.log("FINAL11_PRODUCTION_SYNC=PASS");
}

const build = spawnSync("node", [path.join(__dirname, "build-auth-states.mjs")], {
  cwd: repoRoot,
  encoding: "utf8",
  env: buildChildEnv(),
  maxBuffer: 16 * 1024 * 1024,
});
process.stdout.write(build.stdout || "");
process.stderr.write(build.stderr || "");
process.exit(build.status ?? 1);

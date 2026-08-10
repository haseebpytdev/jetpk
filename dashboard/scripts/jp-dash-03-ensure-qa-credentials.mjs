/**
 * Generate/store JP-DASH-03 QA credentials in Windows Credential Manager (local only).
 * Never prints passwords. Run once per machine before acceptance:automated-login.
 */
import { spawnSync } from "node:child_process";
import crypto from "node:crypto";
import { QA_CREDENTIAL_ROLES } from "./jp-dash-03-acceptance/credential-vault.mjs";

function generatePassword() {
  return crypto.randomBytes(24).toString("base64url");
}

function credentialExists(target) {
  const ps = `
$ptr = [IntPtr]::Zero
Add-Type @"
using System;
using System.Runtime.InteropServices;
public class JpCredProbe {
  [DllImport("Advapi32.dll", CharSet=CharSet.Unicode, SetLastError=true)]
  public static extern bool CredRead(string target, int type, int reservedFlag, out IntPtr credentialPtr);
  [DllImport("Advapi32.dll")]
  public static extern void CredFree(IntPtr cred);
}
"@
if ([JpCredProbe]::CredRead('${target.replace(/'/g, "''")}', 1, 0, [ref]$ptr)) {
  [JpCredProbe]::CredFree($ptr)
  exit 0
}
exit 1
`;
  const result = spawnSync("powershell", ["-NoProfile", "-NonInteractive", "-Command", ps], {
    encoding: "utf8",
    windowsHide: true,
  });
  return result.status === 0;
}

function storeCredential(target, password) {
  const ps = `
$ErrorActionPreference = 'Stop'
cmdkey /generic:"${target.replace(/"/g, '""')}" /user:"jp-dash-03-qa" /pass:"${password.replace(/"/g, '""')}"
`;
  const result = spawnSync("powershell", ["-NoProfile", "-NonInteractive", "-Command", ps], {
    encoding: "utf8",
    windowsHide: true,
  });
  return result.status === 0;
}

function main() {
  const force = process.argv.includes("--force");
  if (process.platform !== "win32") {
    console.log("QA_CREDENTIAL_VAULT=SKIP_NON_WINDOWS");
    process.exit(0);
  }

  let created = 0;
  let existing = 0;

  for (const [role, config] of Object.entries(QA_CREDENTIAL_ROLES)) {
    const target = config.vaultTargets[1];
    if (credentialExists(target) && !force) {
      existing += 1;
      console.log(`QA_${role.toUpperCase()}_VAULT=exists`);
      continue;
    }

    const password = generatePassword();
    if (!storeCredential(target, password)) {
      console.error(`QA_${role.toUpperCase()}_VAULT=fail`);
      process.exit(1);
    }

    created += 1;
    console.log(`QA_${role.toUpperCase()}_VAULT=created`);
  }

  console.log(`QA_CREDENTIAL_VAULT_SUMMARY=created:${created},existing:${existing}`);
}

main();

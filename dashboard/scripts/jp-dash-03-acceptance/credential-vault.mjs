/**
 * Load JP-DASH-03 QA passwords from env or Windows Credential Manager.
 * Never logs or returns passwords to stdout.
 */
import { spawnSync } from "node:child_process";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";

/** @type {Record<string, { envKey: string, vaultTargets: string[] }>} */
export const QA_CREDENTIAL_ROLES = {
  admin: {
    envKey: "JP_DASH_03_QA_ADMIN_PASSWORD",
    vaultTargets: [
      "LegacyGeneric:target=JetPakistan-JP-DASH-03-QA-Admin",
      "JetPakistan-JP-DASH-03-QA-Admin",
    ],
  },
  staff: {
    envKey: "JP_DASH_03_QA_STAFF_PASSWORD",
    vaultTargets: [
      "LegacyGeneric:target=JetPakistan-JP-DASH-03-QA-Staff",
      "JetPakistan-JP-DASH-03-QA-Staff",
    ],
  },
  agent: {
    envKey: "JP_DASH_03_QA_AGENT_PASSWORD",
    vaultTargets: [
      "LegacyGeneric:target=JetPakistan-JP-DASH-03-QA-Agent",
      "JetPakistan-JP-DASH-03-QA-Agent",
    ],
  },
  customer: {
    envKey: "JP_DASH_03_QA_CUSTOMER_PASSWORD",
    vaultTargets: [
      "LegacyGeneric:target=JetPakistan-JP-DASH-03-QA-Customer",
      "JetPakistan-JP-DASH-03-QA-Customer",
    ],
  },
};

/**
 * @param {"admin"|"staff"|"agent"|"customer"} role
 * @returns {string|null}
 */
export function loadQaPasswordFromVault(role) {
  const config = QA_CREDENTIAL_ROLES[role];
  if (!config) {
    return null;
  }

  if (process.env[config.envKey]) {
    return process.env[config.envKey];
  }

  if (process.platform !== "win32") {
    return null;
  }

  for (const target of config.vaultTargets) {
    const password = retrieveWithCredRead(target);
    if (password) {
      return password;
    }
  }

  return null;
}

/** @deprecated use loadQaPasswordFromVault("staff") */
export function loadQaStaffPasswordFromVault() {
  return loadQaPasswordFromVault("staff");
}

function retrieveWithCredRead(vaultTarget) {
  const tempFile = path.join(os.tmpdir(), `jp-dash-03-qa-pw-${process.pid}.tmp`);
  const psScript = `
$ErrorActionPreference = 'Stop'
Add-Type @"
using System;
using System.Runtime.InteropServices;
public class JpCredRead {
  [StructLayout(LayoutKind.Sequential, CharSet=CharSet.Unicode)]
  public struct NativeCredential {
    public int Flags;
    public int Type;
    public IntPtr TargetName;
    public IntPtr Comment;
    public System.Runtime.InteropServices.ComTypes.FILETIME LastWritten;
    public int CredentialBlobSize;
    public IntPtr CredentialBlob;
    public int Persist;
    public int Attribute;
    public IntPtr TargetAlias;
    public IntPtr UserName;
  }
  [DllImport("Advapi32.dll", CharSet=CharSet.Unicode, SetLastError=true)]
  public static extern bool CredRead(string target, int type, int reservedFlag, out IntPtr credentialPtr);
  [DllImport("Advapi32.dll")]
  public static extern void CredFree(IntPtr cred);
}
"@
$target = '${vaultTarget.replace(/'/g, "''")}'
$ptr = [IntPtr]::Zero
if (-not [JpCredRead]::CredRead($target, 1, 0, [ref]$ptr)) { exit 2 }
$cred = [Runtime.InteropServices.Marshal]::PtrToStructure($ptr, [Type][JpCredRead+NativeCredential])
$size = $cred.CredentialBlobSize
$bytes = New-Object byte[] $size
[Runtime.InteropServices.Marshal]::Copy($cred.CredentialBlob, $bytes, 0, $size)
[JpCredRead]::CredFree($ptr)
if ($size % 2 -eq 0 -and $size -gt 0) {
  $text = [System.Text.Encoding]::Unicode.GetString($bytes).Trim([char]0)
  $outBytes = [System.Text.Encoding]::UTF8.GetBytes($text)
} else {
  $outBytes = $bytes
}
$enc = [Convert]::ToBase64String($outBytes)
Set-Content -Path '${tempFile.replace(/\\/g, "\\\\")}' -Value $enc -NoNewline -Encoding ASCII
`;

  const result = spawnSync(
    "powershell",
    ["-NoProfile", "-NonInteractive", "-Command", psScript],
    { encoding: "utf8", windowsHide: true },
  );

  if (result.status !== 0 || !fs.existsSync(tempFile)) {
    try {
      fs.unlinkSync(tempFile);
    } catch {
      /* ignore */
    }
    return null;
  }

  try {
    const encoded = fs.readFileSync(tempFile, "utf8").trim();
    const password = Buffer.from(encoded, "base64").toString("utf8");
    return password.length > 0 ? password : null;
  } finally {
    try {
      fs.unlinkSync(tempFile);
    } catch {
      /* ignore */
    }
  }
}

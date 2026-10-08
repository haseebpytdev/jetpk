/**
 * Ensure agent staff QA password exists in vault; generate + store if missing.
 * Never prints the password.
 */
import { randomBytes } from "node:crypto";
import { spawnSync } from "node:child_process";
import { loadQaPasswordFromVault } from "../jp-dash-03-acceptance/credential-vault.mjs";

export function ensureAgentStaffVaultPassword() {
  const existing = loadQaPasswordFromVault("agentStaff");
  if (existing) {
    return "present";
  }

  const password = randomBytes(18).toString("base64url");
  const target = "JetPakistan-JP-DASH-03-QA-Agent-Staff";
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
$cred.TargetName = [System.Runtime.InteropServices.Marshal]::StringToCoTaskMemUni('${target.replace(/'/g, "''")}')
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

  if (result.status !== 0) {
    throw new Error("AGENT_STAFF_VAULT_WRITE_FAIL");
  }

  if (!loadQaPasswordFromVault("agentStaff")) {
    throw new Error("AGENT_STAFF_VAULT_VERIFY_FAIL");
  }

  return "created";
}

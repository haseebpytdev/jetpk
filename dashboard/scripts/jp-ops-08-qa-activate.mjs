/**
 * JP-OPS-08: reactivate dedicated QA identities using vault passwords (never printed).
 * Uses existing jetpk:dash-03-qa-* commands only.
 */
import { spawnSync } from "node:child_process";
import os from "node:os";
import path from "node:path";
import { loadQaPasswordFromVault } from "./jp-dash-03-acceptance/credential-vault.mjs";

const sshKey = path.join(os.homedir(), ".ssh", "jetpk_contabo_2026_v2");
const remote = "pkjetp@185.215.166.176";
const appRoot = "/home/pkjetp/jetpk_app";
const php = "/usr/local/lsws/lsphp83/bin/lsphp";

function runRemote(envAssignments, artisanArgs) {
  const envPrefix = envAssignments.map(([k, v]) => `${k}='${v.replace(/'/g, `'\\''`)}'`).join(" ");
  const cmd = `cd ${appRoot} && ${envPrefix} ${php} artisan ${artisanArgs}`;
  const result = spawnSync(
    "ssh",
    ["-i", sshKey, "-o", "IdentitiesOnly=yes", "-o", "ConnectTimeout=20", remote, cmd],
    { encoding: "utf8", windowsHide: true },
  );
  if (result.stdout) process.stdout.write(result.stdout.replace(/JP_DASH_03_QA_[A-Z_]*PASSWORD=.*/g, "[redacted]"));
  if (result.stderr) process.stderr.write(result.stderr.replace(/JP_DASH_03_QA_[A-Z_]*PASSWORD=.*/g, "[redacted]"));
  return result.status ?? 1;
}

const adminPw = loadQaPasswordFromVault("admin");
const staffPw = loadQaPasswordFromVault("staff");
const agentPw = loadQaPasswordFromVault("agent");
const customerPw = loadQaPasswordFromVault("customer");

if (!adminPw || !staffPw || !agentPw || !customerPw) {
  console.error("QA_ACTIVATE=missing_vault_password");
  process.exit(1);
}

// Upload latest artisan command file first is caller's responsibility.
let exit = 0;
exit |= runRemote(
  [
    ["JP_DASH_03_QA_ADMIN_PASSWORD", adminPw],
    ["JP_DASH_03_QA_AGENT_PASSWORD", agentPw],
    ["JP_DASH_03_QA_CUSTOMER_PASSWORD", customerPw],
  ],
  "jetpk:dash-03-qa-identities all activate",
);
exit |= runRemote(
  [
    ["JP_DASH_03_QA_ADMIN_PASSWORD", adminPw],
    ["JP_DASH_03_QA_AGENT_PASSWORD", agentPw],
    ["JP_DASH_03_QA_CUSTOMER_PASSWORD", customerPw],
  ],
  "jetpk:dash-03-qa-identities all rotate-password",
);
exit |= runRemote([["JP_DASH_03_QA_STAFF_PASSWORD", staffPw]], "jetpk:dash-03-qa-staff restore-baseline");
exit |= runRemote([["JP_DASH_03_QA_STAFF_PASSWORD", staffPw]], "jetpk:dash-03-qa-staff rotate-password");
exit |= runRemote([], "jetpk:dash-03-qa-identities all status");
exit |= runRemote([], "jetpk:dash-03-qa-staff status");

if (exit !== 0) {
  console.error("QA_ACTIVATE=FAIL");
  process.exit(1);
}
console.log("QA_ACTIVATE=PASS");

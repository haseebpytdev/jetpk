/**
 * JP-DASH-PROD-01 — reconcile production QA context on server.
 * Never logs passwords. Uses credential vault + stdin password sync.
 */
import { spawnSync } from "node:child_process";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { loadQaPasswordFromVault } from "../jp-dash-03-acceptance/credential-vault.mjs";
import { ensureAgentStaffVaultPassword } from "./ensure-agent-staff-vault.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const sshKey = process.env.JP_SSH_KEY || path.join(os.homedir(), ".ssh", "jetpk_contabo_2026_v2");
const sshHost = process.env.JP_SSH_HOST || "pkjetp@185.215.166.176";
const scpHost = process.env.JP_SCP_HOST || "root@185.215.166.176";
const appRoot = process.env.JP_APP_ROOT || "/home/pkjetp/jetpk_app";
const phpBin = process.env.JP_PHP_BIN || "/usr/local/lsws/lsphp83/bin/php";

function ssh(cmd, input = null) {
  const args = ["-i", sshKey, "-o", "BatchMode=yes", sshHost, cmd];
  const result = spawnSync("ssh", args, {
    encoding: "utf8",
    input,
    maxBuffer: 8 * 1024 * 1024,
  });
  return result;
}

function scp(local, remote) {
  return spawnSync("scp", ["-i", sshKey, "-o", "BatchMode=yes", local, `${scpHost}:${remote}`], {
    encoding: "utf8",
    maxBuffer: 8 * 1024 * 1024,
  });
}

function artisan(args, stdin = null) {
  const cmd = `cd ${appRoot} && ${phpBin} artisan ${args}`;
  return ssh(cmd, stdin);
}

const roles = ["admin", "staff", "agent", "agent_staff", "customer"];

console.log("JP_DASH_PROD_CERT_SETUP=START");
console.log(`AGENT_STAFF_VAULT=${ensureAgentStaffVaultPassword()}`);

const commandLocal = path.join(repoRoot, "app/Console/Commands/JetpkDashboardProdCertQaCommand.php");
const commandRemote = `${appRoot}/app/Console/Commands/JetpkDashboardProdCertQaCommand.php`;
const scpResult = scp(commandLocal, commandRemote);
if (scpResult.status !== 0) {
  console.error("COMMAND_UPLOAD=FAIL");
  process.exit(1);
}
console.log("COMMAND_UPLOAD=PASS");

let reconcile = artisan("jetpk:dashboard-prod-cert-qa reconcile");
process.stdout.write(reconcile.stdout || "");
if (reconcile.status !== 0) {
  console.error(reconcile.stderr || "");
  console.error("QA_RECONCILE=FAIL");
  process.exit(1);
}
console.log("QA_RECONCILE=PASS");

for (const role of roles) {
  const vaultRole = role === "agent_staff" ? "agentStaff" : role;
  const password = loadQaPasswordFromVault(vaultRole);
  if (!password) {
    console.error(`PASSWORD_VAULT_MISSING=${role}`);
    process.exit(1);
  }
  const syncRole = role;
  const sync = artisan(`jetpk:dashboard-prod-cert-qa sync-password --role=${syncRole}`, password);
  process.stdout.write(sync.stdout || "");
  if (sync.status !== 0 || !String(sync.stdout).includes("PASSWORD_SYNC=PASS")) {
    console.error(sync.stderr || "");
    console.error(`PASSWORD_SYNC_FAIL=${syncRole}`);
    process.exit(1);
  }
  console.log(`PASSWORD_SYNC_${syncRole}=PASS`);
}

const activate = artisan("jetpk:dashboard-prod-cert-qa activate-all");
process.stdout.write(activate.stdout || "");
console.log("QA_ACTIVATE_ALL=PASS");

const fixtures = artisan("jetpk:dashboard-prod-cert-qa create-fixtures");
process.stdout.write(fixtures.stdout || "");
if (fixtures.status !== 0) {
  console.error(fixtures.stderr || "");
  console.error("QA_FIXTURES=FAIL");
  process.exit(1);
}
console.log("QA_FIXTURES=PASS");

const status = artisan("jetpk:dashboard-prod-cert-qa status");
process.stdout.write(status.stdout || "");
console.log("JP_DASH_PROD_CERT_SETUP=PASS");

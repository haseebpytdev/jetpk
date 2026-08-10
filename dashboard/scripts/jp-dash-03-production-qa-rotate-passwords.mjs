/**
 * Rotate production QA passwords to match local vault (sanitized output).
 */
import { spawnSync } from "node:child_process";
import os from "node:os";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { loadQaPasswordFromVault } from "./jp-dash-03-acceptance/credential-vault.mjs";

const sshKey = path.join(os.homedir(), ".ssh", "jetpk_contabo_2026_v2");
const remote = "root@185.215.166.176";
const appRoot = "/home/pkjetp/jetpk_app";
const php = "/usr/local/lsws/lsphp83/bin/php";

const roles = [
  { role: "admin", artisan: "jetpk:dash-03-qa-identities admin rotate-password", env: "JP_DASH_03_QA_ADMIN_PASSWORD" },
  { role: "staff", artisan: "jetpk:dash-03-qa-staff rotate-password", env: "JP_DASH_03_QA_STAFF_PASSWORD" },
  { role: "agent", artisan: "jetpk:dash-03-qa-identities agent rotate-password", env: "JP_DASH_03_QA_AGENT_PASSWORD" },
  { role: "customer", artisan: "jetpk:dash-03-qa-identities customer rotate-password", env: "JP_DASH_03_QA_CUSTOMER_PASSWORD" },
];

for (const entry of roles) {
  const password = loadQaPasswordFromVault(entry.role);
  if (!password) {
    console.error(`QA_${entry.role.toUpperCase()}_ROTATE=missing_password`);
    process.exit(1);
  }

  const cmd = `cd ${appRoot} && ${entry.env}='${password.replace(/'/g, "'\\''")}' ${php} artisan ${entry.artisan}`;
  const result = spawnSync(
    "ssh",
    ["-F", "NUL", "-i", sshKey, "-p", "22", "-o", "IdentitiesOnly=yes", remote, cmd],
    { encoding: "utf8", windowsHide: true },
  );
  if (result.stdout) process.stdout.write(result.stdout);
  if (result.status !== 0) {
    console.error(`QA_${entry.role.toUpperCase()}_ROTATE=FAIL`);
    process.exit(1);
  }
  console.log(`QA_${entry.role.toUpperCase()}_ROTATE=PASS`);
}

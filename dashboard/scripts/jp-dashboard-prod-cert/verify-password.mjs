import { spawnSync } from "node:child_process";
import os from "node:os";
import path from "node:path";
import { loadQaPasswordFromVault } from "../jp-dash-03-acceptance/credential-vault.mjs";

const role = process.argv[2] || "admin";
const email = process.argv[3] || `jp-dash-03-qa-${role.replace("_", "-")}@jetpakistan.pk`;
const pw = loadQaPasswordFromVault(role === "agent_staff" ? "agentStaff" : role);
const sshKey = path.join(os.homedir(), ".ssh", "jetpk_contabo_2026_v2");
const remote = `cd /home/pkjetp/jetpk_app && /usr/local/lsws/lsphp83/bin/php artisan jetpk:dashboard-prod-cert-qa sync-password --role=${role}`;
const sync = spawnSync("ssh", ["-i", sshKey, "-o", "BatchMode=yes", "pkjetp@185.215.166.176", remote], {
  input: pw,
  encoding: "utf8",
});
console.log(sync.stdout || "");
if (!String(sync.stdout).includes("PASSWORD_SYNC=PASS")) {
  console.error("SYNC_FAIL");
  process.exit(1);
}
console.log("VERIFY_SYNC=PASS");

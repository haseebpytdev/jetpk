import { spawnSync } from "node:child_process";
import os from "node:os";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { loadQaPasswordFromVault } from "../../../dashboard/scripts/jp-dash-03-acceptance/credential-vault.mjs";

const password = loadQaPasswordFromVault("admin");
if (!password) {
  console.log("QA_ADMIN_ROTATE=missing_password");
  process.exit(1);
}
const sshKey = path.join(os.homedir(), ".ssh", "jetpk_contabo_2026_v2");
const remote = "root@185.215.166.176";
const appRoot = "/home/pkjetp/jetpk_app";
const php = "/usr/local/lsws/lsphp83/bin/php";
const cmd = `cd ${appRoot} && JP_DASH_03_QA_ADMIN_PASSWORD='${password.replace(/'/g, "'\\''")}' ${php} artisan jetpk:dash-03-qa-identities admin rotate-password`;
const result = spawnSync("ssh", ["-F", "NUL", "-i", sshKey, "-p", "22", "-o", "IdentitiesOnly=yes", remote, cmd], {
  encoding: "utf8",
  windowsHide: true,
});
if (result.stdout) process.stdout.write(result.stdout);
if (result.stderr) process.stderr.write(result.stderr);
console.log(`QA_ADMIN_ROTATE=${result.status === 0 ? "PASS" : "FAIL"}`);
process.exit(result.status ?? 1);

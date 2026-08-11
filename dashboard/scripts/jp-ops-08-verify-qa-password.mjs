import { spawnSync } from "node:child_process";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { loadQaPasswordFromVault } from "./jp-dash-03-acceptance/credential-vault.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const sshKey = path.join(os.homedir(), ".ssh", "jetpk_contabo_2026_v2");
const remote = "pkjetp@185.215.166.176";
const app = "/home/pkjetp/jetpk_app";
const php = "/usr/local/lsws/lsphp83/bin/lsphp";
const localPhp = path.resolve(__dirname, "../../tmp/jp-ops-08-hash-check.php");

spawnSync("scp", ["-i", sshKey, localPhp, `${remote}:${app}/tmp-hash-check.php`], {
  encoding: "utf8",
  windowsHide: true,
});

const roles = [
  ["admin", "jp-dash-03-qa-admin@jetpakistan.pk"],
  ["staff", "jp-dash-03-qa-staff@jetpakistan.pk"],
];

for (const [role, email] of roles) {
  const password = loadQaPasswordFromVault(role);
  if (!password) {
    console.log(`QA_${role.toUpperCase()}_HASH_CHECK=missing_password`);
    continue;
  }
  const cmd = `cd ${app} && CHECK_EMAIL='${email}' CHECK_PW='${password.replace(/'/g, `'\\''`)}' ${php} tmp-hash-check.php`;
  const result = spawnSync("ssh", ["-i", sshKey, "-o", "IdentitiesOnly=yes", remote, cmd], {
    encoding: "utf8",
    windowsHide: true,
  });
  console.log(`QA_${role.toUpperCase()}_HASH_CHECK`);
  process.stdout.write((result.stdout || "").replace(/CHECK_PW=.*/g, "[redacted]"));
  if (result.status !== 0) {
    process.stderr.write((result.stderr || "").slice(0, 300));
  }
}

spawnSync("ssh", ["-i", sshKey, remote, `rm -f ${app}/tmp-hash-check.php`], {
  encoding: "utf8",
  windowsHide: true,
});

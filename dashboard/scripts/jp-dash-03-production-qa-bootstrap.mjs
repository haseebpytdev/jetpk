/**
 * Production QA bootstrap: OTP-off + create QA identities (sanitized output only).
 * Reads passwords from local vault — never prints them.
 */
import { spawnSync } from "node:child_process";
import os from "node:os";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { loadQaPasswordFromVault } from "./jp-dash-03-acceptance/credential-vault.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const sshKey = path.join(os.homedir(), ".ssh", "jetpk_contabo_2026_v2");
const remote = "root@185.215.166.176";
const appRoot = "/home/pkjetp/jetpk_app";
const php = "/usr/local/lsws/lsphp83/bin/php";

function ssh(command, env = {}) {
  const mergedEnv = { ...process.env, ...env };
  const result = spawnSync(
    "ssh",
    ["-F", "NUL", "-i", sshKey, "-p", "22", "-o", "IdentitiesOnly=yes", remote, command],
    { encoding: "utf8", env: mergedEnv, windowsHide: true },
  );
  if (result.stdout) {
    process.stdout.write(result.stdout);
  }
  if (result.stderr) {
    process.stderr.write(result.stderr);
  }
  return result.status ?? 1;
}

function main() {
  const roles = ["admin", "staff", "agent", "customer"];
  const passwords = {};
  for (const role of roles) {
    const password = loadQaPasswordFromVault(role);
    if (!password) {
      console.error(`QA_${role.toUpperCase()}_PASSWORD=missing`);
      process.exit(1);
    }
    passwords[role] = password;
  }

  console.log("OTP_QA_MODE_ACTIVE=yes");
  console.log("OTP_ORIGINAL_REQUIREMENT=true");

  const envFileCmd = `
if grep -q '^OTA_CLIENT_REQUIRE_LOGIN_OTP=' ${appRoot}/.env; then
  sed -i 's/^OTA_CLIENT_REQUIRE_LOGIN_OTP=.*/OTA_CLIENT_REQUIRE_LOGIN_OTP=false/' ${appRoot}/.env
else
  echo 'OTA_CLIENT_REQUIRE_LOGIN_OTP=false' >> ${appRoot}/.env
fi
cd ${appRoot} && ${php} artisan config:clear && ${php} artisan config:cache
${php} artisan tinker --execute="echo 'OTP_REQUIRED=' . (\\App\\Support\\Auth\\ClientLoginOtpGate::isRequired() ? 'yes' : 'no');"
`;
  if (ssh(envFileCmd) !== 0) {
    console.error("OTP_QA_DEPLOY=FAIL");
    process.exit(1);
  }
  console.log("OTP_QA_DEPLOY=PASS");

  const identityCmd = `
cd ${appRoot} && \\
JP_DASH_03_QA_ADMIN_PASSWORD='${passwords.admin.replace(/'/g, "'\\''")}' \\
JP_DASH_03_QA_STAFF_PASSWORD='${passwords.staff.replace(/'/g, "'\\''")}' \\
JP_DASH_03_QA_AGENT_PASSWORD='${passwords.agent.replace(/'/g, "'\\''")}' \\
JP_DASH_03_QA_CUSTOMER_PASSWORD='${passwords.customer.replace(/'/g, "'\\''")}' \\
${php} artisan jetpk:dash-03-qa-identities all create && \\
JP_DASH_03_QA_STAFF_PASSWORD='${passwords.staff.replace(/'/g, "'\\''")}' \\
${php} artisan jetpk:dash-03-qa-staff create
`;
  if (ssh(identityCmd) !== 0) {
    console.error("QA_IDENTITIES_DEPLOY=FAIL");
    process.exit(1);
  }
  console.log("QA_IDENTITIES_DEPLOY=PASS");
}

main();

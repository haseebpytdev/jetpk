/**
 * JP-DASH-FINAL-11 production QA provisioning driver (workstation).
 * Default: dry-run (no SSH mutations). Set JP_FINAL11_EXECUTE=1 to run reconcile on production.
 * Never uploads runtime PHP — command must exist after protected deploy of merged SHA.
 * Never logs passwords.
 */
import { spawnSync } from "node:child_process";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const sshKey = process.env.JP_SSH_KEY || path.join(os.homedir(), ".ssh", "jetpk_contabo_2026_v2");
const sshHost = process.env.JP_SSH_HOST || "pkjetp@185.215.166.176";
const appRoot = process.env.JP_APP_ROOT || "/home/pkjetp/jetpk_app";
const phpBin = process.env.JP_PHP_BIN || "/usr/local/lsws/lsphp83/bin/php";
const execute = process.env.JP_FINAL11_EXECUTE === "1";

/** @type {Record<string, string>} */
const ROLE_ENV_KEYS = {
  customer_b: "JP_FINAL_11_QA_CUSTOMER_B_PASSWORD",
  agent_b: "JP_FINAL_11_QA_AGENT_B_PASSWORD",
  agent_staff_manager: "JP_FINAL_11_QA_STAFF_MANAGER_PASSWORD",
  agent_staff_accountant: "JP_FINAL_11_QA_STAFF_ACCOUNTANT_PASSWORD",
  agent_staff_sales: "JP_FINAL_11_QA_STAFF_SALES_PASSWORD",
  agent_staff_support: "JP_FINAL_11_QA_STAFF_SUPPORT_PASSWORD",
  agent_staff_ticketing: "JP_FINAL_11_QA_STAFF_TICKETING_PASSWORD",
  agent_staff_viewer: "JP_FINAL_11_QA_STAFF_VIEWER_PASSWORD",
  legacy_agency_admin: "JP_FINAL_11_QA_LEGACY_AGENCY_ADMIN_PASSWORD",
};

function ssh(cmd, input = null) {
  return spawnSync("ssh", ["-i", sshKey, "-o", "BatchMode=yes", sshHost, cmd], {
    encoding: "utf8",
    input,
    maxBuffer: 8 * 1024 * 1024,
  });
}

function artisan(args, stdin = null) {
  const cmd = `cd ${appRoot} && ${phpBin} artisan ${args}`;
  return ssh(cmd, stdin);
}

function resolvePassword(envKey) {
  const value = process.env[envKey]?.trim();
  return value ? value : null;
}

function assertLocalSecrets() {
  for (const envKey of Object.values(ROLE_ENV_KEYS)) {
    if (!resolvePassword(envKey)) {
      console.error(`FINAL11_MISSING_ENV=${envKey}`);
      process.exit(1);
    }
  }
  console.log("FINAL11_LOCAL_SECRETS=PASS");
}

function verifyRemoteCommandSupport() {
  const remoteCommand = `${appRoot}/app/Console/Commands/JetpkDashboardFinal11QaCommand.php`;
  const probe = ssh(
    `test -f ${remoteCommand} && ${phpBin} ${appRoot}/artisan list --raw | grep -F jetpk:dashboard-final-11-qa`,
  );
  if (probe.status !== 0 || !String(probe.stdout).includes("jetpk:dashboard-final-11-qa")) {
    console.error("FINAL11_REMOTE_COMMAND=MISSING");
    console.error("FINAL11_PROTECTED_DEPLOYMENT_REQUIRED=YES");
    process.exit(1);
  }
  console.log("FINAL11_REMOTE_COMMAND=PASS");
}

function verifyProductionRuntimeSha() {
  const expected = (process.env.JP_EXPECTED_RUNTIME_SHA || process.env.JP_PRODUCTION_SHA || "").trim().toLowerCase();
  if (!expected) {
    console.error("FINAL11_RUNTIME_SHA_CHECK=EXPECTED_REQUIRED");
    process.exit(1);
  }
  if (!/^[0-9a-f]{40}$/.test(expected)) {
    console.error("FINAL11_RUNTIME_SHA_CHECK=INVALID_EXPECTED");
    process.exit(1);
  }
  const probe = ssh(
    `(test -f ${appRoot}/.jetpk-runtime-sha && cat ${appRoot}/.jetpk-runtime-sha) || (test -f ${appRoot}/storage/app/deploy-sha.txt && cat ${appRoot}/storage/app/deploy-sha.txt) || echo MISSING`,
  );
  const runtime = String(probe.stdout || "").trim().toLowerCase();
  if (runtime !== expected) {
    console.error("FINAL11_RUNTIME_SHA_MISMATCH=YES");
    console.error(`FINAL11_EXPECTED_RUNTIME_SHA=${expected}`);
    console.error(`FINAL11_ACTUAL_RUNTIME_SHA=${runtime === "missing" ? "MISSING" : runtime}`);
    process.exit(1);
  }
  console.log("FINAL11_RUNTIME_SHA_CHECK=PASS");
}

function syncPasswordsViaStdin() {
  for (const [roleKey, envKey] of Object.entries(ROLE_ENV_KEYS)) {
    const password = resolvePassword(envKey);
    if (!password) {
      console.error(`FINAL11_MISSING_ENV=${envKey}`);
      process.exit(1);
    }
    const sync = artisan(
      `jetpk:dashboard-final-11-qa sync-password --role=${roleKey} --execute`,
      password,
    );
    process.stdout.write(sync.stdout || "");
    if (sync.status !== 0 || !String(sync.stdout).includes("FINAL11_PASSWORD_SYNC=PASS")) {
      console.error(`FINAL11_PASSWORD_SYNC_FAIL=${roleKey}`);
      process.exit(1);
    }
  }
  console.log("FINAL11_PASSWORD_SYNC_ALL=PASS");
}

function runReconcile() {
  const reconcile = artisan("jetpk:dashboard-final-11-qa reconcile --execute");
  process.stdout.write(reconcile.stdout || "");
  if (reconcile.status !== 0 || !String(reconcile.stdout).includes("FINAL11_QA_RECONCILE=PASS")) {
    console.error("FINAL11_QA_RECONCILE=FAIL");
    process.exit(1);
  }
  console.log("FINAL11_QA_RECONCILE=PASS");
}

function emitStatus() {
  const status = artisan("jetpk:dashboard-final-11-qa status");
  process.stdout.write(status.stdout || "");
  if (status.status !== 0) {
    console.error("FINAL11_STATUS=FAIL");
    process.exit(1);
  }
  console.log("FINAL11_STATUS=PASS");
}

function buildAuthStatesIfAvailable() {
  const buildScript = path.join(repoRoot, "dashboard/scripts/jp-dashboard-prod-cert/build-auth-states.mjs");
  if (!fs.existsSync(buildScript)) {
    console.log("FINAL11_AUTH_STATES=SKIPPED_NO_BUILDER");
    return;
  }
  const result = spawnSync("node", [buildScript], {
    cwd: repoRoot,
    encoding: "utf8",
    env: process.env,
    maxBuffer: 8 * 1024 * 1024,
  });
  if (result.status !== 0) {
    console.error("FINAL11_AUTH_STATES=FAIL");
    process.exit(1);
  }
  console.log("FINAL11_AUTH_STATES=PASS");
}

console.log("JP_DASH_FINAL_11_QA_SETUP=START");
console.log(`FINAL11_EXECUTE_MODE=${execute ? "production" : "dry_run"}`);
console.log("FINAL11_DIRECT_RUNTIME_SCP=NO");
console.log("FINAL11_PROTECTED_DEPLOYMENT_REQUIRED=YES");
console.log("PASSWORD_TRANSPORT=STDIN_ONLY");
console.log("SERVER_ENV_PASSWORDS_REQUIRED=NO");

assertLocalSecrets();

if (!execute) {
  console.log("FINAL11_SETUP_DRY_RUN=PASS");
  console.log("JP_DASH_FINAL_11_QA_SETUP=PASS");
  process.exit(0);
}

verifyProductionRuntimeSha();
verifyRemoteCommandSupport();
runReconcile();
syncPasswordsViaStdin();
emitStatus();
buildAuthStatesIfAvailable();
console.log("JP_DASH_FINAL_11_QA_SETUP=PASS");

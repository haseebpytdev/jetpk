/**
 * DEPRECATED — do not use for canary UAT.
 * Case 28+ resilience uses request-scoped fault headers via canary-matrix-helpers.mjs.
 * This script previously stopped jetpk-ai-lab-gateway globally and caused cross-case outages.
 */
import { spawnSync } from "node:child_process";
import os from "node:os";
import path from "node:path";

const action = process.argv[2];
if (action === "stop") {
  console.error("DEPRECATED: global gateway stop disabled. Use SIMULATE_GATEWAY_DOWN fault headers.");
  process.exit(2);
}
const sshKey = path.join(os.homedir(), ".ssh", "jetpk_contabo_2026_v2");
const host = "root@185.215.166.176";
const service = "jetpk-ai-lab-gateway.service";

function ssh(command) {
  const result = spawnSync(
    "ssh",
    ["-i", sshKey, "-o", "BatchMode=yes", host, command],
    { encoding: "utf8", shell: process.platform === "win32" },
  );
  if (result.status !== 0) {
    console.error(result.stderr || result.stdout);
    process.exit(result.status ?? 1);
  }
  return result.stdout;
}

if (action === "stop") {
  ssh(`systemctl stop ${service}`);
  console.log("GATEWAY_STOPPED");
} else if (action === "start") {
  ssh(`systemctl start ${service}`);
  ssh(`curl -sf http://127.0.0.1:8765/health`);
  console.log("GATEWAY_STARTED");
} else if (action === "status") {
  console.log(ssh(`systemctl is-active ${service}`).trim());
} else {
  console.error("Usage: node canary-gateway-control.mjs stop|start|status");
  process.exit(2);
}

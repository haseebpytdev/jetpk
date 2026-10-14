import fs from "node:fs";
import os from "node:os";
import { spawnSync } from "node:child_process";
import { writeFileAtomic } from "./functional-cert-lock.mjs";
import { sanitizeCertificationError } from "./responsive-cert-sanitize.mjs";

function processAlive(pid) {
  if (!pid || !Number.isFinite(pid)) return false;
  try {
    process.kill(pid, 0);
    return true;
  } catch {
    return false;
  }
}

function commandLineLooksLikeResponsiveSweep(cmd) {
  return /final-responsive-sweep\.mjs|run-responsive-cert-selftests\.mjs/.test(cmd || "");
}

function readLock(lockPath) {
  if (!fs.existsSync(lockPath)) return null;
  try {
    return JSON.parse(fs.readFileSync(lockPath, "utf8"));
  } catch {
    return null;
  }
}

export function acquireResponsiveSweepLock(lockPath, runMeta) {
  const existing = readLock(lockPath);
  if (existing?.PID) {
    const alive = processAlive(existing.PID);
    if (alive) {
      const ps = spawnSync(
        "powershell",
        [
          "-NoProfile",
          "-Command",
          `(Get-CimInstance Win32_Process -Filter "ProcessId=${existing.PID}" -ErrorAction SilentlyContinue).CommandLine`,
        ],
        { encoding: "utf8", timeout: 15_000 },
      );
      const cmd = String(ps.stdout || "");
      if (commandLineLooksLikeResponsiveSweep(cmd)) {
        return { ok: false, reason: "ACTIVE_SWEEP", lock: existing };
      }
    }
    try {
      fs.unlinkSync(lockPath);
    } catch {
      /* stale */
    }
  }
  const payload = {
    PID: process.pid,
    PARENT_PID: process.ppid,
    STARTED_AT: new Date().toISOString(),
    RUN_ID: runMeta.runId,
    RUN_FINGERPRINT: runMeta.fingerprint,
    HOSTNAME: os.hostname(),
    LAST_HEARTBEAT_AT: new Date().toISOString(),
  };
  writeFileAtomic(lockPath, `${JSON.stringify(payload, null, 2)}\n`);
  return { ok: true, lock: payload };
}

export function touchResponsiveLockHeartbeat(lockPath) {
  const lock = readLock(lockPath);
  if (!lock || lock.PID !== process.pid) return;
  lock.LAST_HEARTBEAT_AT = new Date().toISOString();
  writeFileAtomic(lockPath, `${JSON.stringify(lock, null, 2)}\n`);
}

export function releaseResponsiveSweepLock(lockPath) {
  const lock = readLock(lockPath);
  if (!lock) return;
  if (lock.PID !== process.pid) return;
  try {
    fs.unlinkSync(lockPath);
  } catch {
    /* ignore */
  }
}

export function refuseSecondSweepMessage(lock) {
  return sanitizeCertificationError(
    `SECOND_RESPONSIVE_SWEEP_START=REFUSED_WHILE_ACTIVE holder_pid=${lock?.PID ?? "unknown"}`,
  );
}

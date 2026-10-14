import fs from "node:fs";
import { writeFileAtomic } from "./functional-cert-lock.mjs";
import { sanitizeForJson } from "./responsive-cert-sanitize.mjs";
import { touchResponsiveLockHeartbeat } from "./responsive-cert-lock.mjs";

export const CHECKPOINT_SCHEMA_VERSION = 1;

export function emptyCheckpoint(runMeta) {
  return {
    SCHEMA_VERSION: CHECKPOINT_SCHEMA_VERSION,
    RUN_ID: runMeta.runId,
    RUN_FINGERPRINT: runMeta.fingerprint,
    PLAN_ROWS_TOTAL: runMeta.planRowsTotal,
    ROWS_COMPLETED: 0,
    ROWS_REMAINING: runMeta.planRowsTotal,
    STARTED_AT: new Date().toISOString(),
    UPDATED_AT: new Date().toISOString(),
    LAST_COMPLETED_INDEX: 0,
    LAST_COMPLETED_CASE_KEY: "",
    CURRENT_CASE_KEY: "",
    SESSION_REFRESHES: 0,
    NETWORK_RETRIES: 0,
    TIMEOUT_RETRIES: 0,
    ROWS: {},
  };
}

export function loadCheckpoint(checkpointPath) {
  if (!fs.existsSync(checkpointPath)) return null;
  return JSON.parse(fs.readFileSync(checkpointPath, "utf8"));
}

export function isCompletedRow(row) {
  return row?.COMPLETED === "YES" && row.CASE_KEY && row.DISPOSITION && row.RESULT_TIMESTAMP;
}

export function saveCheckpointAtomic(checkpointPath, checkpoint) {
  checkpoint.UPDATED_AT = new Date().toISOString();
  writeFileAtomic(checkpointPath, `${JSON.stringify(sanitizeForJson(checkpoint), null, 2)}\n`);
}

export function upsertCompletedRow(checkpoint, planRow, result, counters) {
  const key = planRow.CASE_KEY;
  checkpoint.ROWS[key] = sanitizeForJson({
    ...result,
    INDEX: planRow.INDEX,
    ROLE_KEY: planRow.ROLE_KEY,
    ROUTE: planRow.ROUTE,
    VIEWPORT: planRow.VIEWPORT,
    CASE_KEY: key,
    COMPLETED: "YES",
    RESULT_TIMESTAMP: new Date().toISOString(),
  });
  checkpoint.ROWS_COMPLETED = Object.values(checkpoint.ROWS).filter(isCompletedRow).length;
  checkpoint.ROWS_REMAINING = checkpoint.PLAN_ROWS_TOTAL - checkpoint.ROWS_COMPLETED;
  checkpoint.LAST_COMPLETED_INDEX = planRow.INDEX;
  checkpoint.LAST_COMPLETED_CASE_KEY = key;
  checkpoint.CURRENT_CASE_KEY = "";
  checkpoint.SESSION_REFRESHES = counters.sessionRefreshes;
  checkpoint.NETWORK_RETRIES = counters.networkRetries;
  checkpoint.TIMEOUT_RETRIES = counters.timeoutRetries;
}

export function validateCheckpointAgainstPlan(checkpoint, planRows) {
  const planKeys = new Set(planRows.map((r) => r.CASE_KEY));
  let corrupt = 0;
  let duplicateKeys = 0;
  let unknown = 0;
  const seen = new Set();
  for (const [key, row] of Object.entries(checkpoint.ROWS || {})) {
    if (!planKeys.has(key)) unknown += 1;
    if (seen.has(key)) duplicateKeys += 1;
    seen.add(key);
    if (!isCompletedRow(row)) corrupt += 1;
    if (/Call log:/i.test(JSON.stringify(row))) corrupt += 1;
  }
  return { corrupt, duplicateKeys, unknown };
}

export class HeartbeatWriter {
  constructor(heartbeatPath, lockPath) {
    this.heartbeatPath = heartbeatPath;
    this.lockPath = lockPath;
    this.state = {};
    this.timer = null;
  }

  update(partial) {
    this.state = { ...this.state, ...partial, LAST_HEARTBEAT_AT: new Date().toISOString() };
    writeFileAtomic(this.heartbeatPath, `${JSON.stringify(sanitizeForJson(this.state), null, 2)}\n`);
    if (this.lockPath) touchResponsiveLockHeartbeat(this.lockPath);
  }

  startInterval() {
    if (this.timer) return;
    this.timer = setInterval(() => {
      this.update({});
    }, 10_000);
  }

  stopInterval() {
    if (this.timer) {
      clearInterval(this.timer);
      this.timer = null;
    }
  }
}

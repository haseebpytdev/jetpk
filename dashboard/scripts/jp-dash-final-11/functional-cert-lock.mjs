import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const lockPath = path.join(repoRoot, "tmp/jp-functional-cert-matrix.lock");

function lockHolderPid(holder) {
  const pid = Number.parseInt(String(holder).split("@")[0], 10);
  return Number.isFinite(pid) ? pid : null;
}

function processAlive(pid) {
  if (!pid) {
    return false;
  }
  try {
    process.kill(pid, 0);
    return true;
  } catch {
    return false;
  }
}

export function acquireMatrixLock() {
  if (fs.existsSync(lockPath)) {
    const existing = fs.readFileSync(lockPath, "utf8").trim();
    const holderPid = lockHolderPid(existing);
    if (!processAlive(holderPid)) {
      fs.unlinkSync(lockPath);
    } else {
      return { ok: false, holder: existing };
    }
  }
  const payload = `${process.pid}@${new Date().toISOString()}`;
  fs.writeFileSync(lockPath, payload, "utf8");
  return { ok: true, holder: payload };
}

export function releaseMatrixLock() {
  try {
    if (fs.existsSync(lockPath)) {
      fs.unlinkSync(lockPath);
    }
  } catch {
    /* ignore */
  }
}

export function writeFileAtomic(filePath, content) {
  const dir = path.dirname(filePath);
  const tmp = path.join(dir, `${path.basename(filePath)}.${process.pid}.tmp`);
  fs.writeFileSync(tmp, content, "utf8");
  // Windows AV/indexers can briefly lock the destination during rename.
  for (let attempt = 0; attempt < 5; attempt += 1) {
    try {
      fs.renameSync(tmp, filePath);
      return;
    } catch (err) {
      const code = err?.code;
      if (code !== "EPERM" && code !== "EACCES" && code !== "EBUSY") {
        try {
          fs.unlinkSync(tmp);
        } catch {
          /* ignore */
        }
        throw err;
      }
      if (attempt < 4) {
        const waitMs = 25 * (attempt + 1);
        const end = Date.now() + waitMs;
        while (Date.now() < end) {
          /* brief backoff */
        }
        continue;
      }
      // Last resort: direct overwrite (still durable enough for heartbeat/lock).
      fs.writeFileSync(filePath, content, "utf8");
      try {
        fs.unlinkSync(tmp);
      } catch {
        /* ignore */
      }
      return;
    }
  }
}

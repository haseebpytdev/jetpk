/**
 * Phase 0: lock holder + active matrix writer sanity.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { spawnSync } from "node:child_process";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const lockPath = path.join(repoRoot, "tmp/jp-functional-cert-matrix.lock");
const responsiveLockPath = path.join(
  repoRoot,
  "docs/evidence/jp-dash-final-11/responsive-authoritative/FINAL-RESPONSIVE-SWEEP.lock.json",
);

function holderPid(holder) {
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

function main() {
  let staleLocks = 0;
  let activeWriters = 0;
  let holder = "";
  if (fs.existsSync(lockPath)) {
    holder = fs.readFileSync(lockPath, "utf8").trim();
    const pid = holderPid(holder);
    if (processAlive(pid)) {
      activeWriters = 1;
      const ps = spawnSync("powershell", ["-NoProfile", "-Command", `(Get-Process -Id ${pid} -ErrorAction SilentlyContinue).Path`], {
        encoding: "utf8",
      });
      console.log(`LOCK_HOLDER_PID=${pid}`);
      console.log(`LOCK_HOLDER_PATH=${(ps.stdout || "").trim()}`);
    } else {
      staleLocks = 1;
      fs.unlinkSync(lockPath);
      console.log(`STALE_LOCK_CLEARED=${holder}`);
    }
  }
  let finalCertActive = 0;
  const psList = spawnSync(
    "powershell",
    [
      "-NoProfile",
      "-Command",
      "Get-CimInstance Win32_Process -Filter \"name='node.exe'\" | Select-Object -ExpandProperty CommandLine",
    ],
    { encoding: "utf8" },
  );
  const cmdLines = String(psList.stdout || "");
  if (/final-responsive-sweep\.mjs|run-final-responsive-regression-cert\.mjs/.test(cmdLines)) {
    finalCertActive = 1;
  }
  if (fs.existsSync(responsiveLockPath)) {
    try {
      const lock = JSON.parse(fs.readFileSync(responsiveLockPath, "utf8"));
      if (processAlive(lock.PID)) finalCertActive = 1;
    } catch {
      /* ignore */
    }
  }

  console.log(`FUNCTIONAL_CERT_ACTIVE_WRITERS=${activeWriters}`);
  console.log(`FORM_CERT_ACTIVE_WRITERS=${activeWriters}`);
  console.log(`MATRIX_CONCURRENT_WRITERS=${activeWriters}`);
  console.log(`FINAL_CERT_ACTIVE_WRITERS=${finalCertActive}`);
  console.log(`STALE_LOCKS=${staleLocks}`);
}

main();

import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
export const repoRoot = path.resolve(__dirname, "../../../");
export const EVIDENCE_ROOT = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

export const VIEWPORTS = [
  { name: "DESKTOP", width: 1440, height: 900 },
  { name: "TABLET", width: 1024, height: 768 },
  { name: "MOBILE", width: 390, height: 844 },
];

export function caseKey(roleKey, route, viewport) {
  return `${roleKey}|${route}|${viewport}`;
}

export function resolveNamespace(env = process.env) {
  const ns = env.JP_FINAL11_RESPONSIVE_NAMESPACE?.trim();
  if (ns) return ns;
  if (env.JP_FINAL11_SWEEP_TEST_MODE === "1") return "responsive-selftest";
  if (env.JP_FINAL11_MINI_LIVE === "1") return "responsive-mini-live";
  return "responsive-authoritative";
}

export function resolveEvidenceDir(env = process.env) {
  const ns = resolveNamespace(env);
  const dir = path.join(EVIDENCE_ROOT, ns);
  fs.mkdirSync(dir, { recursive: true });
  return dir;
}

export function planPath() {
  return path.join(EVIDENCE_ROOT, "FINAL-RESPONSIVE-EXECUTION-PLAN.json");
}

export function pathsForDir(evidenceDir) {
  return {
    evidenceDir,
    lock: path.join(evidenceDir, "FINAL-RESPONSIVE-SWEEP.lock.json"),
    checkpoint: path.join(evidenceDir, "FINAL-RESPONSIVE-CHECKPOINT.json"),
    heartbeat: path.join(evidenceDir, "FINAL-RESPONSIVE-HEARTBEAT.json"),
    fingerprint: path.join(evidenceDir, "FINAL-RESPONSIVE-RUN-FINGERPRINT.json"),
    matrixCsv: path.join(evidenceDir, "FINAL-RESPONSIVE-ROUTE-MATRIX.csv"),
    runJson: path.join(evidenceDir, "FINAL-RESPONSIVE-RUN.json"),
    blockersJson: path.join(evidenceDir, "FINAL-RESPONSIVE-BLOCKERS.json"),
    networkJson: path.join(evidenceDir, "FINAL-RESPONSIVE-BROWSER-CONSOLE-NETWORK.json"),
    reachableJson: path.join(evidenceDir, "FINAL-REACHABLE-ROUTES.json"),
  };
}

export function canonicalEvidencePaths() {
  return {
    matrixCsv: path.join(EVIDENCE_ROOT, "FINAL-RESPONSIVE-ROUTE-MATRIX.csv"),
    runJson: path.join(EVIDENCE_ROOT, "FINAL-RESPONSIVE-RUN.json"),
    blockersJson: path.join(EVIDENCE_ROOT, "FINAL-RESPONSIVE-BLOCKERS.json"),
    networkJson: path.join(EVIDENCE_ROOT, "BROWSER-CONSOLE-NETWORK.json"),
    reachableJson: path.join(EVIDENCE_ROOT, "FINAL-REACHABLE-ROUTES.json"),
  };
}

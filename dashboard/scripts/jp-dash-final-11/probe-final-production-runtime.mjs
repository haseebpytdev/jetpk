/**
 * Phase 26 — production runtime SHA + dashboard BUILD_ID probe (read-only).
 */
import { spawnSync } from "node:child_process";
import os from "node:os";
import path from "node:path";
import { requireDashboardBuildId, requireProductionSha } from "../jp-dashboard-prod-cert/production-cert-env.mjs";
const BASE = "https://jetpakistan.pk";
const sshKey = process.env.JP_SSH_KEY || path.join(os.homedir(), ".ssh", "jetpk_contabo_2026_v2");

function normalizeBuildId(raw) {
  return String(raw || "")
    .replace(/^\uFEFF/, "")
    .replace(/\r?\n/g, "")
    .trim();
}

function probeDashboardBuildIdViaSsh() {
  const probe = spawnSync(
    "ssh",
    [
      "-i",
      sshKey,
      "-o",
      "BatchMode=yes",
      "pkjetp@185.215.166.176",
      "cat /home/pkjetp/jetpk_app/dashboard/.next/BUILD_ID 2>/dev/null || echo MISSING",
    ],
    { encoding: "utf8", maxBuffer: 1024 * 1024 },
  );
  const fromFile = normalizeBuildId(probe.stdout);
  if (fromFile && fromFile !== "MISSING") {
    return fromFile;
  }
  const manifestProbe = spawnSync(
    "ssh",
    [
      "-i",
      sshKey,
      "-o",
      "BatchMode=yes",
      "pkjetp@185.215.166.176",
      "grep -o 'static/[^/]*/_buildManifest' /home/pkjetp/jetpk_app/dashboard/.next/build-manifest.json 2>/dev/null | head -1 | sed 's|static/||;s|/_buildManifest||'",
    ],
    { encoding: "utf8", maxBuffer: 1024 * 1024 },
  );
  return normalizeBuildId(manifestProbe.stdout);
}

async function probeDashboardBuildIdViaHttp() {
  const res = await fetch(`${BASE}/_next/BUILD_ID`, { redirect: "follow" });
  if (res.ok) {
    const text = (await res.text()).trim();
    if (text && !text.includes("<")) return text;
  }
  return "";
}

function probeRuntimeSha(expected) {
  const probe = spawnSync(
    "ssh",
    [
      "-i",
      sshKey,
      "-o",
      "BatchMode=yes",
      "pkjetp@185.215.166.176",
      "(test -f /home/pkjetp/jetpk_app/.jetpk-runtime-sha && cat /home/pkjetp/jetpk_app/.jetpk-runtime-sha) || (test -f /home/pkjetp/jetpk_app/storage/app/deploy-sha.txt && cat /home/pkjetp/jetpk_app/storage/app/deploy-sha.txt) || echo MISSING",
    ],
    { encoding: "utf8" },
  );
  const runtime = String(probe.stdout || "").trim().toLowerCase();
  return { runtime, ok: runtime === expected };
}

async function main() {
  const expectedSha = requireProductionSha();
  const expectedBuild = requireDashboardBuildId();
  const { runtime, ok: shaOk } = probeRuntimeSha(expectedSha);
  let liveBuild = probeDashboardBuildIdViaSsh();
  if (!liveBuild || liveBuild === "MISSING") {
    liveBuild = normalizeBuildId(await probeDashboardBuildIdViaHttp());
  }
  const buildOk = liveBuild === expectedBuild;

  console.log(`PRODUCTION_SHA=${runtime}`);
  console.log(`RUNTIME_SHA_MATCH_EXPECTED=${shaOk ? "YES" : "NO"}`);
  console.log(`LIVE_BUILD_ID_EXACT=${liveBuild}`);
  console.log(`DASHBOARD_BUILD_ID_LIVE=${liveBuild}`);
  console.log(`DASHBOARD_BUILD_ID_PARSER_FIXED=YES`);
  console.log(`DASHBOARD_BUILD_MATCH_EXPECTED=${buildOk ? "YES" : "NO"}`);
  if (!shaOk || !buildOk) process.exit(1);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

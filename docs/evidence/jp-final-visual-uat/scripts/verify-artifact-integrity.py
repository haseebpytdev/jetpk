#!/usr/bin/env python3
"""Verify active visual-UAT manifests against staged artifact bytes + build consistency.

Fails if any active FILE is missing, hash mismatches, resolves from archive-rejected,
or carries a stale RELEASE/PUBLIC/DASHBOARD build id.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import re
import subprocess
import sys
from pathlib import Path


ACTIVE_MANIFESTS = [
    "manifest-live.json",
    "manifest-group-payment-live.json",
    "manifest-portals-live.json",
    "manifest-golden-flights-live.json",
    "manifest-logo-favicon-matrix.json",
    "manifest-functional-regression.json",
    # legacy top-level manifest.json / portals-flights (empty supersession) excluded
]

ARCHIVE_MARKERS = ("archive-rejected-", "archive/", "archive-historical/")

FINAL_RELEASE_SHA = "675c7e5efaa2656b2435c186cfe4665e086676bb"
FINAL_PUBLIC_BUILD_ID = "FfNP1fgjiB4_gK6lNt4FI"
FINAL_DASHBOARD_BUILD_ID = "dOefZBIOnEl7EbTETViNa"

STALE_TOKENS = (
    "x0Fcwu-hw0F-hU42hMwZe",
    "LqbZGu-MD_-drCGMO63af",
    "nme7",
    "ztvU",
    "db901c2",
    "27a1ed9",
)

PUBLIC_ROW_HINTS = (
    "public/",
    "auth/",
    "groups/",
    "branding/",
    "global/",
    "flights/",
    "matrices/matrix-_",
)
DASHBOARD_ROW_HINTS = (
    "portals/",
    "matrices/matrix-dash-",
    "/admin/",
    "/customer/",
    "/agent/",
)


def sha256_file(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as f:
        for chunk in iter(lambda: f.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def git_commit(evidence_root: Path) -> str:
    try:
        out = subprocess.check_output(
            ["git", "rev-parse", "HEAD"],
            cwd=str(evidence_root.parents[2] if len(evidence_root.parts) > 2 else evidence_root),
            text=True,
            stderr=subprocess.DEVNULL,
        )
        return out.strip()
    except Exception:
        return "UNKNOWN"


def collect_rows(manifest_path: Path) -> list[dict]:
    if not manifest_path.exists():
        return []
    data = json.loads(manifest_path.read_text(encoding="utf-8"))
    if isinstance(data, list):
        return [r for r in data if isinstance(r, dict) and r.get("FILE")]
    if isinstance(data, dict):
        rows: list[dict] = []
        for key in ("rows", "screenshots", "logoRows", "faviconRows"):
            for r in data.get(key) or []:
                if isinstance(r, dict) and r.get("FILE"):
                    rows.append(r)
        # Top-level build stamps (functional regression) — synthetic row for gate only
        if not rows and (
            data.get("PUBLIC_BUILD_ID")
            or data.get("publicBuildId")
            or data.get("RELEASE_SHA")
            or data.get("releaseSha")
        ):
            rows.append(
                {
                    "FILE": f"__manifest__/{manifest_path.name}",
                    "RELEASE_SHA": data.get("RELEASE_SHA") or data.get("releaseSha"),
                    "PUBLIC_BUILD_ID": data.get("PUBLIC_BUILD_ID") or data.get("publicBuildId"),
                    "DASHBOARD_BUILD_ID": data.get("DASHBOARD_BUILD_ID") or data.get("dashboardBuildId"),
                    "_synthetic": True,
                }
            )
        # Also stamp logo summary fields onto each row if missing
        top_release = data.get("RELEASE_SHA") or data.get("releaseSha")
        top_public = data.get("PUBLIC_BUILD_ID") or data.get("publicBuildId")
        top_dash = data.get("DASHBOARD_BUILD_ID") or data.get("dashboardBuildId")
        for r in rows:
            if top_release and not r.get("RELEASE_SHA") and not r.get("releaseSha"):
                r["RELEASE_SHA"] = top_release
            if top_public and not r.get("PUBLIC_BUILD_ID") and not r.get("publicBuildId"):
                r["PUBLIC_BUILD_ID"] = top_public
            if top_dash and not r.get("DASHBOARD_BUILD_ID") and not r.get("dashboardBuildId"):
                r["DASHBOARD_BUILD_ID"] = top_dash
        return rows
    return []


def resolve_source(evidence_root: Path, file_rel: str) -> Path | None:
    """Resolve active screenshot under live/ only (never archive)."""
    if file_rel.startswith("__manifest__/"):
        return evidence_root / file_rel.replace("__manifest__/", "")
    rel = file_rel.replace("\\", "/").lstrip("/")
    candidates = [
        evidence_root / "live" / rel,
        evidence_root / rel,
        evidence_root / "live" / Path(rel).name,
    ]
    if not rel.startswith("live/"):
        candidates.insert(0, evidence_root / "live" / rel)
    for c in candidates:
        if c.is_file() and "archive-rejected" not in str(c).replace("\\", "/") and "/archive/" not in str(c).replace("\\", "/"):
            return c.resolve()
    return None


def resolve_artifact(artifact_root: Path, file_rel: str, source: Path | None, evidence_root: Path) -> Path | None:
    if file_rel.startswith("__manifest__/"):
        name = file_rel.split("/", 1)[-1]
        for c in (artifact_root / name, artifact_root / "manifests" / name):
            if c.is_file():
                return c.resolve()
        return None
    rel = file_rel.replace("\\", "/").lstrip("/")
    candidates = [
        artifact_root / "screenshots" / "live" / rel,
        artifact_root / "screenshots" / rel,
    ]
    if source is not None:
        try:
            live_root = (evidence_root / "live").resolve()
            rel_from_live = source.resolve().relative_to(live_root).as_posix()
            candidates.insert(0, artifact_root / "screenshots" / "live" / rel_from_live)
        except Exception:
            pass
    for c in candidates:
        if c.is_file():
            return c.resolve()
    return None


def row_release(row: dict) -> str | None:
    v = row.get("RELEASE_SHA") or row.get("releaseSha")
    return str(v).strip() if v else None


def row_public(row: dict) -> str | None:
    v = row.get("PUBLIC_BUILD_ID") or row.get("publicBuildId") or row.get("OBSERVED_PUBLIC_BUILD_ID")
    return str(v).strip() if v else None


def row_dashboard(row: dict) -> str | None:
    v = row.get("DASHBOARD_BUILD_ID") or row.get("dashboardBuildId")
    return str(v).strip() if v else None


def looks_public(file_rel: str, row: dict) -> bool:
    f = file_rel.replace("\\", "/")
    if any(h in f for h in DASHBOARD_ROW_HINTS) and "matrices/matrix-dash-" in f:
        return False
    if any(h in f for h in PUBLIC_ROW_HINTS):
        return True
    role = str(row.get("ROLE") or "").lower()
    return role in ("anonymous", "customer") and "portals/" not in f


def looks_dashboard(file_rel: str, row: dict) -> bool:
    f = file_rel.replace("\\", "/")
    if any(h in f for h in DASHBOARD_ROW_HINTS):
        return True
    return bool(row_dashboard(row))


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--evidence-root", required=True)
    ap.add_argument("--artifact-root", required=True)
    ap.add_argument("--out", required=True)
    args = ap.parse_args()

    evidence_root = Path(args.evidence_root).resolve()
    artifact_root = Path(args.artifact_root).resolve()
    out_path = Path(args.out)

    evidence_commit = git_commit(evidence_root)
    files: list[dict] = []
    seen: set[str] = set()
    unique_paths: set[str] = set()

    source_missing = 0
    artifact_missing = 0
    hash_mismatch = 0
    archive_overlap = 0
    release_mismatch = 0
    public_mismatch = 0
    dashboard_mismatch = 0
    stale_token_hits = 0

    for name in ACTIVE_MANIFESTS:
        manifest_path = evidence_root / name
        # Scan whole active manifest text for stale tokens (excluding archive path strings)
        if manifest_path.is_file():
            text = manifest_path.read_text(encoding="utf-8", errors="ignore")
            for tok in STALE_TOKENS:
                if tok in text:
                    stale_token_hits += 1

        for row in collect_rows(manifest_path):
            file_rel = str(row["FILE"]).replace("\\", "/")
            key = f"{name}::{file_rel}"
            if key in seen:
                continue
            seen.add(key)
            if not row.get("_synthetic"):
                unique_paths.add(file_rel)

            source = resolve_source(evidence_root, file_rel)
            artifact = resolve_artifact(artifact_root, file_rel, source, evidence_root)

            source_sha = sha256_file(source) if source and source.suffix.lower() == ".png" else (
                sha256_file(source) if source else None
            )
            artifact_sha = sha256_file(artifact) if artifact else None
            # Synthetic manifest stamps: MATCH if both present as files
            if row.get("_synthetic"):
                match = source is not None and artifact is not None
                if source is None:
                    source_missing += 1
                if artifact is None:
                    artifact_missing += 1
            else:
                match = bool(source_sha and artifact_sha and source_sha == artifact_sha)
                if source is None:
                    source_missing += 1
                if artifact is None:
                    artifact_missing += 1
                if source_sha and artifact_sha and source_sha != artifact_sha:
                    hash_mismatch += 1

            if source and any(m in str(source).replace("\\", "/") for m in ARCHIVE_MARKERS):
                archive_overlap += 1
            if artifact and any(m in str(artifact).replace("\\", "/") for m in ARCHIVE_MARKERS):
                archive_overlap += 1

            rel = row_release(row)
            pub = row_public(row)
            dash = row_dashboard(row)

            if rel and rel != FINAL_RELEASE_SHA:
                release_mismatch += 1
            if looks_public(file_rel, row) and pub and pub != FINAL_PUBLIC_BUILD_ID:
                public_mismatch += 1
            if looks_dashboard(file_rel, row) and dash and dash != FINAL_DASHBOARD_BUILD_ID:
                dashboard_mismatch += 1
            # Public rows must declare the final public build when field present
            if (looks_public(file_rel, row) or row.get("_synthetic")) and pub and pub != FINAL_PUBLIC_BUILD_ID:
                if not looks_dashboard(file_rel, row):
                    pass  # already counted
            if row.get("_synthetic") and dash and dash != FINAL_DASHBOARD_BUILD_ID:
                dashboard_mismatch += 1

            files.append(
                {
                    "EVIDENCE_COMMIT": evidence_commit,
                    "MANIFEST": name,
                    "FILE": file_rel,
                    "SOURCE_PATH": str(source) if source else None,
                    "ARTIFACT_PATH": str(artifact) if artifact else None,
                    "SOURCE_SHA256": source_sha,
                    "ARTIFACT_SHA256": artifact_sha,
                    "MATCH": match,
                    "RELEASE_SHA": rel,
                    "PUBLIC_BUILD_ID": pub,
                    "DASHBOARD_BUILD_ID": dash,
                }
            )

    integrity = (
        "PASS"
        if source_missing == 0
        and artifact_missing == 0
        and hash_mismatch == 0
        and archive_overlap == 0
        and release_mismatch == 0
        and public_mismatch == 0
        and dashboard_mismatch == 0
        and stale_token_hits == 0
        and len(files) > 0
        else "FAIL"
    )

    payload = {
        "EVIDENCE_COMMIT": evidence_commit,
        "ARTIFACT_INTEGRITY": integrity,
        "SOURCE_FILE_MISSING": source_missing,
        "ARTIFACT_FILE_MISSING": artifact_missing,
        "HASH_MISMATCH": hash_mismatch,
        "ARCHIVE_OVERLAP": archive_overlap,
        "ACTIVE_RELEASE_SHA_MISMATCH": release_mismatch,
        "ACTIVE_PUBLIC_BUILD_MISMATCH": public_mismatch,
        "ACTIVE_DASHBOARD_BUILD_MISMATCH": dashboard_mismatch,
        "STALE_TOKEN_HITS": stale_token_hits,
        "ACTIVE_MANIFEST_ROWS": len(files),
        "UNIQUE_ACTIVE_SCREENSHOTS": len(unique_paths),
        "ACTIVE_FILE_COUNT": len(files),  # legacy alias = manifest rows
        "FINAL_RELEASE_SHA": FINAL_RELEASE_SHA,
        "FINAL_PUBLIC_BUILD_ID": FINAL_PUBLIC_BUILD_ID,
        "FINAL_DASHBOARD_BUILD_ID": FINAL_DASHBOARD_BUILD_ID,
        "files": files,
    }
    out_path.parent.mkdir(parents=True, exist_ok=True)
    out_path.write_text(json.dumps(payload, indent=2), encoding="utf-8")
    print(json.dumps({k: payload[k] for k in payload if k != "files"}, indent=2))
    return 0 if integrity == "PASS" else 1


if __name__ == "__main__":
    sys.exit(main())

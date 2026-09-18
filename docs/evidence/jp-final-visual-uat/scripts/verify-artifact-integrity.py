#!/usr/bin/env python3
"""Verify active visual-UAT manifests against staged artifact bytes.

Fails if any active FILE is missing, hash mismatches, or resolves from archive-rejected.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import subprocess
import sys
from pathlib import Path


ACTIVE_MANIFESTS = [
    "manifest-live.json",
    "manifest-group-payment-live.json",
    "manifest-portals-flights-live.json",
    "manifest-golden-flights-live.json",
    "manifest-logo-favicon-matrix.json",
    "manifest-functional-regression.json",
    # legacy top-level manifest.json points at obsolete groups/group-payment-w*.png
    # and must not gate the active live artifact.
]

ARCHIVE_MARKERS = ("archive-rejected-", "archive/")


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
        rows = data.get("rows") or data.get("screenshots") or []
        return [r for r in rows if isinstance(r, dict) and r.get("FILE")]
    return []


def resolve_source(evidence_root: Path, file_rel: str) -> Path | None:
    """Resolve active screenshot under live/ or groups/ only (never archive)."""
    rel = file_rel.replace("\\", "/").lstrip("/")
    candidates = [
        evidence_root / "live" / rel,
        evidence_root / rel,
        evidence_root / "live" / Path(rel).name,
    ]
    # FILE values are often like "groups/foo.png" or "portals/foo.png" relative to live/
    if not rel.startswith("live/"):
        candidates.insert(0, evidence_root / "live" / rel)
    for c in candidates:
        if c.is_file() and "archive-rejected" not in str(c).replace("\\", "/"):
            return c.resolve()
    return None


def resolve_artifact(artifact_root: Path, file_rel: str, source: Path | None, evidence_root: Path) -> Path | None:
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

    source_missing = 0
    artifact_missing = 0
    hash_mismatch = 0
    archive_overlap = 0

    for name in ACTIVE_MANIFESTS:
        for row in collect_rows(evidence_root / name):
            file_rel = str(row["FILE"]).replace("\\", "/")
            key = f"{name}::{file_rel}"
            if key in seen:
                continue
            seen.add(key)

            source = resolve_source(evidence_root, file_rel)
            artifact = resolve_artifact(artifact_root, file_rel, source, evidence_root)

            source_sha = sha256_file(source) if source else None
            artifact_sha = sha256_file(artifact) if artifact else None
            match = bool(source_sha and artifact_sha and source_sha == artifact_sha)

            if source is None:
                source_missing += 1
            if artifact is None:
                artifact_missing += 1
            if source_sha and artifact_sha and source_sha != artifact_sha:
                hash_mismatch += 1

            # Archive must never be the resolved source/artifact path for an active row
            if source and any(m in str(source).replace("\\", "/") for m in ARCHIVE_MARKERS):
                archive_overlap += 1
            if artifact and any(m in str(artifact).replace("\\", "/") for m in ARCHIVE_MARKERS):
                archive_overlap += 1

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
                }
            )

    integrity = (
        "PASS"
        if source_missing == 0
        and artifact_missing == 0
        and hash_mismatch == 0
        and archive_overlap == 0
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
        "ACTIVE_FILE_COUNT": len(files),
        "files": files,
    }
    out_path.parent.mkdir(parents=True, exist_ok=True)
    out_path.write_text(json.dumps(payload, indent=2), encoding="utf-8")
    print(json.dumps({k: payload[k] for k in payload if k != "files"}, indent=2))
    return 0 if integrity == "PASS" else 1


if __name__ == "__main__":
    sys.exit(main())

# JP-BOOKING-LIVE-UAT-01 — handoff (do not execute here)

## Prereqs from Closure-02

- Flight search live-certified; SMTP supplier row deactivated
- FAB-02 live
- CMS trending past dates fixed on public HTML
- AbhiPay still **BLOCKED_CONFIGURATION** (0 gateway rows) — decide manual-only vs configure before payment UAT

## Scope (next phase only)

1. One supplier at a time (prefer AirBlue Zapways TEST conn 23 historically certified; then Sabre; then PIA).
2. Controlled OW: search → select → passengers → review → **approved** book → retrieve → optional ticket → cancel.
3. Explicit owner authorization for each mutation class.
4. Idempotent external refs; named QA agency/user; redact secrets/PII in evidence.

## Hard bans

- No refund spam; no production customer email blasts; no One API enable; no Al-Haider book unless flag explicitly enabled.

## Exit

Per-supplier PASS/FAIL/BLOCKED with PNRs and status transitions. Search certification ≠ booking PASS.

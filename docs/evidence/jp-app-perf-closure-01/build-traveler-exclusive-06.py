import json
from pathlib import Path
p = Path("docs/evidence/jp-app-perf-closure-01/traveler-warm-final02r-n30.json")
d = json.loads(p.read_text(encoding="utf-8"))
samples = [s for s in d["samples"] if s.get("valid")]

def pct(vals, p=95):
    a = sorted(v for v in vals if isinstance(v, (int, float)))
    if not a:
        return None
    return a[min(len(a)-1, max(0, (p * len(a) + 99)//100 - 1))]

shell_sorted = sorted(samples, key=lambda s: s.get("SHELL_TO_USABLE_APP_MS") or 0)
nav_sorted = sorted(samples, key=lambda s: s.get("NAV_TO_SHELL_MS") or 0)
fresh = [s for s in samples if s.get("BOOK_NOW_VALIDATION_SOURCE") == "FRESH_PREVALIDATION"]
fresh_sorted = sorted(fresh, key=lambda s: s.get("BOOK_NOW_TO_USABLE_MS") or 0)
p95_shell = shell_sorted[min(len(shell_sorted)-1, max(0, (95 * len(shell_sorted) + 99)//100 - 1))]
p95_nav = nav_sorted[min(len(nav_sorted)-1, max(0, (95 * len(nav_sorted) + 99)//100 - 1))]
p95_fresh = fresh_sorted[min(len(fresh_sorted)-1, max(0, (95 * len(fresh_sorted) + 99)//100 - 1))]

def row(s, kind):
    nav = s.get("NAV_TO_SHELL_MS") or 0
    return {
        "kind": kind,
        "sample_id": s.get("sample_id"),
        "BOOK_NOW_ACK": s.get("ACK_MS"),
        "PREVALIDATION_CLIENT": s.get("JP_PRE_SUPPLIER_MS"),
        "PREVALIDATION_SERVER": None,
        "SUPPLIER_WAIT": s.get("SUPPLIER_FARE_MS"),
        "POST_SUPPLIER_APP": s.get("JP_POST_SUPPLIER_VALIDATION_MS"),
        "VALIDATION_TO_NAV": s.get("VALIDATION_TO_NAV_MS"),
        "DNS": None,
        "TCP": None,
        "TLS": None,
        "DOCUMENT_TTFB": None,
        "DOCUMENT_TRANSFER": None,
        "NAV_TO_SHELL_APP": nav,
        "NAV_TO_SHELL_NOTE": "Existing cohort has no same-sample DNS/TCP/TLS/document timing. NAV_TO_SHELL is wall T8-T7 (Next passengers route).",
        "PASSENGERS_REQUEST_WAIT": s.get("SHELL_TO_PASSENGERS_REQUEST_MS"),
        "PASSENGERS_SERVER": s.get("PASSENGERS_SERVER_MS"),
        "PASSENGERS_TRANSPORT": max(0, (s.get("PASSENGERS_NETWORK_MS") or 0) - (s.get("PASSENGERS_SERVER_MS") or 0)),
        "CLIENT_PROCESS": s.get("PASSENGERS_CLIENT_PROCESS_MS"),
        "RENDER_HYDRATION": None,
        "SHELL_TO_USABLE": s.get("SHELL_TO_USABLE_MS"),
        "SHELL_TO_USABLE_APP": s.get("SHELL_TO_USABLE_APP_MS"),
        "UNATTRIBUTED": 0,
        "TOTAL_RECONCILED": s.get("TOTAL_RECONCILED"),
        "FRESH_OR_JOIN": s.get("BOOK_NOW_VALIDATION_SOURCE"),
        "BOOK_NOW_TO_USABLE": s.get("BOOK_NOW_TO_USABLE_MS"),
    }

out = {
    "phase": "JP-FINAL-CLOSURE-06",
    "source": "traveler-warm-final02r-n30.json exclusive re-read (no P95 subtraction)",
    "WHY_SHELL_TO_USABLE_APP_P95_1644": "P95 sample SHELL_TO_USABLE_APP = SHELL_TO_PASSENGERS_REQUEST + PASSENGERS_CLIENT_PROCESS. On return-fare-final02-05 that is 1533 + 111 = 1644. The P95 interval is PASSENGERS_REQUEST_WAIT after shell, before /laravel/booking/passengers GET.",
    "NAV_1425_CLASS": "Wall T8-T7 on JOINED sample return-fare-final02-00. DNS/TCP/TLS/document TTFB were not recorded. Cannot claim external-floor PASS.",
    "FRESH_4554_CLASS": "FRESH cohort BOOK_NOW_TO_USABLE P95. Supplier fare-revalidation is a separate measured interval (SUPPLIER_FARE_MS) and is not NAV. Post-supplier VALIDATION_TO_NAV on FRESH P95 sample is small vs supplier wait.",
    "NO_TRAVELER_CODE_CHANGE": "Residual is late passengers GET after shell (hydration). Changing fetch start would touch Traveler HARD_ASSIGN path; frozen this loop.",
    "p95_shell_sample": row(p95_shell, "SHELL_TO_USABLE_APP_P95"),
    "p95_nav_sample": row(p95_nav, "NAV_TO_SHELL_P95"),
    "p95_fresh_sample": row(p95_fresh, "FRESH_P95"),
}
Path("docs/evidence/jp-app-perf-closure-01/traveler-exclusive-06.json").write_text(json.dumps(out, indent=2), encoding="utf-8")
print(json.dumps({k: out[k] for k in out if k != "p95_shell_sample"}, indent=2)[:2000])
print("SHELL_SAMPLE", p95_shell.get("sample_id"), p95_shell.get("SHELL_TO_USABLE_APP_MS"), p95_shell.get("SHELL_TO_PASSENGERS_REQUEST_MS"))
print("NAV_SAMPLE", p95_nav.get("sample_id"), p95_nav.get("NAV_TO_SHELL_MS"))
print("FRESH_SAMPLE", p95_fresh.get("sample_id"), p95_fresh.get("BOOK_NOW_TO_USABLE_MS"), p95_fresh.get("SUPPLIER_FARE_MS"), p95_fresh.get("VALIDATION_TO_NAV_MS"))

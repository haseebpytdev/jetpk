#!/usr/bin/env python3
import json, os, time, urllib.parse
from http.cookiejar import CookieJar
from pathlib import Path
from urllib.request import HTTPCookieProcessor, Request, build_opener

BASE = os.environ.get("CQ41_BASE", "https://jetpakistan.pk").rstrip("/")
OUT = Path(os.environ.get("CQ41_OUT", "canary-out"))
OUT.mkdir(parents=True, exist_ok=True)
JAR = CookieJar()
OP = build_opener(HTTPCookieProcessor(JAR))


def refresh():
    OP.open(Request(BASE + "/", headers={"User-Agent": "CQ41-Canary/1.0"}), timeout=60).read(2048)


def xsrf():
    for c in JAR:
        if c.name == "XSRF-TOKEN":
            return urllib.parse.unquote(c.value)
    return None


def chat(msg):
    refresh()
    body = json.dumps({"message": msg}).encode()
    h = {
        "User-Agent": "CQ41-Canary/1.0",
        "Accept": "application/json",
        "Content-Type": "application/json",
        "Referer": BASE + "/",
        "Origin": BASE,
        "X-XSRF-TOKEN": xsrf() or "",
    }
    t0 = time.perf_counter()
    for attempt in range(3):
        try:
            with OP.open(Request(BASE + "/api/public/ai/chat", data=body, headers=h, method="POST"), timeout=180) as r:
                js = json.loads(r.read().decode())
                return r.status, js, (time.perf_counter() - t0) * 1000
        except Exception as e:
            code = getattr(e, "code", None)
            if code == 419 or attempt < 2:
                refresh()
                h["X-XSRF-TOKEN"] = xsrf() or ""
                time.sleep(1.5)
                continue
            raise


cases = [
    ("18b_news", "What happened in the news today?"),
    ("18c_btc", "What is Bitcoin's price right now?"),
    ("18d_aapl", "What is the current stock price of Apple?"),
    ("19_fare", "What is the latest Emirates fare Lahore to Dubai?"),
    ("20_booking", "Check my booking please"),
    ("20b_see", "I want to see my booking"),
    ("20c_status", "Booking status"),
    ("21_handoff", "Talk to support"),
]

for key, msg in cases:
    try:
        st, js, ms = chat(msg)
        entry = {
            "http_status": st,
            "latency_ms": round(ms, 1),
            "conversation_id": js.get("conversation_id"),
            "message": (js.get("message") or "")[:1200],
            "meta": js.get("meta") or {},
            "actions": js.get("actions"),
            "state": js.get("state"),
        }
        (OUT / f"{key}.json").write_text(json.dumps(entry, indent=2, ensure_ascii=False), encoding="utf-8")
        print(key, st, round(ms, 1), (js.get("meta") or {}).get("FINAL_RESPONSE_SOURCE"), (js.get("message") or "")[:140].replace("\n", " "), flush=True)
        time.sleep(2)
    except Exception as e:
        print(key, "ERR", e, flush=True)

print("DONE", flush=True)

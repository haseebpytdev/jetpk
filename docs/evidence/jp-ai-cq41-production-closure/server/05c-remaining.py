#!/usr/bin/env python3
import json, os, time, urllib.parse
from http.cookiejar import CookieJar
from urllib.request import HTTPCookieProcessor, Request, build_opener

BASE = "https://jetpakistan.pk"
OUT = "/tmp/cq41-canary-out"
os.makedirs(OUT, exist_ok=True)
JAR = CookieJar()
OP = build_opener(HTTPCookieProcessor(JAR))
OP.open(Request(BASE + "/", headers={"User-Agent": "CQ41"}), timeout=60).read(1024)


def xsrf():
    for c in JAR:
        if c.name == "XSRF-TOKEN":
            return urllib.parse.unquote(c.value)
    return None


def chat(msg):
    body = {"message": msg}
    data = json.dumps(body).encode()
    h = {
        "User-Agent": "CQ41",
        "Accept": "application/json",
        "Content-Type": "application/json",
        "Referer": BASE + "/",
        "Origin": BASE,
        "X-XSRF-TOKEN": xsrf() or "",
    }
    t0 = time.perf_counter()
    last = None
    for attempt in range(3):
        try:
            with OP.open(Request(BASE + "/api/public/ai/chat", data=data, headers=h, method="POST"), timeout=180) as r:
                js = json.loads(r.read().decode())
                ms = (time.perf_counter() - t0) * 1000
                return r.status, js, ms
        except Exception as e:
            last = e
            time.sleep(2 * (attempt + 1))
            OP.open(Request(BASE + "/", headers={"User-Agent": "CQ41"}), timeout=60).read(1024)
            h["X-XSRF-TOKEN"] = xsrf() or ""
    raise last


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
        open(f"{OUT}/{key}.json", "w", encoding="utf-8").write(json.dumps(entry, indent=2, ensure_ascii=False))
        print(key, st, round(ms, 1), (js.get("meta") or {}).get("FINAL_RESPONSE_SOURCE"), (js.get("message") or "")[:120].replace("\n", " "), flush=True)
        time.sleep(1.5)
    except Exception as e:
        print(key, "ERR", e, flush=True)

print("DONE", flush=True)

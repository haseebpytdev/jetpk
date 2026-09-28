# RUN1 noncomparable after handoff

`RUN1_NONCOMPARABLE_AFTER_HANDOFF=YES`

Primary session file: `out/sessions/05-real-qwen-session.json`  
Summary: `out/SUMMARY-run1.json`

- USER_MESSAGES=41, RESUME_ACTIONS=0, SESSION_RECORDS=41
- After `Talk to support` the conversation stayed `WAITING_FOR_HUMAN`
- Later Roman Urdu / pad turns were not normal AI samples (tens of ms)
- First-29 pre-handoff slice remains useful (Qwen 9→4, total p95 ≈12844→7679)

Authoritative comparable run: **RUN2** (`SUMMARY-run2.json`).

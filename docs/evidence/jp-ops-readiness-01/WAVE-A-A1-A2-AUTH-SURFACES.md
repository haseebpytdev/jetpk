# Wave A — A1/A2 auth surface smoke (non-mutating)

```
PROBED_AT_UTC=2026-10-14T19:32:30Z
RUNTIME_SHA=a138eb570b05910046df7fb251beeef839b25395
```

| Surface | URL | Result |
|---------|-----|--------|
| Health | `/up` | 200 |
| Homepage | `/` | 200, brand present |
| Login | `/login` | 200 |
| Agent shell | `/agent` | 200 |
| Admin root | `/admin` | 302 redirect (not 500) |
| Dashboard root | `/dashboard` | 302 redirect (not 500) |
| Legacy `/admin/login` | `/admin/login` | 404 (route model; not regression from Wave 1C) |
| Legacy `/agent/login` | `/agent/login` | 404 (route model; not regression from Wave 1C) |

```
WAVE_A_FINDINGS_NEW=legacy /admin/login and /agent/login return 404; /admin and /agent roots respond without 500
WAVE_A_DEFECTS_OPENED=0
CLASSIFICATION=INVENTORY_NOTE (not new P0/P1 from Wave 1C)
```

No credentials used. No role matrix deep checks yet (A2 continues).

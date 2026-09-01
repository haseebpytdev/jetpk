# JP-AI-ASSIST-01D-R2 authority

## Writable / master

- Writable: `C:\Users\khadi\ota-jetpk`
- Master: `C:\Users\khadi\ota` (strict read-only)

## Branch / freeze (verified at phase start)

| Field | Value |
|-------|-------|
| Branch | `phase/jp-flight-perf-01` |
| Start local HEAD | `78427fa553e8b1295d43b1ea156bb6b88e59a2de` |
| Engineering / production runtime | `0e747db23f9f75839ca73960dcc6fca47dab9ea1` |
| Remote freeze | `1f12edef052da278f02b7ffeaf4e7a881c663ef9` |
| Ahead | 69 |
| Public build | `nNeL8Y49UVwT1K0Dv5Br5` |
| Dashboard build | `fbzOL_dHxc_Iq0ScPoglD` |
| R7D evidence tip | `78427fa553e8b1295d43b1ea156bb6b88e59a2de` |

`git fetch jetpk` confirmed remote unchanged. **No push.**

## Policy

- EXTERNAL_AI_API_ALLOWED=NO
- Local inference only (llama.cpp on 127.0.0.1)
- No live booking / PNR / payment / ticketing / cancel
- No permanent public AI activation in this phase
- Model binaries outside Git

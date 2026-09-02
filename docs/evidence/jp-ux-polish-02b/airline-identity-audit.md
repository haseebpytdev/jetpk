# JP-UX-POLISH-02B — Airline identity audit

## Authority
- Branch: `phase/jp-flight-perf-01`
- Local HEAD start: `63bcf800b2f92168a3c0ad3b877940f2bebe4e28`
- Remote: `1f12edef052da278f02b7ffeaf4e7a881c663ef9` (unchanged)
- Runtime SHA (frontend): `f593ddeb45890fdd7d985f4a5ef9705ac7d4ea03`
- Public build: `m-n0qXZkLHvCqrRPZ2lcx`
- MOFA on production tree: **NO** (`config/visa.php` absent)

## Root cause (02A residual)
Generic IATA CDN (`pics.avs.io` / IATA-only template) was treated as logo identity authority.

| Code | Canonical name | Wrong CDN identity | Fix |
|---|---|---|---|
| PF | AirSial | Primera Air | Official AirSial logo from `www.airsial.com` (green star + AIRSIAL), stored locally |
| 9P | Fly Jinnah | AirArabia wordmark | Restored pre-02A FJ monogram master (not Air Arabia) |

## Resolution contract
- `AIRLINE_LOGO_RESOLUTION_IATA_ONLY=NO`
- Prefer: travel-assets master → airline-logos → fail-closed generic (no IATA CDN for unsafe/override codes)
- `PF` / `9P` overrides set `block_iata_cdn_download=true` + `logo_path` to travel-assets masters
- Config: `ota.airline_logo_cache.iata_only_download_blocked=true` + `iata_cdn_identity_unsafe_codes=['PF','9P']`

## Live asset hashes (production `/storage/airline-logos/`)
| Code | Expected identity | SHA256 (prefix) | Notes |
|---|---|---|---|
| PF | AirSial | `206B5723231F5A9C` | Transparent corners; AirSial wordmark |
| 9P | Fly Jinnah | `ABE2F6547A595CEB` | FJ red circle; not Air Arabia |
| G9 | Air Arabia | `A366982A239FA08D` | Control — distinct from 9P |
| GF | Gulf Air | `5346685582463581` | Control preserved |
| PK | PIA | present | No mismatch flagged |
| SV | Saudia | present | No mismatch flagged |
| QR | Qatar Airways | present | No mismatch flagged |
| EK | Emirates | present | No mismatch flagged |
| WY | Oman Air | present | No mismatch flagged |
| TK | Turkish Airlines | present | No mismatch flagged |
| PA | Airblue | present | No mismatch flagged |

## Live group card proof
- PF live card: `07-groups-pf-live-card.png` — **AIR SIAL · PF** with AirSial logo
- 9P live card: `08-groups-9p-live-card.png` — **FLY JINNAH · 9P** with FJ logo

## Result
- `PF_WRONG_PRIMERA_AIR=0`
- `9P_WRONG_AIRARABIA_WORDMARK=0`
- `CURRENT_AIRLINE_LOGO_IDENTITY_MISMATCHES=0`
- `WRONG_AIRLINE_LOGO=0`
- Stage contract preserved: square, radius 0, transparent, object-fit contain

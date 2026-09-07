# P2 UI QA matrix

| Check | Method | Result target |
|---|---|---|
| Desktop shell | HTML assert `jetpk-container` + `max-width:640px` | PASS |
| Mobile | CSS media 480px + no fixed 620 width | PASS |
| Outlook | table layout, inline styles, VML button where present | PASS |
| Gmail size | representative HTML length audit | ASSESSED |
| Dark mode | light-first; logos/CTAs remain | PASS |
| Text/plain | CustomerFacingEmailRendered / Jetpk plainBody | PASS |
| Encoding | no `\u2014`, no `null` labels | PASS |
| Legacy modern shell | grep View::make modern | 0 callers |

Artifacts: generate under `docs/evidence/jp-email-p2/` with synthetic fixtures only (no production PII).

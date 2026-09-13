# JP-AIRBLUE-ZAPWAYS-03R — deployment report

## Release

| Field | Value |
|---|---|
| Phase | JP-AIRBLUE-ZAPWAYS-03R |
| Branch | `phase/jp-master-unfinished-closure-10` |
| BASELINE SHA | `205aa2613bd338a2a5366578e955c1093c493622` |
| Objective | Authoritative Zapways v3 wire contract correction (no supplier calls) |
| PIA modified | NO |
| Certificate/key changes | NO |

## Corrections

- Removed inferred v3 SOAPActions; only documented Read URLs remain
- SeatMap / Ancillary / AirBookModify type 5 exact supplier hierarchies
- Read/Cancel optional Instance (omit when blank)
- Invalid protocol fail-closed; blank defaults v2
- Certification-aware search: uncertified v3 cannot displace certified v2

## Tests (local)

- `php artisan test --filter=AirBlue` — 85 passed, 262 assertions

## Production deploy

Scoped runtime files only (see Files to upload).

## Certification blockers (unchanged)

- JetPakistan Zapways credentials: WAITING
- Certificate supplier-loaded: EXTERNAL / WAITING
- v2/v3 supplier certification: WAITING

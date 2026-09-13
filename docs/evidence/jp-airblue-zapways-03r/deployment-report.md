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

| Field | Value |
|---|---|
| Backup ID | `20260913T172625Z` |
| Method | scoped SCP runtime files + artisan cache rebuild |
| RELEASE SHA | `7fd1517c44ddd42e025b76c35a3a80de47e1ac5a` |
| PRODUCTION GIT HEAD | N/A (not a Git working tree) |

## Hash parity (local vs production)

| File | SHA256 |
|---|---|
| `AirBlueZapwaysProtocolVersion.php` | `7b19be730f07509d756f949ead7120e0550b9a0adb3a692739424e1f73baac1a` |
| `AirBlueConnectionSearchPolicy.php` | `a1a7361e3b1c2884ee7914633aaa339939b52987a1f6160fee4081dea2204397` |
| `AirBlueOtaXmlBuilder.php` | `4e62b1d8887a6a4281256c8aa231f70b46a907fe12c245212d5ad731fe1b251d` |
| `config/suppliers.php` | `b249c028cdfbfe61a077368af7f8fe422356ec09f2440e65a4c949b62213d93c` |

## Production smoke

| Check | Result |
|---|---|
| `https://jetpakistan.pk/` HTTP | 200 |
| Laravel log AirBlue errors | none recent |
| Zapways supplier calls | NONE |
| TLS key regenerated | NO |

## Certification blockers (unchanged)

- JetPakistan Zapways credentials: WAITING
- Certificate supplier-loaded: EXTERNAL / WAITING
- v2/v3 supplier certification: WAITING

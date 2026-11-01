# Phase 4 — Document precedence policy (declared; not implemented)

```
DOCUMENT_PRECEDENCE_POLICY=
1) Non-empty passport_number wins: DocID=passport, DocType=2 (repo-proven), issuing/expiry only from real passport fields.
2) Else if non-empty national_id_number AND NATIONAL_ID_DOCUMENT_TYPE_VALUE proven from Zapways OTA contract: emit Document with that DocType; omit ExpireDate/DocIssueCountry unless authoritative fields exist (none for national_id_expiry today).
3) Never overwrite passport with national_id.
4) Never emit blank/partial Document (empty DocID forbidden).
5) Never fabricate expiry/country/type.
6) If neither document available: fail-closed before AirBook once ID is mandatory for ticketing path, OR preserve legacy omit only where supplier contract explicitly permits — pending VERIFICATION-01.
7) AirBlue-only; no Sabre/PIA/IATI/Duffel/OneAPI payload changes.
```

Implementation of steps 2/6 deferred until DocType proven.

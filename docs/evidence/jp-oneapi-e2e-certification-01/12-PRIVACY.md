# OneAPI Privacy / Redaction

DATE: 2026-11-02

## Rules obeyed

- No passwords / bearer tokens / JSESSIONID values / raw transaction identifiers in evidence
- No private passenger identity
- No raw PNRs / ticket numbers in public evidence
- SOAP credentials never committed
- Evidence uses fixture masks (`FIXTURE_SESSION_MASKED`, `***` suffixes)

## Leak checks (offline)

- Book interpreter safe summary masks PNR and transaction id
- SOAP live transport logs go through `SensitiveDataRedactor`
- Cross-supplier credential/session leak N/A until AEROST lane present on separate branch

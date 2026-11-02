# Privacy

- No passwords/bearer tokens/PNRs/ticket refs/invoices in public evidence
- Offer tokens carry opaque NLD/acceptance only
- Auth/HTTP logs use SensitiveDataRedactor
- Cross-supplier credential/session leak intentionally avoided via isolated namespace + cache key prefix `ejmiral_aerost_token:`

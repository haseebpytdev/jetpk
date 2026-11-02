# EJMIRAL AEROST — Preflight

DATE: 2026-11-02  
BRANCH: `feat/jp-ejmiral-aerost-01`  
BASE_SHA: `884ee8145fd2d58e90c090dfbb62fe394804a799`

## Safety

- UAT/STAGING mutations authorized for certification only
- PRODUCTION supplier mutations: NOT AUTHORIZED
- No PR / merge / deploy in this loop
- No secrets/PNR/tokens in evidence

## Isolation

- Supplier key: `ejmiral_aerost`
- Namespace: `app/Services/Suppliers/EjmiralAerost/`
- Does not reuse OneAPI transport/session classes
- OneAPI certification lives on separate branch `feat/jp-oneapi-e2e-certification-01`

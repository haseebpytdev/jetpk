# Dual Supplier Credential Security

DATE: 2026-11-02

| Gate | Value |
|------|-------|
| CREDENTIAL_STORAGE_ENCRYPTED | YES — `SupplierConnection` credentials via encrypted array cast |
| SECRET_LOG_LEAK | 0 target — auth/HTTP logs use `SensitiveDataRedactor`; passwords never intentional log fields |
| Blank password on edit | Retains existing encrypted value (`OneApiSupplierConnectionNormalizer` / existing credential-update convention) |
| UI password display | Never shows stored passwords |
| Cross-supplier mixing | Forbidden — OneAPI REST/SOAP identities and AEROST Sub Agent credentials remain isolated |

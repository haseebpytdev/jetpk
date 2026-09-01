# Saudi MOFA provider

`App\Services\Visa\Providers\SaudiMofaVisaProvider`

- Fixture transport by default (`OTA_VISA_SAUDI_MOFA_TRANSPORT=fixture`)
- Live transport (`LiveVisaHttpTransport`) denied unless policy gate allows
- Host/path allowlist + redirect allowlist
- Signature detection → `ProviderChanged` (never misreported as not-found)
- Result parser for structured HTML fields
- Document = sanitized HTML (scripts stripped)

| Flag | Value |
|---|---|
| SAUDI_MOFA_PROVIDER_IMPLEMENTED | YES |
| AUTOMATED_TEST_MOFA_NETWORK_CALLS | 0 |
| CAPTCHA_AUTOMATION_CODE | 0 |

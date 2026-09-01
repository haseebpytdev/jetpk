# Terms / authorization review

Sources reviewed (current official materials):

1. Visa Platform usage policy PDF: `https://visa.mofa.gov.sa/Templates/Usage%20policy%20-%20AR.pdf`
2. MOFA portal Usage Policy: `https://mofa.gov.sa/en/ministry/Pages/UsagePolicy.aspx`
3. User manual (process description only): `https://visa.mofa.gov.sa/Templates/UserManualEn.pdf`

## Findings (non-legal advice)

| Topic | Observation |
|---|---|
| Intended use | Portal described as for **personal use** |
| Automation / interference | Users agree not to use any device, software, or routine to interfere or attempt to interfere with proper working of the portal |
| Load | Unreasonable / disproportionately large load prohibited |
| Framing / caching / linking | Framing prohibited without prior MOFA permission; hyperlinking/framing requires specific request and permission |
| Commercial reuse of contents | Selling/licensing/copying/reproducing portal contents for public or commercial purposes requires prior written MOFA permission |
| Automated access | **Not explicitly licensed** for third-party server-mediated commercial relay |
| Third-party relay | **Not explicitly permitted** |
| PDF relay / redistribution | Commercial reproduction/distribution of materials appears restricted without written permission |

## Return flags

| Flag | Value |
|---|---|
| MOFA_TERMS_AUTOMATED_ACCESS | **UNCLEAR_LIKELY_RESTRICTED** |
| MOFA_TERMS_THIRD_PARTY_RELAY | **UNCLEAR_NOT_EXPLICITLY_PERMITTED** |
| MOFA_TERMS_PDF_RELAY | **UNCLEAR_WRITTEN_PERMISSION_LIKELY_REQUIRED** |
| MOFA_POLICY_CERTAINTY | **LOW** |
| PRODUCTION_POLICY_APPROVAL_REQUIRED | **YES** |

This is a **business/legal approval gate**, not a pure engineering failure.

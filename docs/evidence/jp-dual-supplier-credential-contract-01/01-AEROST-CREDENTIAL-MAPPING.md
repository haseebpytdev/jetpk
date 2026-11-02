# AEROST Credential Mapping

DATE: 2026-11-02  
AEROST_BRANCH: `feat/jp-ejmiral-aerost-01`  
AEROST_HEAD: `456b3d27c9cc6f9de9961e65b12e2e98f86fe3a2`

## Proven vs unproven hosts

| Gate | Value |
|------|-------|
| AEROST_PSA_PORTAL_URL_PROVEN | YES — `https://sa.uat.ejmiral.com:7777` (administrative PSA portal only) |
| AEROST_API_BASE_URL_PROVEN | NO — docs define `{{API_URL}}/api/ota/v1/...` but do not prove API host equals PSA portal |

Do **not** default `api_base_url` to the PSA portal URL.

## Runtime SupplierConnection (Sub Travel Agent only)

```
username=<Sub Travel Agent user>
password=<Sub Travel Agent password>
api_base_url=<vendor-confirmed API host>
```

| Gate | Value |
|------|-------|
| PSA_CREDENTIALS_ACCEPTED_FOR_API | NO |
| SUB_AGENT_CREDENTIALS_REQUIRED | YES |
| AEROST_RUNTIME_CREDENTIAL_MODEL_CORRECT | YES (verified on AEROST branch: Sub Agent fields only; `default_api_base_url` empty env) |

PSA passwords must never be stored on the AEROST runtime connection.

# Module boundary

## Independence

| Flag | Value |
|---|---|
| MOFA_MODULE_REQUIRED_FOR_OTA_CORE | **NO** |
| MOFA_MODULE_REQUIRED_FOR_AI | **NO** |
| AI_REQUIRED_FOR_MOFA | **NO** |
| CHATWOOT_REQUIRED_FOR_MOFA | **NO** |
| MOFA_INSTALL_OPTIONAL | **YES** |
| MOFA_UNINSTALL_CORE_SAFE | **YES** |
| MOFA_MODULE_COUPLED_TO_AI | **NO** |
| SERVER_SIDE_ADAPTER_RECOMMENDED | **YES** |
| IFRAME_RECOMMENDED | **NO** (`X-Frame-Options: DENY`) |

## Conceptual tree

```
OTA Core
└── Optional Modules
    ├── AI Assistant (Ask JetPakistan)
    ├── Saudi MOFA Visa
    ├── Chatwoot
    └── future adapters
```

## Existing project convention to reuse (do not invent competing framework)

JetPakistan already has `App\Support\Platform\PlatformModuleRegistry` + admin module gates.

Future MOFA work should register as an **optional** platform module key (e.g. `saudi_mofa_visa`) with:

- Admin ON/OFF without source redeploy
- No dependency edges to AI / Chatwoot / flights / groups
- Disabling must not break Flights, Groups, Booking, Customer, Agent, Admin, Ask JetPakistan

## Provider architecture (design-only; not production-wired)

Prefer isolated contracts under a Visa module namespace conceptually equivalent to:

```
Modules/Visa/
  Contracts/VisaLookupProvider
  Providers/SaudiMofaVisaProvider
  DTO/{VisaLookupSession,VisaLookupRequest,VisaLookupResult,VisaDocument}
  Exceptions/{CaptchaInvalid,CaptchaExpired,VisaNotFound,ProviderChanged,ProviderUnavailable}
```

Practical placement options later (02+ only after policy gate):

- `app/Services/Visa/...` behind PlatformModuleGate, **or**
- dedicated optional package/path not loaded when module OFF

## Client boundary

Customer browser → JetPakistan backend → MOFA

Do not call MOFA directly from browser JS if server adapter is used.

## Public UX (draft only — not published)

Visa → Saudi Visa Search → human captcha → result summary (if structured) → View/Download official PDF → optional local image copy → attribution:

> Visa information supplied by the Saudi Ministry of Foreign Affairs.

## AI / Chatwoot

- Ask JetPakistan may later deep-link to Visa Search UI only
- No AI→MOFA calls, no LLM captcha/passport handling
- Support via generic Contact Support boundary only

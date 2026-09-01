# Module architecture

```
OTA Core
└── Optional Modules
    ├── AI Assistant
    ├── Visa (public_visa)
    │    └── Providers: saudi_mofa | mock
    └── Chatwoot (future)
```

| Flag | Value |
|---|---|
| VISA_MODULE_REQUIRED_FOR_OTA_CORE | NO |
| VISA_MODULE_REQUIRED_FOR_AI | NO |
| AI_REQUIRED_FOR_VISA | NO |
| CHATWOOT_REQUIRED_FOR_VISA | NO |
| VISA_INSTALL_OPTIONAL | YES |
| VISA_UNINSTALL_CORE_SAFE | YES |
| CLIENT_NAME_HARDCODED_FOR_MODULE | NO |
| PUBLIC_LAYER_DEPENDS_ON_PROVIDER_INTERFACE | YES |

Platform module key: `public_visa` (defaultEnabled=false). Config: `config/visa.php`.

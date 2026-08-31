# JetPakistan AI Assistant (JP-AI-ASSIST)

Isolated travel-shopping / help assistant living inside `ota-jetpk`.

## Hard rules

- Same VPS only (`/home/pkjetp/jetpk_app/ai-assistant`)
- Localhost inference only (never public firewall port)
- No supplier mutation tools
- No fare invention — prices from JetPakistan search tools only
- Core OTA must survive AI failure (load shedding)

## Layout

```
ai-assistant/
  README.md
  gateway/          # HTTP gateway (Laravel talks here on 127.0.0.1)
  runtime/          # process wrappers / systemd unit templates
  tools/            # typed tool schemas (search_flights, search_groups, …)
  policies/         # safety / injection refusal policies
  prompts/          # system prompts
  knowledge/        # approved FAQ/CMS excerpts only
  ranking/          # deterministic cheapest/fastest/best-value
  adapters/
    web/
    whatsapp/       # contract only in V1
  tests/
  config/
  models/           # GGUF binaries — NEVER commit
  data/             # temp conversation — NEVER commit
```

## Status

Architecture scaffold for JP-RELEASE-ENHANCEMENTS-01. Runtime model deploy is gated by capacity audit.

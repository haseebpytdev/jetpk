# Hosted AI Embed — Client Integration Guide

This document describes how a future client integrates the **hosted JetPakistan AI embed product** into their website. It is preparation-only: no production client is activated until onboarding is complete.

## What the client receives

| Delivered | Not delivered |
|-----------|---------------|
| iframe URL (`https://AI_HOST/integrations/ai/EMBED_KEY`) | Laravel / PHP backend source |
| Rendered HTML, CSS, browser JavaScript | AI orchestrator, prompts, policy code |
| Documented `postMessage` events | RAG pipeline, knowledge storage |
| Integration checklist | Database models, supplier adapters |
| Domain allowlisting instructions | Server credentials or API secrets |

Browser-delivered assets are inspectable by design. Security relies on origin allowlists, opaque server-side sessions, and capability gates—not on hiding frontend code.

## What the client does **not** need

- No repository access
- No secret API key inside HTML
- No backend deployment on the client side
- No changes to JetPakistan production Ask FAB (that remains a separate public integration)

## iframe snippet (example)

Replace `AI_HOST` and `EMBED_KEY` with values issued during onboarding.

```html
<iframe
  src="https://AI_HOST/integrations/ai/EMBED_KEY"
  title="AI Support"
  width="100%"
  height="640"
  style="border:0;max-width:480px;"
  loading="lazy"
  referrerpolicy="strict-origin-when-cross-origin">
</iframe>
```

Recommended container: fixed or flexible height with `min-height: 480px` on mobile.

## Domain allowlisting (required)

Each tenant stores an explicit HTTPS origin allowlist, for example:

- `https://www.client.example.com`
- `https://app.client.example.com`

Rules:

- **Exact scheme + host (+ port if non-default)** — no wildcards
- **`allowed_origins=[]` means fail closed** — iframe/API reject all parent origins until domains are added
- Adding a domain is an **operational change** (tenant record update), not a code deployment

## Session model

1. Parent page loads iframe from our host.
2. iframe JavaScript detects parent origin and requests an opaque session token from  
   `POST /api/embed/ai/{EMBED_KEY}/session`
3. Subsequent API calls send:
   - `X-JP-AI-Embed-Session: <opaque token>`
   - `X-JP-AI-Embed-Parent-Origin: <parent origin>`
4. Token is stored in iframe `sessionStorage` (no third-party cookies).
5. Sessions expire server-side; origin binding is enforced on every request.

## API surface (hosted)

All routes are scoped by embed key, not tenant name:

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/integrations/ai/{embedKey}` | iframe page |
| POST | `/api/embed/ai/{embedKey}/session` | Create session |
| POST | `/api/embed/ai/{embedKey}/chat` | Send message |
| GET | `/api/embed/ai/{embedKey}/messages` | Poll transcript |
| POST | `/api/embed/ai/{embedKey}/clear` | Start new chat |
| POST | `/api/embed/ai/{embedKey}/handoff` | Request human support |

Unknown or revoked embed keys return **404** (no tenant enumeration).

## CSP / framing expectations

- Global site pages keep `X-Frame-Options: SAMEORIGIN`.
- Embed iframe route sends **route-scoped** `Content-Security-Policy` with `frame-ancestors` listing the tenant allowlist only.
- Client pages embedding the iframe do not need to weaken their own CSP for our assets (assets load inside the iframe origin).

## postMessage contract (optional parent integration)

The iframe may emit events to the parent origin (never `*`). Payload shape:

```json
{
  "type": "jp-ai-embed-ready | jp-ai-embed-resize | jp-ai-embed-handoff | jp-ai-embed-error",
  "tenantPublicId": "uuid",
  "height": 640,
  "detail": {}
}
```

Parents should verify `event.origin` matches the configured AI host before trusting messages.

## Capabilities (tenant-specific)

Each tenant enables capabilities explicitly. Default for new tenants: **disabled**.

| Capability | Meaning |
|------------|---------|
| `general_ai` | Basic assistant replies |
| `knowledge` | Tenant-scoped FAQ / RAG namespace |
| `lead_capture` | Conversational lead capture |
| `flight_search` | Read-only flight search (JetPakistan adapter only today) |
| `booking_lookup` | Secure booking lookup adapter |
| `support_handoff` | Human handoff workflow |

Tenants never inherit JetPakistan capabilities accidentally.

## Branding (data only)

Configured server-side per tenant:

- display name, assistant name
- logo URL (HTTPS)
- primary theme color
- welcome text, optional support label

Raw HTML/JS injection from clients is **not** supported.

## Privacy & security responsibilities

**Our platform**

- Host AI runtime, knowledge namespace, and audit logs
- Enforce origin + session + capability gates
- Isolate tenants (conversations, knowledge, leads, search, booking lookup)
- Rotate embed keys without exposing raw keys in logs

**Client**

- Embed only on HTTPS origins they control
- Provide accurate allowed domains before go-live
- Respect read-only embed contract (no booking/payment mutations via chat)
- Handle any PII collected through their own privacy policy

## Onboarding checklist (internal)

Use this template when a real client is ready—**do not create fictional production tenants during product prep**.

1. Client legal / commercial agreement
2. Client display name, assistant name, logo, theme
3. Allowed HTTPS origin(s)
4. Enabled capabilities + adapters
5. Knowledge namespace + approved content upload
6. Support handoff destination (email, CRM, webhook, etc.)
7. Issue embed key; share iframe snippet only
8. Staging verification on client staging origin
9. Production enable: `embed_enabled=true` for tenant only (global `AI_EMBED_ENABLED` remains operator-controlled)
10. Post-go-live monitoring of embed audit events

## Configuration reference (operators)

| Setting | Purpose |
|---------|---------|
| `AI_EMBED_ENABLED` | Global kill switch (default `false`) |
| `AI_EMBED_PUBLIC_BASE_URL` | Snippet/API base (defaults to `APP_URL`) |
| `AI_EMBED_SESSION_TTL` | Session lifetime seconds |

Operational tenant management (preferred over env per client):

```bash
php artisan ai-embed:tenant-sync-jetpakistan
php artisan ai-embed:tenant-upsert {slug} --display-name= --assistant-name= --origin= --capability= --issue-key
php artisan ai-embed:key-rotate {slug}
php artisan ai-embed:tenant-status {slug}
```

## Key rotation

- Old embed key → 404 after revocation
- New key → active when tenant is operational and origins configured
- Key alone is **not** authentication; origin + session remain required

## Source-code boundary summary

**Client receives:** iframe URL, rendered UI assets, documented events.  
**Client does not receive:** backend source, prompts, RAG, credentials, supplier/booking internals.

---

*Product phase: Embed-02 client-agnostic hosted core. Production activation requires explicit operator action after real client onboarding.*

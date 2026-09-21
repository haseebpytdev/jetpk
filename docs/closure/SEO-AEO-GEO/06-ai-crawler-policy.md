# AI crawler policy (GEO)

**Status:** PASS (intentional discoverability)  
**Date:** 2026-09-21  
**Owner preference:** Public SEO pages should be discoverable by search and AI citation crawlers.  
**Hard constraint:** Private / transactional disallows must not be weakened.

## Sources of truth

| Artifact | Role |
|---|---|
| `frontend/app/robots.ts` | Production `/robots.txt` from Next (non-prod: disallow all) |
| `public/robots.txt` | Static mirror + comments; keep rules identical to production allowlist |
| `frontend/app/sitemap.ts` | XML sitemap for indexable public paths (+ Laravel `sitemap-routes`) |
| `docs/closure/SEO-AEO-GEO/01-route-inventory.md` | Path class / indexability inventory |

## Discovery vs training

| Goal | Policy |
|---|---|
| **Search / discovery / citation** | `User-agent: *` Allow `/` for public SEO surfaces. Named bots `GPTBot`, `ClaudeBot`, and `Google-Extended` get the **same** Allow + private Disallow as `*` so intent is explicit (not an accidental inherit-only gap). |
| **Training / grounding products** | We do **not** add blanket training bans that would hide public brand pages. `Google-Extended` is listed with the same public allow to make the choice intentional and auditable. Revisit only if legal/product requires a training-specific deny. |
| **Private / ops** | `/admin`, `/customer`, `/agent`, `/staff`, booking/checkout, search-session, API, and related paths stay Disallow for `*` and named AI bots. Application auth remains authoritative. |

## Explicit non-goals

- Do not open private disallows for any bot.
- Do not invent keyword filler or fake schema to chase AI answers.
- Do not treat robots.txt as authorization.

## Verification

After deploy, confirm `https://jetpakistan.pk/robots.txt` lists named AI agents with the same private Disallow set as `*`, and that `Sitemap:` points at `/sitemap.xml`.

# Phase 0 · Document 07 — Branding Leakage Audit

> **REVISION 1** — Leakage scope now explicitly covers **all** authenticated roles
> (Customer, Agent, Agent Staff, Internal Staff, Admin) **and** the
> Authenticated-Entry surfaces. The 4 `haseeb` hits previously noted in Blade are
> in auth/error/registration layouts (`layouts/auth`, `ui/site/v2/layouts/auth`,
> `frontend/agent-registration/submitted`, `errors/layout`) — now formally tracked
> as auth-surface items to eyeball (likely asset paths/comments, not visible brand).

**Phase:** JETPK-DASHBOARD-UI-FOUNDATION-ROUTE-PARITY-AND-RESPONSIVE-REDESIGN
**Stage:** Phase 0 (audit & architecture) · **Baseline:** `claude/ui-master` @ `6fbfae4`

> **Rule (CLAUDE.md):** the public product is **JetPakistan**. `Parwaaz Travels`,
> `YoursDomain`, `YD Travel`, `haseeb-master`, and unrelated client branding must
> not appear in JetPakistan **UI, emails, URLs, or public content**. Legacy
> compatibility code may remain where required, but must not leak into those
> surfaces.

---

## 1. White-label context

JetPakistan is one tenant of a white-label base. `clients/` holds three profiles:

| Client dir | `company_name` |
|---|---|
| `clients/_template` | `REPLACE_CLIENT_NAME` (scaffold) |
| `clients/client-demo` | `Demo Travel Agency` |
| `clients/jetpk` | **JetPakistan** |

The base was derived from a "master" (the `haseeb-master` lineage). The codebase
therefore legitimately contains master/legacy references in **non-user-facing**
layers, plus purpose-built **parity-audit tooling** that compares JetPakistan
against the master (e.g. `app/Support/Audits/HaseebMasterRouteSafetyAuditService.php`,
`HaseebMasterRouteSafetyCatalog.php`, `JetpkMasterTraceAuditService.php`).

---

## 2. Current leakage state (grep, baseline `6fbfae4`)

**Whole-repo counts** (excluding `.git`, `node_modules`, `vendor`):

| Term | Total hits | Interpretation |
|---|---|---|
| `Parwaaz` | 103 | Mostly config/tooling; **0 in Blade, 0 in emails** |
| `YoursDomain` | 29 | Config/tooling; 0 in Blade/emails |
| `YD Travel` | 23 | Config/tooling; 0 in Blade/emails |
| `haseeb-master` | 240 | Master lineage refs in config/services/audits |
| `haseeb` (any) | 375 | Superset of above; config, client resolver, audits |

**User-facing surfaces (the surfaces the rule actually governs):**

| Surface | Parwaaz | YoursDomain | YD Travel | haseeb |
|---|---|---|---|---|
| Rendered Blade (`resources/views/**/*.blade.php`) | 0 | 0 | 0 | **4** |
| Email templates (`resources/views/emails/`) | 0 | 0 | 0 | 0 |
| Public assets (`public/`) | 1 *(protective — see §4)* | 0 | 0 | — |

**Verdict:** the dashboard/portal UI and all emails are effectively **clean** of
the forbidden brands at baseline. The residual references live in the
legacy-compat/config/audit layers, which the rule explicitly permits.

---

## 3. Items to verify in the implementation phase (not necessarily leaks)

The 4 `haseeb` hits in Blade are in **non-dashboard** layouts and are most likely
asset paths, class names, or comments rather than visible brand text. Confirm
each renders no visible "haseeb"/"master" string to an end user:

- `resources/views/ui/site/v2/layouts/auth.blade.php`
- `resources/views/layouts/auth.blade.php`
- `resources/views/frontend/agent-registration/submitted.blade.php`
- `resources/views/errors/layout.blade.php`

None are dashboard views, so they are outside the redesign's edit scope; they are
listed only so the branding gate has a complete picture.

---

## 4. Existing defensive measure (good practice already in place)

The single `public/` hit is **not** leakage — it is protective CSS in
`public/themes/frontend/jetpakistan/css/booking.css`:

```
/* Hide Master/Parwaaz brand leakage on JetPK booking shells */
.jp-site-main .ota-checkout-brand img[alt*="Parwaaz"], … { /* hidden */ }
```

The team already actively suppresses stray Parwaaz-branded imagery on JetPakistan
booking shells. The redesign must **preserve** this rule (and equivalents) — do
not remove anti-leakage CSS while restyling.

---

## 5. Branding gate for the redesign (method to re-run)

Before each implementation commit and at phase completion, run against changed
files and against the dashboard/email trees:

```bash
grep -rIi --include=*.blade.php -E "Parwaaz|YoursDomain|YD Travel|haseeb" \
  resources/views/dashboard resources/views/mobile resources/views/emails
# Expected: no NEW hits vs baseline (dashboard/mobile/emails stay at 0)
```

Additional checks:
- No forbidden brand in generated URLs (all links via `client_route()`; brand
  never appears in a path).
- No forbidden brand in visible text, `alt`, `title`, `aria-label`, or `<meta>`.
- Logos/favicon come from `clients/jetpk/branding.json`
  (`logo/logo.svg`, `favicon/favicon.ico`) — never a master/other-client asset.
- Footer text = JetPakistan (`"JetPakistan — your gateway to seamless travel."`).

Optionally leverage the repo's own auditors (`JetpkMasterTraceAuditService`,
`HaseebMasterRouteSafetyAuditService`) locally for a deeper parity/leak sweep.

---

## 6. Phase-0 disposition

Documentation only. **No file is modified in Phase 0.** Baseline branding state:
dashboard UI and emails clean; residual references are permitted legacy-compat in
config/tooling; a protective anti-leakage CSS rule already exists and must be
preserved. The gate in §5 is the standing check for every redesign commit.

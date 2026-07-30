# Signup — Account Types, Validation, and Approval Visual Contract (JP-UI-05)

## Scope

Customer registration (`/register`), agent registration (`/agent/register`), validation states, and post-submit outcomes. Mockup **#7** alignment.

## Module

`frontend/features/auth/components/` — `CustomerRegistrationForm`, `AgentRegistrationForm`, shared `AuthPageShell`.

## Account types

| Route | Form | Audience |
|-------|------|----------|
| `/register` | `CustomerRegistrationForm` | Retail customers |
| `/agent/register` | `AgentRegistrationForm` | Travel agents / agencies |

### Unsupported types (must remain hidden)

The following account-type selectors must **not** appear unless Laravel explicitly enables them:

- `account-type-family-manager`
- `account-type-business-traveler`

Visual audit scenario `signup-unsupported-account-types-hidden` asserts these `forbiddenTestIds`.

## Split-screen layout

Same `AuthPageShell` contract as login:

- Illustration panel with `SIGNUP_BENEFITS` (customer) or `AGENT_SIGNUP_BENEFITS` (agent)
- `AuthFormCard` on the right (mobile: form first)

## Customer registration fields

| Field | Required | Validation |
|-------|----------|------------|
| First name | Yes | Non-empty |
| Last name | Yes | Non-empty |
| Email | Yes | Valid email format |
| Password | Yes | Laravel password rules |
| Confirm password | Yes | Must match password |
| Terms consent | Yes | Checkbox "I accept…" |

## Agent registration fields

Agent form includes agency/business fields per Laravel contract (company name, contact details, etc.). Visual parity uses same card chrome; field set remains Laravel-authoritative.

## Validation states

| State | Trigger | Visual |
|-------|---------|--------|
| Empty submit | Click "Create account" with empty fields | Inline errors + alert summary |
| Password rules | Short password entered | Password strength/rules hint |
| Consent error | Submit without terms checkbox | Alert for consent requirement |
| Submitting | Slow register fixture | Disabled CTA, loading label |

## Post-submit outcomes

| Outcome | Visual |
|---------|--------|
| Verification required | Success message directing email verification |
| Agent pending approval | Laravel-provided pending message (no invented timeline) |

## Benefits panel

### Customer (`SIGNUP_BENEFITS`)

1. Faster bookings
2. Save travelers
3. Manage bookings
4. Exclusive offers

### Agent (`AGENT_SIGNUP_BENEFITS`)

Agency-focused benefits per `auth-benefits.ts` (bolt, users, calendar, tag icons).

## Social / OAuth on signup

Same rule as login: OAuth row hidden unless Laravel configures providers. `oauth-google` included in signup `forbiddenTestIds` when unconfigured.

## Typography and spacing

- Form field spacing: `mt-1` on inputs below labels
- Section title in `AuthFormCard`: `text-jp-lg font-semibold`
- Error text: `text-jp-sm text-jp-danger`
- Consent checkbox: full label clickable; `focus-visible` on control

## Test IDs

| testId | Element |
|--------|---------|
| `auth-page-shell` | Split layout root |
| `auth-form-card` | Registration card |

## Related scenarios

Signup family (20): layout matrix (12) + customer, agent, validation-errors, password-rules, consent-error, submitting, success-verification, unsupported-hidden.

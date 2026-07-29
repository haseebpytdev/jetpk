# Agent Wallet, Ledger, and Deposits Contract

## Route mapping

Laravel Blade paths differ from Next.js information architecture:

| Concern | Laravel path | Next.js path |
|---------|--------------|--------------|
| Wallet overview | `GET /agent/wallet` | `/agent/wallet` |
| Full ledger | `GET /agent/ledger` | `/agent/wallet/ledger` |
| Deposit list | `GET /agent/deposits` | `/agent/deposits` |
| New deposit | `GET /agent/deposits/create` | `/agent/deposits/new` |
| Submit deposit | `POST /agent/deposits` | `/agent/deposits/new` (form POST) |

All read endpoints accept `?format=json`.

## Authorization

| Endpoint | Permission | Platform module |
|----------|------------|-----------------|
| Wallet | `agent.wallet.view` | `agent_wallet` |
| Ledger | `agent.ledger.view` | `agent_ledger` |
| Deposits list | `agent.wallet.view` | `agent_deposits` |
| Deposit create/store | `agent.payments.upload` | `agent_deposits` |

Staff without `agent.wallet.view` receive 403 on wallet JSON (verified in contract tests).

## Wallet overview

`GET /agent/wallet?format=json`

Response:

- `summary` — `balance`, `available_balance`, `pending_deposits`, `credit_limit`, `credit_enabled`, `currency`, `wallet_status`, `last_updated`
- `pending_deposit_count`
- `recent_ledger_entries` — last 10 transactions (abbreviated ledger rows)
- `capabilities` — `can_view_ledger`, `can_create_deposit`
- `quick_actions` — links to `/agent/wallet/ledger` and `/agent/deposits/new` when permitted

## Ledger index

`GET /agent/ledger?format=json`

Query: `page`, `type`, `q` (search).

Response:

- `summary` — balance snapshot
- `filters` — applied filters echo
- `allowed_filters` — type and status enums
- `entries[]` — paginated ledger rows
- `pagination`

Each entry:

- `reference`, `date`, `type`, `direction` (`credit` | `debit`), `amount`, `currency`
- `balance_after`, `description`, `status`
- `booking_reference` — from transaction meta when present
- `deposit_reference` — from linked deposit request
- `created_by` — author name when available

## Deposits list

`GET /agent/deposits?format=json`

Query: `page`.

Response:

- `summary` — balance, pending deposits, currency
- `deposits[]` — paginated deposit requests
- `pagination`

Each deposit:

- `deposit_reference`, `requested_amount`, `currency`, `date`, `method`
- `proof_status` — `uploaded` | `missing`
- `approval_status` — `{ code, label }`
- `credited_amount` — when approved
- `rejection_reason` — when rejected
- `next_action` — `{ code, label }`

## Deposit create

`GET /agent/deposits/create?format=json`

Returns form field constraints and `submit_url: /laravel/agent/deposits`.

Fields:

- `amount` — required, min 1, max 99999999.99
- `payment_method` — optional, max 100 chars
- `reference` — optional bank reference, max 255
- `agent_note` — optional, max 2000
- `proof` — optional file, max 5120 KB, mimes jpg/jpeg/png/pdf/webp

`POST /agent/deposits` — multipart form with CSRF. Success returns `{ ok: true, redirect_url }`.

## Wallet summary on dashboard

Dashboard overview embeds `wallet_summary` and metrics `wallet_balance`, `available_balance`, `pending_deposits` when user has wallet permission.

## Data scoping

All wallet/deposit/ledger queries scoped to authenticated agency's `Agent` record. No cross-agency leakage.

## Blade fallback

Wallet, ledger, and deposit Blade views remain available without `format=json`.

## Excluded

- Accounting ledger (`/agent/accounting/ledger`) — separate admin-style ledger; not in Next.js phase
- Finance statement export — Blade only
- Ledger manage actions (`agent.ledger.manage`) — no Next.js UI in this phase

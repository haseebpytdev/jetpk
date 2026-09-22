# JP-AI-PUBLIC-SUPPORT-READONLY-BOOKING-SCOPE

Authoritative scope addendum for temporary public Ask JetPakistan activation.

## Allowed (read-only / support)

- Governed RAG / travel information within approved scope
- Read-only flight search after confirmation flow
- Customer Query / lead capture when commercially appropriate
- Human handoff to support
- Existing booking lookup after **reference + (email OR phone)** ownership match
- Process guidance for booking, payment, baggage, changes (no execution)

## Forbidden (hard-locked)

`BOOKING_MUTATIONS=0`, `HOLD_MUTATIONS=0`, `PNR_CREATION=0`, `TICKET_MUTATIONS=0`,
`PAYMENT_MUTATIONS=0`, `CANCEL_MUTATIONS=0`, `REFUND_MUTATIONS=0`, `VOID_MUTATIONS=0`,
`EXCHANGE_MUTATIONS=0`

Enforced via `AiAssistantSettingsService::hardLockedWriteCapabilities()` (all `locked=true`, `activatable=false`).

## Booking lookup contract

| Layer | Authority |
|-------|-----------|
| Tool | `AiAssistantBookingLookupTool` |
| Service | `GuestBookingAccessService::findBookingForLookup()` |
| Presenter | `AiAssistantBookingChatPresenter` (customer-safe summary only) |

Verification requires **booking reference + matching email OR phone**. Name alone is not proof.
Phone matching normalizes PK local (`03…`) and E.164 (`+92…`) via `PhoneNumberNormalizer` inside `GuestBookingAccessService`.
Failed verification returns a generic mismatch message — no existence leak, no field-level hints.

## Authenticated users

Conversation may carry `user_id`; lead/query services use authenticated identity where applicable.
Cross-user / IDOR lookup is blocked by visitor hash + conversation ownership on public chat APIs.

## Privacy

Do not expose passport numbers, payment instruments, supplier credentials, internal notes, or other passengers' PII in AI responses.

## Production activation note

`GENERAL_PUBLIC_ACTIVATION` remains **NOT_AUTHORIZED** until explicit owner approval.
Contract verification runs via PHPUnit (`PublicAiAssistantTest`, presenter unit test) locally;
live anonymous FAB checks require admin public mode + env hard-allow.

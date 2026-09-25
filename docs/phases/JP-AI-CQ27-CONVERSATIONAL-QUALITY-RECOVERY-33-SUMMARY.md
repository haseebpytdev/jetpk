# JP-AI-CQ27-CONVERSATIONAL-QUALITY-RECOVERY-33 SUMMARY

## Phase
JP-AI-CQ27-CONVERSATIONAL-QUALITY-RECOVERY-33

## Branch
`work/jp-ai-cq27-conversation-quality-33`

## Base
`dceaa8d3f235ee6a8d5b4c23ac6513f4682f9cbb`

## Objective
Close conversational quality/state/routing failures from real mobile transcripts without weakening safety gates. No production deploy. Embed stays off.

## Included
- Open-jaw / multi-leg detection and preservation
- Explicit route precedence over stale state
- Date required before conversational search (no silent +7)
- Cabin/business persistence + confirmation + deep-link
- `last_flight_search` active context follow-ups
- Active travel follow-up before open-domain
- Lead help-first for shopping refinements / dates
- Explicit Resume AI for `WAITING_FOR_HUMAN`
- Affirmative without pending action (lead consent preserved)
- Current-unverified weather limitation
- Per-conversation chat write lock (409)
- CQ27 regression suite A–K

## Excluded
- Production deploy
- iframe / embed activation
- Live weather provider
- Unrelated AI architecture redesign
- Supplier mutations

## Tests executed
- `ConversationQuality27RecoveryTest`
- `FlightSearchConfirmationAndHandoffClosure29Test`
- `ConversationQuality26Test` / `ConversationQuality26AiFirstTest`
- `ConversationalLeadCaptureTest`
- `PublicAiAssistantTest`
- `AiEmbedSecurityTest` / `AiEmbedChatTest`
- `TenantIsolationTest` / `EmbedKnowledgeIsolationTest`

## Status
Quality recovery implemented on branch. **DEPLOYED=NO**.

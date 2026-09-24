# R7 MESSAGE FIDELITY + GROUNDING

## ARTIFACT_ROOT_CAUSE

UAT/canary harness JS string coercion: a non-string `undefined` value concatenated
into the outgoing prompt produced the literal prefix `undefined` (e.g.
`undefinedWhat is JetPakistan?`). Exact-half doubles (`I need helpI need help`)
came from double fill/paste into the composer without clearing residual text.

Prior FE/BE sanitizers (`/^(?:undefined|null)+/i` strip + exact-half dedupe)
masked the harness defect by rewriting production user text. That was wrong for
open-domain fidelity.

## DOUBLE_INPUT_ROOT_FIX

- `frontend/scripts/canary-matrix-helpers.mjs` `sendMessage`: reject non-string
  text; clear input then fill once with the exact string.
- FE `sanitizeUserOutgoing` / BE `sanitizeUserMessage`: only NUL strip, trim,
  length/HTML/injection gates — no semantic word rewrite.

## GROUNDING

`clauseSupportedByApprovedCorpus` now requires every material content token
supported (direct or small paraphrase map). Aggregate 60% overlap removed.
Hyperbolic qualifiers gated: award-winning, government-approved, largest, cheapest.

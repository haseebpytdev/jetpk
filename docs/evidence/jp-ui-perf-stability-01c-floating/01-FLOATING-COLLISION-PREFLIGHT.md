# JP-UI-PERF-STABILITY-01C — Floating collision preflight

## Bases

- `MAIN_BASE_SHA=ea712ce8e3698cf9786e1698c231d622a735e4e1`
- `PRODUCTION_FRONTEND_BASE_SHA=1dece982b1a8d07fb13426e627ed68e752a5c42b`
- `PHASE_C_PRODUCTION_MAIN_PREIMAGE_PARITY=8_OF_8_PASS`

## Baseline (pre-fix, code-proven)

On lifted flight/checkout routes the shared layout set Ask bottom and dock bottom to the same ~108px value:

| Control | Bottom |
|---|---|
| Dock (lifted) | 108px / `max(6.75rem, env(safe-area)+5.5rem)` |
| Ask (lifted) | `Math.max(108, 16+56+14)=108` |

Both occupy the same bottom-right region → `BASELINE_LIFTED_ASK_DOCK_COLLISION=YES`.

Secondary: dock-open only added `FAB_DOCK_PANEL_OFFSET_PX=72` while the panel is taller; Ask open already hid the dock.

Desktop: Ask still reserved dock stack space while dock is `lg:hidden`.

## Root cause

Independent Ask lift (`108`) did not stack above the authoritative lifted dock bottom.

## Implementation

Dock bottom is authority (`--jp-dock-fab-bottom` with safe-area expressions).

Ask bottom on sub-lg:

```text
calc(var(--jp-dock-fab-bottom) + 56px + 14px)
```

Lifted Ask = **178** (not 108). Desktop Ask uses standalone `max(18px, env(safe-area-inset-bottom))`.

Mutual exclusivity:

- Ask open → hide/close dock (existing + details sync)
- Dock open → hide Ask FAB (`data-jp-dock-open` + provider close)

`FAB_DOCK_PANEL_OFFSET_PX` removed.

Runtime files:

- `frontend/features/public-floating/public-floating-layout.ts`
- `frontend/features/public-floating/PublicFloatingLayoutProvider.tsx`
- `frontend/components/navigation/PublicFloatingActionDock.tsx`
- `frontend/features/ai-assistant/components/AskJetPakistanChat.tsx`
- `frontend/app/globals.css`
- `frontend/components/layout/PublicShell.tsx` (Playwright force-AI cookie only; inert without cookie)

Unchanged: FlightResultCard / PairReturnCard / OutboundOptionCard / fare / booking semantics / Phase-A media / Phase-B branding.

## Post-fix geometry

| State | Dock bottom | Ask bottom |
|---|---|---|
| Base sub-lg AI | 16 | 86 |
| Lifted sub-lg AI | 108 | 178 |
| Desktop | hidden | 18 (standalone) |
| Dock open | — | Ask FAB hidden |
| Ask open | dock hidden | panel |

## Horizontal overflow

Phase-C suite: `scrollWidth <= clientWidth + 1` on home/login/results/passengers/review/return/groups fixtures across 320–1440. No floating-owned overflow offenders. External content overflow not proven in this pass → `PHASE_C_HORIZONTAL_OVERFLOW_EXTERNAL=NO` for measured routes.

## Card width

Flight result cards untouched; Phase-C results fixture asserted card width stable within 1px during measurement.

## Gates

- Unit layout: PASS (lifted Ask=178)
- FAB static regression: PASS
- Phase-C Playwright: 20/20 PASS
- Typecheck / lint / Next build: PASS (pre-existing unused-var warnings only)
- Phase-A prod media check: derivatives YES; giant original NO
- Phase-B prod SSR logos: correct agency logo on `/`, `/login`, `/flights/results`

## Production

`PRODUCTION_DEPLOYED=NO` — selective candidate only.

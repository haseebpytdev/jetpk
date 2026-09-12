# Real Device Owner QA Checklist — JP-UI-RESPONSIVE-ICON-MEDIA-CLOSURE-01

**Status:** `REAL_DEVICE_OWNER_QA=PENDING_OWNER` — do not mark PASS until verified on a physical phone.

## Device

- [ ] Physical iPhone or Android phone (not desktop emulation only)
- [ ] Safari or Chrome mobile browser
- [ ] Portrait and landscape

## FAB geometry (critical)

On `/` and `/support` with Ask JetPakistan enabled:

- [ ] Green menu FAB and Ask FAB are both visible without scrolling to the footer
- [ ] Minimum ~12px visible gap between FAB bounding boxes (no overlap)
- [ ] FABs stay anchored to the visible viewport while scrolling top → middle → footer
- [ ] FABs remain reachable after browser address bar expands/collapses
- [ ] Opening the menu dock does not overlap the Ask FAB
- [ ] Opening Ask JetPakistan hides the menu dock (no competing stacks)
- [ ] Soft keyboard (Ask input focused) does not trap FABs off-screen

## Icon language

- [ ] Menu/close/send/support icons look consistent (Lucide stroke style)
- [ ] No emoji standing in for UI icons
- [ ] No childish filled cartoon icons on primary chrome

## Safe area

- [ ] FABs respect notch/home-indicator on iPhone
- [ ] No FAB clipped at screen edges

## Sign-off

| Field | Value |
|-------|-------|
| Tester | |
| Device model | |
| OS / browser | |
| Build SHA tested | |
| Result | PASS / FAIL |
| Notes | |

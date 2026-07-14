# JetPakistan OTA — Claude Instructions

## Repository Scope

This repository is the standalone JetPakistan Laravel OTA.

Claude may work on:

- public JetPakistan pages
- customer dashboard
- agent dashboard
- staff dashboard
- admin dashboard
- deeper create, edit, show and detail pages
- shared UI components
- forms, tables, cards, filters, dialogs and navigation
- responsive behavior
- accessibility
- visual testing
- safe local fixtures

## Prohibited Actions

Do not:

- deploy to production
- access production SSH or SFTP
- use production credentials
- perform live supplier searches
- create live bookings or PNRs
- ticket, cancel, void or refund
- process real payments
- send production emails
- modify production data
- expose secrets
- work directly on main
- merge your own branches
- force push

## Development Safety

Use only:

- local environment files
- local SQLite databases
- seeders
- factories
- deterministic fixtures
- mocked or testing-only supplier data
- local Playwright runs

Do not make external supplier calls unless an explicitly approved phase states otherwise.

## Branding

The public product is JetPakistan.

Do not introduce visible:

- Parwaaz Travels
- YoursDomain
- YD Travel
- haseeb-master
- unrelated client branding

Legacy compatibility code may remain where required, but it must not leak into JetPakistan UI, emails, URLs or public content.

## UI Requirements

- one coherent JetPakistan design system
- shared component ownership
- consistent typography
- consistent spacing
- consistent buttons
- consistent forms
- consistent cards and tables
- responsive desktop, tablet and mobile layouts
- no persistent blue or cyan browser/framework glow
- preserve accessible keyboard focus using `:focus-visible`
- no broad global focus suppression
- no page-specific patch when the shared component owns the defect
- no placeholder or demo wording in user-facing production UI

## Phase Workflow

For every phase:

1. create a branch from `claude/ui-master`;
2. inspect before changing code;
3. identify root causes;
4. document scope and exclusions;
5. implement one phase only;
6. run relevant tests;
7. capture evidence;
8. create a phase summary;
9. commit;
10. push the phase branch;
11. stop for ChatGPT and Cursor review.

Never merge the phase yourself.

## Required Phase Summary

Create:

`docs/phases/<PHASE-NAME>-SUMMARY.md`

Include:

- phase name
- branch name
- objective
- included scope
- excluded scope
- investigation findings
- root causes
- exact files changed
- routes changed
- database changes
- backend changes
- frontend changes
- tests executed
- assertion counts
- screenshots
- responsive verification
- accessibility verification
- known limitations
- risks
- rollback instructions
- commit SHA
- final status

Do not report `FINAL_FAIL=0` unless every acceptance criterion passes.

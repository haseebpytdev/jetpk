# JetPakistan OTA — Claude Instructions

## Repository Scope

This repository is the standalone JetPakistan Laravel OTA.

Claude/Cursor may work on:

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
- JetPakistan production server management and verification under the rules below

## JetPakistan Production Access — Standing Owner Authorization

The owner has explicitly authorized Cursor to retain operational access to the canonical JetPakistan production environment for normal project work.

Canonical production target:

```text
https://jetpakistan.pk
185.215.166.176
```

Cursor may use the configured JetPakistan SSH credentials directly for scoped JetPakistan operations without asking for a new one-time SSH exception on every loop.

Allowed production operations include:

- SSH access to inspect and manage the JetPakistan server
- deployment through the established JetPakistan protected deployment workflow
- recovery/verification of established deployment wrappers and release tooling
- service/process health checks and controlled restarts/reloads when required
- PM2/public/dashboard build and runtime verification
- Laravel/Artisan/cache/route/log checks required by deployment or incident response
- read-only filesystem/configuration inspection
- browser and API production UAT against `https://jetpakistan.pk`
- production log, network, HTTP and runtime evidence capture
- read-only live supplier/search calls needed to verify fares, inventory or availability
- use of dedicated QA/admin credentials already provisioned for JetPakistan testing
- safe, reversible CMS QA draft/publish/restore actions when required by an acceptance test
- SSH/SFTP/SCP for legitimate JetPakistan operational recovery or server management when needed; application releases should still prefer the protected Git-SHA deployment path

Routine operations within this standing authorization do not require the owner to manually run SSH commands for Cursor. Cursor should carry the operation through itself when it has the required access and tooling.

### Production safety boundaries

Standing SSH/server access does **not** authorize unrelated or destructive actions. Do not:

- force push or rewrite Git history
- run destructive Git cleanup/reset against project state without explicit approval
- delete the production application tree or release history wholesale
- perform destructive database resets, truncates, drops, or bulk production-data edits
- create live bookings or PNRs solely for testing
- ticket, cancel, void or refund solely for testing
- process real payments solely for testing
- mutate supplier inventory, holds, bookings, tickets or settlement state solely for evidence
- rotate/change production credentials unless the active task requires it or the owner explicitly requests it
- expose secrets, SSH keys, passwords, tokens or private customer data in evidence
- send production emails to real customers solely for testing
- reboot/upgrade the server or perform broad package/infrastructure changes unless required by the task and properly safeguarded
- use unrelated production hosts or historical staging hosts as JetPakistan production

For risky infrastructure changes, take/verify backups first, use the smallest reversible change, and verify service health immediately afterward. Avoid full service stop/start sequences when a graceful reload is sufficient.

## Development Safety

Prefer:

- local environment files
- local SQLite databases
- seeders
- factories
- deterministic fixtures
- mocked or testing-only supplier data for development
- local Playwright runs

Live supplier/search calls are allowed only when they are read-only and materially required for production verification. Do not cross from search/availability verification into booking, hold, ticketing, payment or refund mutations.

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

1. inspect current branch, remote and production state before changing code;
2. identify root causes;
3. document scope and exclusions;
4. implement one coherent phase only;
5. run relevant tests;
6. capture evidence;
7. run the required independent verifier when the active closure requires one;
8. commit and push the phase branch;
9. deploy through the established JetPakistan production workflow when the active task authorizes deployment;
10. perform production browser/log/runtime verification;
11. record the final engineering SHA, production SHA and any evidence-only head separately.

Do not merge or force-push unless the owner explicitly requests that Git operation.

## Required Phase Summary

Create or update the phase evidence/summary location required by the active loop.

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
- screenshots/evidence
- responsive verification
- accessibility verification
- production UAT where applicable
- production engineering SHA
- remote evidence head where applicable
- known limitations
- risks
- rollback instructions
- commit SHA
- final status

Do not report `PASS` or `FINAL_FAIL=0` unless every mandatory acceptance criterion for the active closure is accounted for.

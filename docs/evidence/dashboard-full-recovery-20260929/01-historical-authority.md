# 01 — Historical authority

## Remote main
`jetpk/main` = `3de07cdb` (authoritative; matches owner-observed SHA; never move backwards).

## Recovery refs (retained)
| Ref | SHA |
|---|---|
| `jetpk/checkpoint/jetpakistan-app-release-20260921` | `78dadc7b` |
| tag `jetpakistan-recovered-final-20260921` | `78dadc7b` |
| `jetpk/backup/jetpakistan-post-recovery-20260921` | `8c5d79da` |

Dashboard tree vs checkpoint: essentially same pages; main adds jp-dash-03 acceptance scripts only.

## Phase branch with richer Admin writes (NOT on main)
`phase/jetpk-owner-uat-wave-2-admin-staff-business-closure` tip `0ebb2278`  
Merge-base with main: `f311d07b`  
Notable commits NOT ancestors of main: `47c6bbd3` (API Connections hub), `e1c727e4` (branding JSON), `6d019160` (RBAC write).

## Listed historical SHAs
All of the prompt-listed customer/agent/cms/branding SHAs are **ancestors of main**. Feature **source** for Customer/Agent portals and Admin Next **read** modules is present. Missing owner experience is primarily **nav/runtime exposure of Blade mutation surfaces** and **unmerged phase Next write hubs**.

## Go-live
On main: Blade `admin.go-live-checklist` only. No Next `system/go-live` page and no `systemGoLive` API constant — consistent (not half-wired).

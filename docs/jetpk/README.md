# JetPK deployment & completion docs

Index for **JetPakistan** (`slug: jetpk`, theme: `jetpakistan`, assets: `jetpk-assets`).

Master roadmap: [../JETPK-V1-CLIENT-UI-COMPLETION-ROADMAP.md](../JETPK-V1-CLIENT-UI-COMPLETION-ROADMAP.md)

## Checklists & inventories

| Document | Use when |
|----------|----------|
| [file-inventory.md](file-inventory.md) | Auditing JetPK-only files before deploy |
| [common-backend-inventory.md](common-backend-inventory.md) | Confirming shared backend scope |
| [public-asset-inventory.md](public-asset-inventory.md) | Uploading theme + branding assets |
| [seed-profile-checklist.md](seed-profile-checklist.md) | First-time DB + profile setup |
| [env-checklist.md](env-checklist.md) | Production `.env` for JetPK server |
| [module-toggle-checklist.md](module-toggle-checklist.md) | Enabling/disabling product modules |
| [package-separation-audit.md](package-separation-audit.md) | Standalone deploy boundary |
| [sftp-deployment-checklist.md](sftp-deployment-checklist.md) | File upload procedure |
| [rollback-checklist.md](rollback-checklist.md) | Reverting a bad deploy |
| [component-library-status.md](component-library-status.md) | `x-jp.*` component tracker |
| [dashboard-implementation-plan.md](dashboard-implementation-plan.md) | Next-phase admin/staff/agent/customer dashboard theming |

## Related platform docs

- [runtime-client-asset-resolution.md](../runtime-client-asset-resolution.md)
- [client-prefixed-route-parity.md](../client-prefixed-route-parity.md)
- [new-client-deployment-checklist.md](../new-client-deployment-checklist.md)
- [content-source-notes.md](../content-source-notes.md) — live site copy reference

## Quick commands

```bash
php artisan ota:seed-jetpakistan-client-profile
php artisan ota:client-preview-runtime-status --client=jetpk
php artisan ota:client-route-parity-status --client=jetpk --target=jetpk
```

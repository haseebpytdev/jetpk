# JP-OPS-READINESS-01 Wave 1A — Storage / Permissions

**Mode:** READ-ONLY — no chmod/chown

## Observed ownership

| Path | Mode | Owner | Notes |
|------|------|-------|-------|
| `storage/` | drwxrwxr-x | pkjetp | OK for runtime |
| `storage/logs` | drwxrwxr-x | pkjetp | writable; rotation active |
| `storage/framework` | drwxrwxr-x | pkjetp | OK |
| `bootstrap/cache` | drwxrwxr-x | pkjetp | OK |
| `storage/app/public` | drwxrwxr-x | pkjetp | 58M |
| `public/storage` | drwxr-xr-x | pkjetp | **91M — NOT a symlink** |

## Findings

1. **`public/storage` is a real directory**, not `storage:link` symlink. Content appears present (`agencies`, `airline-logos`, `client-assets`, `jetpk`, `travel-assets`). Risk: divergence vs `storage/app/public` if writers assume link.
2. No world-writable sensitive root dirs observed in sampled paths.
3. No chmod performed.

## Verdict

```
STORAGE_SYMLINK_EXPECTED=YES (Laravel convention)
STORAGE_SYMLINK_ACTUAL=NO (directory instead)
PERMISSIONS_RUNTIME=APPEAR_OK
ACTION_FOR_WAVE_1B=Investigate/reconcile public/storage vs storage/app/public before any link rewrite
```

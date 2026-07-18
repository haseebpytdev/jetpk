# Test helper scripts

## `run-feature-dirs.ps1`

Runs each `tests/Feature/<subdir>` sequentially with `php -d memory_limit=2G artisan test`.
Use when the full Feature suite OOMs on Windows.

```powershell
.\scripts\test\run-feature-dirs.ps1 -Label "integration-HEAD"
```

Output defaults to `storage/test-results/feature-dirs-<label>.json` (gitignored).

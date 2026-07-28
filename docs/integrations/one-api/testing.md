# One API testing

## PHPUnit

```bash
php artisan test --filter=OneApi
```

Uses `Http::fake` for REST and `fixture_path` on `OneApiSoapTransport` for SOAP.

## Fixtures

`tests/Fixtures/Suppliers/OneApi/` — see `FIXTURE-SOURCES.md`.

## Artisan

| Command | Purpose |
|---------|---------|
| `ota:one-api-connection-audit` | Readiness dimensions |
| `ota:one-api-test-matrix` | 24-case ISA matrix (fixture CSV) |
| `ota:one-api-search-probe` | Search dry-run / live |
| `ota:one-api-fixture-test` | Fixture file sanity |

Live probes require explicit `--confirm-live-*` flags and config gates in `config/suppliers.php`.

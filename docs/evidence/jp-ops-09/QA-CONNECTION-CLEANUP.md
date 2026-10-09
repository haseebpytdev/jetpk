# QA API connection cleanup

## Mechanism

- Command: `php artisan supplier:prune-jpqa-write-connections` (default **dry-run**)
- Execute: `--execute` (DB-only delete; **no supplier API calls**)
- Pattern: `^JPQA-WRITE-\d+-api$`
- Protected: connection id **22**, named connections (AirBlue TEST v2, JPAK Group, PIA JetPK, etc.)

## Production status (pre-deploy)

Command **not yet on production** at certified app SHA `50ae55c…`.  
Dry-run inventory from prior session (16 AirBlue rows, 11 active `JPQA-WRITE-*`, connection **22** preserved).

## Post-deploy steps

1. Deploy merged SHA containing the command.
2. `php artisan supplier:prune-jpqa-write-connections` (dry-run) — capture counts.
3. `php artisan supplier:prune-jpqa-write-connections --execute`
4. Verify `JPQA_WRITE_ROWS_REMAINING=0` and protected names unchanged.

`REAL_SUPPLIER_CALLS_DURING_CLEANUP=0`  
`EXTERNAL_SIDE_EFFECTS=0`

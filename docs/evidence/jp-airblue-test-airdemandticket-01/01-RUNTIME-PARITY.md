# Runtime parity

Deployed (with LIVE backup under `/home/pkjetp/backups/jp-airblue-airdemandticket-01-20261101T085402Z`):

| File | SHA256 | Parity |
|------|--------|--------|
| AirBlueQaTicketingScope.php | `8db372c83f9bf69f6b974f3ee63123251ee2d145a7ca676742dca075138819d3` | PASS |
| TicketingService.php | `a6ee8fb4e1640e251ff9ada1a6284db574a418516bdd1d3891ffc4faa0b0d106` | PASS |
| AirBlueQaTestTicketingCommand.php | `534af48b397015c89e9af831ca21d0c5744faf562cd23bb1f605d8c161c098c9` | PASS |

- `php artisan optimize:clear`
- Command registered; `--temporary-enable-ticketing` present
- Pre-mutation tests: 43 passed / 148 assertions

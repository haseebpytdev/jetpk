# Runtime deploy (PR #164 + #167)

Uploaded to `/home/pkjetp/jetpk_app` (live versions backed up first):

| File | Local SHA256 | Runtime SHA256 | Parity |
|------|--------------|----------------|--------|
| `AirBlueQaTicketingScope.php` | `ff88edbaa147f235cf29a72862f0034becf8f7eaf968abaebd914f8455842a72` | `ff88edbaa147f235cf29a72862f0034becf8f7eaf968abaebd914f8455842a72` | PASS |
| `TicketingService.php` | `5357e6074e6eaa99ff7463838b9b3fd7731f59db7235663e3bc42a37e65c2c0d` | `5357e6074e6eaa99ff7463838b9b3fd7731f59db7235663e3bc42a37e65c2c0d` | PASS |
| `AirBlueQaTestTicketingCommand.php` | `a340d7b1f4c44c528afcedb471d65dd39e8cdc5e92ded65a1a88c5bcd15996b5` | `a340d7b1f4c44c528afcedb471d65dd39e8cdc5e92ded65a1a88c5bcd15996b5` | PASS |

Post-upload: `php artisan optimize:clear`

QA command registered: `airblue:qa-test-ticketing` = YES

Runtime backup dir (code): `/home/pkjetp/backups/jp-airblue-ticketing-enable-01-20261101T081755Z`

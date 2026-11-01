# Temporary ticketing_enabled enable

Secure backup (credentials; not in git / not printed):

`CONN23_BACKUP_PATH=/home/pkjetp/backups/jp-airblue-ticketing-enable-01-20261101T081938Z`  
`CONN23_BACKUP_CREATED=YES` (chmod 700/600)

Changed ONLY `credentials.ticketing_enabled` → true via Laravel model.

| Check | Result |
|-------|--------|
| TICKETING_ENABLED_TEMPORARY | YES |
| CREDENTIALS_NON_TICKETING_HASH_AFTER_ENABLE | equals BEFORE |
| CONN23_ONLY_FLAG_CHANGED | PASS |
| provider/env/protocol/host/target/status/active | unchanged |

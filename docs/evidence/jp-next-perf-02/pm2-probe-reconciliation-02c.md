# JP-NEXT-PERF-02C — PM2 probe reconciliation

## Failed probe context

`PM2_FAILED_PROBE_CONTEXT=`
Shell/SSH invocations during JP-NEXT-PERF-02B that ran `pm2` **without** the pkjetp Node/npm-global environment:

- `su - pkjetp` / incomplete `PATH` (no `/usr/local/bin/node`, no `/home/pkjetp/.npm-global/bin`)
- PowerShell-mangled remote one-liners that dropped `PATH`/`NVM` setup
- Resulting stderr: `pm2: command not found` / `env: 'node': No such file or directory`

This was a **local probe environment failure**, not a production process crash.

## Production impact

`PM2_FAILED_PROBE_PRODUCTION_IMPACT=NO`

Activate scripts under `jetpk-production-run` already used the correct pkjetp + npm-global path and reported:

- `PUBLIC_PM2=online`
- `DASHBOARD_PM2=online`

Public build `U9-V-YGZgQ3qKayMCp4BX` remained served.

## Canonical probe (02C)

Executed with:

```bash
PATH=/home/pkjetp/.npm-global/bin:/usr/local/bin:...
PM2_HOME=/home/pkjetp/.pm2
node @ /usr/local/bin/node
pm2 @ /home/pkjetp/.npm-global/bin/pm2
sudo -u pkjetp -H … pm2 list
```

Result:

- `PM2_CANONICAL_PROBE=PASS`
- `PM2_PUBLIC=online` (jetpk-public-frontend)
- `PM2_DASHBOARD=online` (jetpk-dashboard)
- OLS=`612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`
- BUILD=`U9-V-YGZgQ3qKayMCp4BX`

No second PM2 binary installed.

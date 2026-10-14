#!/bin/bash
# JP-OPS-READINESS-01 WAVE-1A — READ-ONLY forensics (no mutations)
set -u
APP=/home/pkjetp/jetpk_app
OUT=/tmp/jp-ops-w1a-$$.txt
exec >"$OUT" 2>&1

echo "=== WAVE1A_PROBE_START $(date -u +%Y-%m-%dT%H:%M:%SZ) ==="

echo "=== DF ==="
df -h
echo "=== DFI ==="
df -i

echo "=== DU_HOME_TOP ==="
du -xh --max-depth=1 /home 2>/dev/null | sort -h | tail -20
echo "=== DU_PKJETP ==="
du -xh --max-depth=1 /home/pkjetp 2>/dev/null | sort -h | tail -30
echo "=== DU_JETPK_APP ==="
du -xh --max-depth=1 "$APP" 2>/dev/null | sort -h | tail -40
echo "=== DU_STORAGE ==="
du -xh --max-depth=2 "$APP/storage" 2>/dev/null | sort -h | tail -40
echo "=== DU_RELEASES ==="
du -xh --max-depth=1 /home/pkjetp/releases 2>/dev/null | sort -h | tail -30
ls -lah /home/pkjetp/releases 2>/dev/null | head -40
echo "=== DU_BACKUPS ==="
du -xh --max-depth=1 /home/pkjetp/backups 2>/dev/null | sort -h | tail -30
ls -lah /home/pkjetp/backups 2>/dev/null | head -40
echo "=== DU_VAR_LOG ==="
du -xh --max-depth=1 /var/log 2>/dev/null | sort -h | tail -20
echo "=== DU_TMP ==="
du -xh --max-depth=1 /tmp 2>/dev/null | sort -h | tail -15
echo "=== DU_NODE_NEXT ==="
du -xh --max-depth=1 "$APP/dashboard/.next" 2>/dev/null | sort -h | tail -10
du -xh --max-depth=1 "$APP/frontend/.next" 2>/dev/null | sort -h | tail -10
du -sh "$APP/dashboard/node_modules" "$APP/frontend/node_modules" "$APP/node_modules" 2>/dev/null
du -sh "$APP/vendor" 2>/dev/null

echo "=== LOG_SIZES ==="
ls -lahS "$APP/storage/logs" 2>/dev/null | head -40
ls -lahS /home/pkjetp/.pm2/logs 2>/dev/null | head -30
echo "=== LOG_ROTATE ==="
ls -la /etc/logrotate.d 2>/dev/null | head -30
grep -l -i 'laravel\|jetpk\|pkjetp' /etc/logrotate.d/* 2>/dev/null || true

echo "=== CRON ==="
crontab -l 2>/dev/null || true

echo "=== PROCESSES ==="
ps -eo pid,ppid,user,etime,pcpu,pmem,rss,cmd --sort=-rss | head -40
ps -ef | grep -E 'queue:work|schedule:run|artisan|next-server|PM2|php.*8088' | grep -v grep | head -40

echo "=== LISTENERS ==="
ss -lntp 2>/dev/null | grep -E '8088|3001|3010|3000|80|443' || true

echo "=== CAPACITY ==="
nproc
free -h
uptime
swapon --show 2>/dev/null || true
vmstat 1 3 2>/dev/null || true

echo "=== STORAGE_PERMS ==="
ls -lad "$APP" "$APP/storage" "$APP/storage/logs" "$APP/storage/framework" "$APP/storage/framework/cache" "$APP/storage/framework/sessions" "$APP/storage/framework/views" "$APP/storage/app" "$APP/storage/app/public" "$APP/bootstrap/cache" "$APP/public/storage" 2>/dev/null
ls -la "$APP/public/storage" 2>/dev/null | head -5
readlink -f "$APP/public/storage" 2>/dev/null || true
namei -l "$APP/public/storage" 2>/dev/null || true
find "$APP/storage" "$APP/bootstrap/cache" -maxdepth 2 -printf '%m %u %g %p\n' 2>/dev/null | head -80

echo "=== BACKUPS_DETAIL ==="
find /home/pkjetp/backups -type f -printf '%s %TY-%Tm-%Td %TH:%TM %p\n' 2>/dev/null | sort -n | tail -40

echo "=== SCHEDULE_LIST ==="
cd "$APP" && /usr/local/lsws/lsphp83/bin/php artisan schedule:list --no-ansi 2>/dev/null | head -80

echo "=== WAVE1A_PROBE_END ==="
echo "OUTFILE=$OUT"
# print path for scp
wc -c "$OUT"
cat "$OUT"

#!/bin/bash
set +e
LOG=/usr/local/lsws/logs/access.log
VH=/usr/local/lsws/conf/vhosts/jetpakistan.pk/vhconf.conf

echo "=== OCT16_ADMIN_PATHS_SAMPLE ==="
grep '16/Oct/2026' "$LOG" | grep '/admin' | sed -n 's/.*"\([A-Z]*\) \([^ ]*\) HTTP[^"]*" \([0-9][0-9][0-9]\).*/\1 \2 \3/p' | cut -d'?' -f1 | sort | uniq -c | sort -rn | head -n 40

echo "=== OCT16_SETTINGS_LINES ==="
grep '16/Oct/2026' "$LOG" | grep -i settings | sed -n 's/.*\[\([^]]*\)\].*"\([A-Z]*\) \([^ ]*\) HTTP[^"]*" \([0-9][0-9][0-9]\).*/\1 \2 \3 \4/p' | cut -d'?' -f1

echo "=== ALL_PAYMENTS_ABHIPAY_EVER ==="
grep -E 'settings/payments|payments/abhipay' "$LOG" | wc -l

echo "=== REWRITE_ADMIN_PAYMENT ==="
grep -nE 'admin|payment' "$VH" | head -n 60

echo "=== VH_ROOT_MAP ==="
grep -nE 'vhDomain|vhRoot|docRoot|listeners|map' "$VH" | head -n 40
grep -nE 'jetpakistan|vhRoot' /usr/local/lsws/conf/httpd_config.conf | head -n 40

# Domain-tagged logs?
echo "=== DOMAIN_LOG_DIRS ==="
ls -lah /usr/local/lsws/logs/
find /home -maxdepth 4 -name 'access.log' -size +10k 2>/dev/null | head -n 20

# CyberPanel often stores per-domain
ls -lah /home/pkjetp/logs/ 2>/dev/null
ls -lah /home/logs/jetpakistan.pk 2>/dev/null
find /home/logs -iname '*jetpakistan*' 2>/dev/null | head -n 20

echo "=== CHECK_IF_ACCESS_LOG_IS_DEFAULT_ONLY ==="
# Count hosts if combined format includes host
awk '{print $1}' "$LOG" | sort | uniq -c | sort -rn | head -n 15
# Host header style logs
grep -oE 'Host: [^ ]+' "$LOG" 2>/dev/null | sort | uniq -c | sort -rn | head
# Some OLS logs include virtual host in extra field
tail -n 20 "$LOG"

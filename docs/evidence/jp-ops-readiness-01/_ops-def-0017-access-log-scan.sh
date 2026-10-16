#!/bin/bash
set +e
LOG=/usr/local/lsws/logs/access.log
ERR=/usr/local/lsws/logs/error.log

echo "=== PAYMENTS_PATH_HITS ==="
grep -E 'settings/payments|payments/abhipay' "$LOG" 2>/dev/null | tail -n 80 | sed -E 's/"[^"]{0,40}(Cookie|Authorization)[^"]*"/"[REDACTED_HEADER]"/Ig' | while IFS= read -r line; do
  ts=$(echo "$line" | sed -n 's/.*\[\([^]]*\)\].*/\1/p')
  req=$(echo "$line" | sed -n 's/.*"\([A-Z]*\) \([^ ]*\) HTTP[^"]*" \([0-9][0-9][0-9]\).*/\1 \2 \3/p')
  method=$(echo "$req" | awk '{print $1}')
  path=$(echo "$req" | awk '{print $2}' | cut -d'?' -f1)
  status=$(echo "$req" | awk '{print $3}')
  if [ -n "$method" ]; then
    echo "$ts $method $path $status"
  else
    echo "PARSE_FAIL $(echo "$line" | cut -c1-160)"
  fi
done

echo "=== COUNT_SETTINGS_PAYMENTS ==="
grep -c 'settings/payments' "$LOG" 2>/dev/null || echo 0

echo "=== OCT16_ADMIN_SETTINGS ==="
grep '16/Oct/2026' "$LOG" 2>/dev/null | grep 'admin/settings' | while IFS= read -r line; do
  ts=$(echo "$line" | sed -n 's/.*\[\([^]]*\)\].*/\1/p')
  req=$(echo "$line" | sed -n 's/.*"\([A-Z]*\) \([^ ]*\) HTTP[^"]*" \([0-9][0-9][0-9]\).*/\1 \2 \3/p')
  method=$(echo "$req" | awk '{print $1}')
  path=$(echo "$req" | awk '{print $2}' | cut -d'?' -f1)
  status=$(echo "$req" | awk '{print $3}')
  [ -n "$method" ] && echo "$ts $method $path $status"
done | tail -n 50

echo "=== ERROR_HINTS ==="
grep -iE 'payment|abhipay|TokenMismatch|CSRF|ValidationException|419' "$ERR" 2>/dev/null | tail -n 15 | cut -c1-180

echo "=== VHOST_DOCROOTS ==="
for vh in jetpakistan.pk iframe-pilot.jetpakistan.pk; do
  f=/usr/local/lsws/conf/vhosts/$vh/vhconf.conf
  echo "-- $vh --"
  grep -E 'docRoot|vhRoot|/home/' "$f" 2>/dev/null | head -n 15
done

echo "=== LARAVEL_LOG_HINTS ==="
# last 5000 lines only for speed
tail -n 5000 /home/pkjetp/jetpk_app/storage/logs/laravel.log 2>/dev/null | grep -iE 'abhipay|payment_gateway|TokenMismatch|CSRF|ValidationException|AgencyPayment' | tail -n 30 | sed -E 's/[A-Za-z0-9+\/=_-]{40,}/[REDACTED]/g' | cut -c1-200

echo "=== FAILED_JOBS_SAFE ==="
cd /home/pkjetp/jetpk_app && /usr/local/lsws/lsphp83/bin/php -r '
require "vendor/autoload.php";
$a=require "bootstrap/app.php";
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach (Illuminate\Support\Facades\DB::table("failed_jobs")->orderByDesc("id")->limit(5)->get(["id","queue","failed_at"]) as $j) {
  echo "FAILED_JOB id={$j->id} queue={$j->queue} at={$j->failed_at}\n";
}
'

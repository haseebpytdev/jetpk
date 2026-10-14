#!/bin/bash
set -euo pipefail
APP=/home/pkjetp/jetpk_app
REL=/home/pkjetp/releases/jetpk-20261014T192123Z
PHP=/usr/local/lsws/lsphp83/bin/php
SHA=a138eb570b05910046df7fb251beeef839b25395

FILES=(
  app/Http/Controllers/Auth/GoogleOnboardingController.php
  app/Jobs/Notifications/DeliverQueuedOperationalMail.php
  app/Services/Communication/BookingCommunicationService.php
  app/Services/Communication/OtaNotificationService.php
  app/Services/Email/JetpkOperationalEmailService.php
  config/mail.php
)

echo "=== MD5 BEFORE ==="
for f in "${FILES[@]}"; do
  echo "-- $f"
  md5sum "$REL/$f" "$APP/$f" || true
done

echo "=== COPY (no preserve ownership) ==="
for f in "${FILES[@]}"; do
  mkdir -p "$(dirname "$APP/$f")"
  # Prefer rsync without group/owner; fallback to cp
  if command -v rsync >/dev/null 2>&1; then
    rsync -a --no-owner --no-group "$REL/$f" "$APP/$f"
  else
    cp -f "$REL/$f" "$APP/$f"
  fi
  chown pkjetp:pkjetp "$APP/$f" 2>/dev/null || true
done

echo "=== MD5 AFTER ==="
for f in "${FILES[@]}"; do
  echo "-- $f"
  md5sum "$REL/$f" "$APP/$f"
done

printf '%s' "$SHA" > "$APP/.jetpk-runtime-sha"
printf '%s' "$SHA" > "$APP/.jetpk-authorized-sha"
chown pkjetp:pkjetp "$APP/.jetpk-runtime-sha" "$APP/.jetpk-authorized-sha" 2>/dev/null || true
echo "PRODUCTION_RUNTIME_SHA=$(cat "$APP/.jetpk-runtime-sha")"

cd "$APP"
sudo -u pkjetp "$PHP" artisan optimize:clear || "$PHP" artisan optimize:clear

echo "=== VERIFY ==="
grep -n 'DeliverQueuedOperationalMail::dispatch\|dispatch(function' "$APP/app/Services/Communication/OtaNotificationService.php" | head -10
grep -n 'notification_queue' "$APP/config/mail.php" | head -5
"$PHP" -r "require 'vendor/autoload.php'; \$a=require 'bootstrap/app.php'; \$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo 'MAIL_NOTIFICATION_QUEUE='.config('mail.notification_queue').PHP_EOL; echo 'QUEUE_CONNECTION='.config('queue.default').PHP_EOL;"

echo "FINISH_DEPLOY_OK"

echo JETPK_PRODUCTION_LOCK_ACQUIRED
APP=/home/pkjetp/jetpk_app
SHA=88944e977c4e66d33b9cbe9515fb40308732148a
f=app/Support/Emails/JetpkEmailQaRecipientLock.php
STAMP=$(date -u +%Y%m%dT%H%M%SZ)
RB=/home/pkjetp/releases/jp-final-05-lock-$STAMP
mkdir -p "$RB/$(dirname $f)"
if [ -f "$APP/$f" ]; then cp -a "$APP/$f" "$RB/$f"; fi
TMP=/tmp/jp-final-05-lock-$STAMP
mkdir -p "$TMP"
curl -fsSL "https://github.com/haseebpytdev/jetpk/archive/${SHA}.tar.gz" -o "$TMP/src.tgz"
ROOT=$(tar -tzf "$TMP/src.tgz" | head -1 | cut -d/ -f1)
tar -xzf "$TMP/src.tgz" -C "$TMP" "$ROOT/$f"
cp -a "$TMP/$ROOT/$f" "$APP/$f"
chown pkjetp:pkjetp "$APP/$f"
grep -n normalizeOrFail "$APP/$f" | head -5
PHP=/usr/local/lsws/lsphp83/bin/php
sudo -u pkjetp "$PHP" "$APP/artisan" optimize:clear
echo LOCK_BACKUP=$RB
echo LOCK_DEPLOYED=YES

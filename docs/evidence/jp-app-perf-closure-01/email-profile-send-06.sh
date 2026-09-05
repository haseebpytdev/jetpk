echo JETPK_PRODUCTION_LOCK_ACQUIRED
APP=/home/pkjetp/jetpk_app
PHP=/usr/local/lsws/lsphp83/bin/php
if [ ! -x "$PHP" ]; then PHP=/usr/bin/php; fi
cd "$APP" || exit 1
RUN=jp-final-06-profile-$(date -u +%Y%m%dT%H%M%SZ)
echo RUNTIME=$(cat "$APP/.jetpk-runtime-sha")
sudo -u pkjetp "$PHP" artisan jetpk:email-prod-qa --run-id="$RUN-inv" --inventory
INV="$APP/storage/app/email-qa/live/$RUN-inv/email-inventory.json"
export INV
export KEEP='support_ticket_created__admin'
SKIP=$(python3 -c 'import json,os
inv=json.load(open(os.environ["INV"]))
keep=set(os.environ["KEEP"].split(","))
ids=[s["scenario_id"] for s in inv.get("scenarios",[])]
print(",".join(i for i in ids if i not in keep))
')
echo '=== PROFILE MUTATE THEN SEND ==='
sudo -u pkjetp "$PHP" -r '
require "/home/pkjetp/jetpk_app/vendor/autoload.php";
$app = require "/home/pkjetp/jetpk_app/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$settings = App\Models\AgencySetting::query()->orderBy("id")->first();
if (!$settings) { echo "NO_AGENCY_SETTING\n"; exit(1); }
$orig = $settings->support_phone;
file_put_contents("/tmp/jp-final-06-phone-orig.txt", (string) $orig);
$probe = "+92-51-8894406";
$settings->support_phone = $probe;
$settings->save();
$resolved = App\Support\Branding\CompanyEmailProfileResolver::resolveForPlatform();
echo "ORIG=".json_encode($orig)."\n";
echo "PROBE=".$probe."\n";
echo "RESOLVED_AFTER=".json_encode($resolved->support_phone)."\n";
echo "PROFILE_RESOLVER_RENDER_PROVEN=".($resolved->support_phone === $probe ? "YES" : "NO")."\n";
'
sudo -u pkjetp "$PHP" artisan jetpk:email-prod-qa --run-id="$RUN-send" --render --send --skip-ids="$SKIP"
python3 - "$APP/storage/app/email-qa/live/$RUN-send/html" <<'PY'
import os,sys
d=sys.argv[1]
hit=0
for fn in os.listdir(d) if os.path.isdir(d) else []:
    t=open(os.path.join(d,fn),encoding='utf-8',errors='replace').read()
    if '8894406' in t: hit += 1
print('PROFILE_VALUE_IN_TRANSPORT_SNAPSHOT_FILES', hit)
print('PROFILE_RESOLVER_TRANSPORT_PROVEN', 'YES' if hit>0 else 'NO')
PY
echo MANIFEST_PROFILE="$APP/storage/app/email-qa/live/$RUN-send/email-delivery-manifest.json"
cat "$APP/storage/app/email-qa/live/$RUN-send/email-delivery-manifest.json"
sudo -u pkjetp "$PHP" -r '
require "/home/pkjetp/jetpk_app/vendor/autoload.php";
$app = require "/home/pkjetp/jetpk_app/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$settings = App\Models\AgencySetting::query()->orderBy("id")->first();
$orig = file_get_contents("/tmp/jp-final-06-phone-orig.txt");
if ($orig === false) { echo "ORIG_FILE_MISSING\n"; exit(1); }
$settings->support_phone = ($orig === "" ? null : $orig);
$settings->save();
$fresh = App\Models\AgencySetting::query()->orderBy("id")->first();
$resolved = App\Support\Branding\CompanyEmailProfileResolver::resolveForPlatform();
echo "RESTORED_DB=".json_encode($fresh->support_phone)."\n";
echo "RESOLVED_RESTORED=".json_encode($resolved->support_phone)."\n";
echo "PROFILE_VALUE_RESTORED=".(((string) ($fresh->support_phone ?? "")) === $orig ? "YES" : "NO")."\n";
'
echo SUPPLIER_MUTATION_CALLS=0
echo JP_FINAL_06_PROFILE_SEND_DONE

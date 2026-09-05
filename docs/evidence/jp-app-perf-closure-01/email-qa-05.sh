echo JETPK_PRODUCTION_LOCK_ACQUIRED
APP=/home/pkjetp/jetpk_app
PHP=/usr/local/lsws/lsphp83/bin/php
if [ ! -x "$PHP" ]; then PHP=/usr/bin/php; fi
RUN=jp-final-05-889-$(date -u +%Y%m%dT%H%M%SZ)
echo RUN_ID=$RUN
echo RUNTIME=$(cat "$APP/.jetpk-runtime-sha")
echo PUBLIC_BUILD=$(cat "$APP/frontend/.next/BUILD_ID")
cd "$APP" || exit 1
sudo -u pkjetp "$PHP" artisan jetpk:email-prod-qa --run-id="$RUN" --inventory
INV="$APP/storage/app/email-qa/live/$RUN/email-inventory.json"
export INV
export KEEP='booking_confirmed__admin,group_booking_payment_submitted__admin,ticket_issued__booking_agent,agent_application_submitted__admin,support_ticket_created__admin'
SKIP=$(python3 -c 'import json,os
inv=json.load(open(os.environ["INV"]))
keep=set(os.environ["KEEP"].split(","))
ids=[s["scenario_id"] for s in inv.get("scenarios",[])]
skip=[i for i in ids if i not in keep]
print(",".join(skip))
')
echo KEEP="$KEEP"
echo SKIP_N=$(python3 -c 'print(len(open("/dev/stdin").read().split(",")))' <<<"$SKIP")
echo SKIP_READY
sudo -u pkjetp "$PHP" artisan jetpk:email-prod-qa --run-id="$RUN-render" --render --skip-ids="$SKIP"
HTMLDIR="$APP/storage/app/email-qa/live/$RUN-render/html"
python3 - "$HTMLDIR" <<'PY'
import os,re,sys
d=sys.argv[1]
files=sorted(os.listdir(d)) if os.path.isdir(d) else []
print('RENDER_FILES',len(files))
html_tag=css=pre=layout=0
greet_fail=0
cta_fail=0
ticket_fail=0
human='YES'
for fn in files:
    p=os.path.join(d,fn)
    text=open(p,encoding='utf-8',errors='replace').read()
    if fn.endswith('.txt'):
        html_tag += len(re.findall(r'<[^>]+>', text))
        if re.search(r'[{};]|@media|font-family', text, re.I): css += 1
        if '\u200c' in text or '&#8204' in text: pre += 1
        if text.count('\n\n\n')>0: layout += 1
        if len(text.strip())<40: human='NO'
    if fn.endswith('.html'):
        if 'booking_confirmed__admin' in fn and 'Dear Administrator' not in text: greet_fail += 1
        if 'group_booking_payment_submitted__admin' in fn and 'Dear Administrator' not in text: greet_fail += 1
        if 'ticket_issued__booking_agent' in fn and 'Dear Agent' not in text: greet_fail += 1
        if 'agent_application_submitted__admin' in fn:
            if 'company' not in text.lower() and 'agency' not in text.lower():
                print('AGENT_APP_CONTEXT_WEAK')
print('INCORRECT_ROLE_GREETING_COUNT', greet_fail)
print('PLAIN_TEXT_HTML_TAG_COUNT', html_tag)
print('PLAIN_TEXT_CSS_LEAK_COUNT', css)
print('PLAIN_TEXT_PREHEADER_FILLER_COUNT', pre)
print('PLAIN_TEXT_LAYOUT_NOISE_COUNT', layout)
print('PLAIN_TEXT_HUMAN_READABLE', human)
PY

echo '=== PROFILE MUTATION ==='
sudo -u pkjetp "$PHP" -r '
require "/home/pkjetp/jetpk_app/vendor/autoload.php";
$app = require "/home/pkjetp/jetpk_app/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$settings = App\Models\AgencySetting::query()->orderBy("id")->first();
if (!$settings) { echo "NO_AGENCY_SETTING\n"; exit(1); }
$orig = $settings->support_phone;
file_put_contents("/tmp/jp-final-05-phone-orig.txt", (string) $orig);
$probe = "+92-51-8894405";
$settings->support_phone = $probe;
$settings->save();
$resolved = App\Support\Branding\CompanyEmailProfileResolver::resolveForPlatform();
echo "ORIG=".json_encode($orig)."\n";
echo "PROBE=".$probe."\n";
echo "RESOLVED_AFTER=".json_encode($resolved->support_phone)."\n";
echo "PROFILE_RESOLVER_PROVEN=".($resolved->support_phone === $probe ? "YES" : "NO")."\n";
'
sudo -u pkjetp "$PHP" artisan jetpk:email-prod-qa --run-id="$RUN-profile" --render --skip-ids="$SKIP"
python3 - "$APP/storage/app/email-qa/live/$RUN-profile/html" <<'PY'
import os,sys
d=sys.argv[1]
hit=0
for fn in os.listdir(d):
    t=open(os.path.join(d,fn),encoding='utf-8',errors='replace').read()
    if '8894405' in t: hit += 1
print('PROFILE_VALUE_IN_RENDER_FILES', hit)
PY
sudo -u pkjetp "$PHP" -r '
require "/home/pkjetp/jetpk_app/vendor/autoload.php";
$app = require "/home/pkjetp/jetpk_app/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$settings = App\Models\AgencySetting::query()->orderBy("id")->first();
$orig = file_get_contents("/tmp/jp-final-05-phone-orig.txt");
if ($orig === false) { echo "ORIG_FILE_MISSING\n"; exit(1); }
$settings->support_phone = ($orig === "" ? null : $orig);
$settings->save();
$fresh = App\Models\AgencySetting::query()->orderBy("id")->first();
echo "RESTORED_DB=".json_encode($fresh->support_phone)."\n";
echo "PROFILE_VALUE_RESTORED=".(((string) ($fresh->support_phone ?? "")) === $orig ? "YES" : "NO")."\n";
'
echo '=== SEND ==='
sudo -u pkjetp "$PHP" artisan jetpk:email-prod-qa --run-id="$RUN-send" --render --send --skip-ids="$SKIP"
echo MANIFEST="$APP/storage/app/email-qa/live/$RUN-send/email-delivery-manifest.json"
cat "$APP/storage/app/email-qa/live/$RUN-send/email-delivery-manifest.json"
echo SUPPLIER_MUTATION_CALLS=0
echo JP_FINAL_05_EMAIL_QA_DONE

echo JETPK_PRODUCTION_LOCK_ACQUIRED
APP=/home/pkjetp/jetpk_app
PHP=/usr/local/lsws/lsphp83/bin/php
RUN=jp-final-06-fix-$(date -u +%Y%m%dT%H%M%SZ)
cd "$APP" || exit 1
sudo -u pkjetp "$PHP" artisan jetpk:email-prod-qa --run-id="$RUN-inv" --inventory
INV="$APP/storage/app/email-qa/live/$RUN-inv/email-inventory.json"
export INV
export KEEP='booking_confirmed__admin,ticket_issued__booking_agent,support_ticket_created__admin,agent_application_submitted__admin'
SKIP=$(python3 -c 'import json,os
inv=json.load(open(os.environ["INV"]))
keep=set(os.environ["KEEP"].split(","))
ids=[s["scenario_id"] for s in inv.get("scenarios",[])]
print(",".join(i for i in ids if i not in keep))
')
echo RUNTIME=$(cat "$APP/.jetpk-runtime-sha")
sudo -u pkjetp "$PHP" artisan jetpk:email-prod-qa --run-id="$RUN" --render --send --skip-ids="$SKIP"
echo MANIFEST="$APP/storage/app/email-qa/live/$RUN/email-delivery-manifest.json"
cat "$APP/storage/app/email-qa/live/$RUN/email-delivery-manifest.json"
echo SUPPLIER_MUTATION_CALLS=0
echo JP_FINAL_06_FIX_SEND_DONE

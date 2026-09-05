echo JETPK_PRODUCTION_LOCK_ACQUIRED
python3 - <<'PY'
import os
base='/home/pkjetp/jetpk_app/storage/app/email-qa/live/jp-final-05-889-20260905T181014Z-render/html'
for fn in ['booking_confirmed__admin.html','group_booking_payment_submitted__admin.html','ticket_issued__booking_agent.html','agent_application_submitted__admin.html']:
    p=os.path.join(base,fn)
    t=open(p,encoding='utf-8',errors='replace').read()
    greet='Dear Administrator' if 'Dear Administrator' in t else ('Dear Agent' if 'Dear Agent' in t else 'MISSING')
    cta='cta' if ('View booking' in t or 'Open ticket' in t or 'Review application' in t or 'href=' in t) else 'NO_HREF'
    print(fn, 'GREET='+greet, 'HAS_HREF='+str('href=' in t), 'TICKET_PNR='+str('X7K9QP' in t or 'PNR' in t))
PY

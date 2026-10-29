#!/usr/bin/env bash
set -euo pipefail
KEEP_SERVERS=1 bash /home/pkjetp/tmp/pr139-build-proof/_run-server-evidence.sh
python3 - <<'PY'
from pathlib import Path
import re
html = Path('/tmp/pr139-home.html').read_text(encoding='utf-8', errors='replace')
ids = sorted(set(re.findall(r'data-testid="([^"]+)"', html)))
print('TESTIDS', len(ids))
for i in ids:
    print(i)
print('TRENDING', html.count('trending-routes-section'))
print('ROUTE_CARD', html.count('route-card'))
print('DEST', html.count('destination-card'))
print('FEATURED', html.count('featured-offer'))
print('SUPPORT', html.count('support-callout'))
print('HERO_PIC', html.count('homepage-hero-responsive-picture'))
print('EVIDENCE', len(re.findall(r'_evidence/pr139-responsive/', html)))
PY

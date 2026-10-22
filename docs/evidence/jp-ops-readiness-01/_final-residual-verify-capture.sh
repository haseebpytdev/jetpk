#!/usr/bin/env bash
set -euo pipefail
echo "=== CAPTURE_UTC=$(date -u +%Y-%m-%dT%H:%M:%SZ) ==="
bash /tmp/_final-residual-postops.sh
echo "=== PHASES_1_4_8 ==="
bash /tmp/_final-residual-closure-probe.sh 1
bash /tmp/_final-residual-closure-probe.sh 4
bash /tmp/_final-residual-closure-probe.sh 8
echo "=== 0023_ABSENCE ==="
for p in \
  /home/pkjetp/backups/jp-ask-ui-01-20260907T211951Z \
  /home/pkjetp/backups/jp-perf-final-02e-20260904T233239Z \
  /home/pkjetp/backups/jp-perf-final-02d-20260904T203550Z \
  /home/pkjetp/releases/jetpk-20260930T080534Z
do
  if [[ -e "$p" ]]; then echo "STILL=$p"; else echo "ABSENT=$p"; fi
done
read -r TOTAL_K USED_K AVAIL_K PCT < <(df -Pk / | awk 'NR==2{print $2,$3,$4,$5}' | tr -d '%')
FREE_GB=$(awk -v a="$AVAIL_K" 'BEGIN{printf "%.2f", a/1024/1024}')
echo "DISK_USED_PERCENT=$PCT"
echo "DISK_FREE_GB=$FREE_GB"
echo "BIN_HAS_ALERT=$(grep -c 'jetpk:ops-health-alert' /home/pkjetp/bin/disk-health-check.sh || true)"
echo "CAPTURE_COMPLETE"

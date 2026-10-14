#!/bin/bash
set -uo pipefail
echo "=== SWAP ==="
ls -lah /cyberpanel.swap 2>/dev/null
du -h /cyberpanel.swap 2>/dev/null
stat -c 'SIZE=%s BLOCKS=%b BLKSIZE=%B' /cyberpanel.swap 2>/dev/null
echo "=== ROOT FILES ==="
ls -lah / | head -40
du -xsb /cyberpanel.swap /app /frontend 2>/dev/null
echo "=== AI LAB ==="
du -xhd1 /opt/jetpakistan-ai-lab 2>/dev/null | sort -hr
du -xhd1 /opt/jetpakistan-ai-lab/models 2>/dev/null | sort -hr | head -20
ps -ef | grep -Ei 'ollama|llama|ai-lab|jetpakistan-ai' | grep -v grep | head -30
echo "=== PM2 DESCRIBE ==="
pm2 describe 0 2>/dev/null | head -40 || true
pm2 list 2>&1 | head -30 || true
echo DONE

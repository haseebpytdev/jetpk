echo JETPK_PRODUCTION_LOCK_ACQUIRED
APP=/home/pkjetp/jetpk_app
cd "$APP" || exit 1
if git rev-parse HEAD >/dev/null 2>&1; then
  echo PRODUCTION_RUNTIME_SHA=$(git rev-parse HEAD)
fi
if [ -f .jetpk-runtime-sha ]; then echo JP_RUNTIME_SHA=$(cat .jetpk-runtime-sha); fi
if [ -f frontend/.next/BUILD_ID ]; then echo PUBLIC_BUILD_ID=$(cat frontend/.next/BUILD_ID); fi
if [ -f dashboard/.next/BUILD_ID ]; then echo DASHBOARD_BUILD_ID=$(cat dashboard/.next/BUILD_ID); fi
df -P / | tail -1
df -iP / | tail -1
echo LOAD=$(cat /proc/loadavg)
echo MEM=$(awk '/MemTotal|MemAvailable|SwapTotal|SwapFree/{printf "%s=%s ", $1,$2}' /proc/meminfo)
PM2=/home/pkjetp/.npm-global/bin/pm2
if [ -x "$PM2" ]; then sudo -u pkjetp "$PM2" jlist | python3 -c 'import sys,json; d=json.load(sys.stdin);
[print("PM2",x.get("name"),x.get("pm2_env",{}).get("status")) for x in d]'; fi
echo OLS_SHA256=$(sha256sum /usr/local/lsws/conf/httpd_config.conf | awk '{print $1}')
echo SUPPLIER_MUTATION_BASELINE=0
echo ROLLBACK_SETS=$(ls -1 /home/pkjetp/releases 2>/dev/null | tail -5)

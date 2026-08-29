# JP-FINAL-CLOSURE-01 — Git report (R3 rebuild)

## Authority

- BRANCH=`phase/jp-flight-perf-01`
- REMOTE=`jetpk` → `https://github.com/haseebpytdev/jetpk.git`
- START_R3_LIVE_RUNTIME=`37d489cec3ff34f2679f80201d4581e178863b38`
- START_PUBLIC_BUILD=`4KN41ZZvPsqgb3xu8D7Ju`

## Engineering commits this R3 continuation

| SHA | TYPE | SUBJECT | DEPLOYED |
|---|---|---|---|
| `86fa57fbe5b4c28d64ecc06d16d82710ad29fda1` | EMAIL_FINAL | email content/branding (prior) | YES (via 37d489ce lineage) |
| `37d489cec3ff34f2679f80201d4581e178863b38` | GROUP_DISCOVERY | facet unblock + groups UI | YES |
| `52460b310757e550f133e7c33a1d47440f56daa4` | EMAIL_URL | force jetpakistan.pk when canonical domain is local | superseded |
| `63e66e65bf8d83acaa5feaeb0efcedd66ad1f75e` | EMAIL_URL+LOGO | rewrite local *.test logo hosts to jetpakistan.pk | YES (file hash match live) |

## Tip

- LOCAL/REMOTE HEAD=`63e66e65bf8d83acaa5feaeb0efcedd66ad1f75e`
- DEPLOYED_RUNTIME_SHA (PHP branding file)=`63e66e65bf8d83acaa5feaeb0efcedd66ad1f75e`
- PUBLIC_BUILD_ID=`4KN41ZZvPsqgb3xu8D7Ju` (unchanged; no frontend rebuild)

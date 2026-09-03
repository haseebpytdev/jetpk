# BOM / JSON init proof (ITERATION_03 closure gate)

Measured against production `https://jetpakistan.pk` after runtime SHA `84543e9e` / current `b7e51260`.

## Local source

```text
config/ota-flights.php FIRST3=3C 3F 70
OTA_FLIGHTS_UTF8_BOM_PRESENT_AFTER_FIX=NO
```

## Production GET `/laravel/flights/results/search`

```text
HTTP_STATUS=200
FIRST_BYTES_HEX=7b 22 73 65 61 72 63 68   # {"search
BOM_PRESENT=NO
JSON_PARSE_RAW_OK=YES
JSON_RESPONSE_PREFIX_BYTES_VALID=YES
SEARCH_ID_AVAILABLE=YES
SEARCH_ID_PREFIX=2fc06f4a-61c5-49
JSON_PARSE_FAILURE_COUNT=0
```

## Warm Return N=30 (b7e51260) — search_id use

```text
SEARCHMODULE_SEARCH_ID_USED=YES
INIT_SEARCH_ID_OK_COUNT=30/30
```

Gate: PASS for BOM/JSON/search_id defect.

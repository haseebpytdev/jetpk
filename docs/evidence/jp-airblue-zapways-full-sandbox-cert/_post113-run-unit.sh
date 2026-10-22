#!/usr/bin/env bash
set -euo pipefail
APP=/home/pkjetp/jetpk_app
PHP=/usr/local/lsws/lsphp83/bin/php
cd "$APP"
echo "=== AIRBLUE_XML_TESTS ==="
# Production may lack the PR113 test file; run if present, else local assertion probe
if [[ -f tests/Unit/Services/Suppliers/AirBlue/AirBlueOtaXmlBuilderTest.php ]]; then
  $PHP vendor/bin/phpunit --filter 'AirBlueOtaXmlBuilderTest' \
    tests/Unit/Services/Suppliers/AirBlue/AirBlueOtaXmlBuilderTest.php 2>&1 | tee /tmp/ab-pr113-xml-tests.txt | tail -50
else
  echo "AIRBLUE_XML_TESTS=SKIPPED_TEST_FILE_NOT_DEPLOYED"
fi

echo "=== RELATED_GATES ==="
$PHP vendor/bin/phpunit --filter 'AirBlueBookingUncertainStateTest|AirBlueExternalCallGateTest|AirBlueLiveCliGateTest|AirBlueClientRedactionTest|AirBlueVoid' 2>&1 \
  | tee /tmp/ab-pr113-related-tests.txt | tail -60

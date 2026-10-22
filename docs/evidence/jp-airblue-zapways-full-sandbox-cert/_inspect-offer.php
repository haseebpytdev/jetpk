<?php
$j = json_decode(file_get_contents('/home/pkjetp/jetpk_app/tests/Fixtures/airblue/search_isb_khi.json'), true);
$it = $j['first_offer']['raw_payload']['provider_context']['priced_itineraries'][0] ?? [];
echo 'FB='.count($it['fare_breakdowns'] ?? []).' SEG='.count($it['segments'] ?? []).PHP_EOL;
if (! empty($it['fare_breakdowns'][0])) {
    $fb = $it['fare_breakdowns'][0];
    echo 'PTC='.($fb['ptc'] ?? '?').' TOTAL='.($fb['total'] ?? '?').' BASE='.($fb['base'] ?? '?').PHP_EOL;
    echo 'KEYS='.implode(',', array_keys($fb)).PHP_EOL;
}
$seg = $it['segments'][0] ?? [];
echo 'SEG_KEYS='.implode(',', array_keys($seg)).PHP_EOL;

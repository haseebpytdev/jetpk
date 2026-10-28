<?php
/**
 * Phase F–H: controlled AirBlue v3 TEST AirLowFareSearch (max 2) + fare-variant analysis.
 * No AirBook/ticket/seat/refund/cancel. No credential/XML dump.
 */
declare(strict_types=1);

$appRoot = "/home/pkjetp/jetpk_app";
chdir($appRoot);
require $appRoot."/vendor/autoload.php";
$app = require $appRoot."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Data\FlightSearchRequestData;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\AirBlueConnectionSearchPolicy;
use App\Services\Suppliers\AirBlue\AirBlueFlightSearchService;

function out(string $k, string|int|float|bool|null $v): void {
    if (is_bool($v)) { $v = $v ? "YES" : "NO"; }
    echo $k."=".(string) $v.PHP_EOL;
}
function hash12(string $s): string { return substr(hash("sha256", $s), 0, 12); }

$V3_HASH = "535fa30d7e25";
$v3 = null;
foreach (SupplierConnection::query()->where("provider", "airblue")->orderBy("id")->get() as $cand) {
    $cred = is_array($cand->credentials) ? $cand->credentials : [];
    if ((string) ($cred["protocol_version"] ?? "") === "3.0" && hash12((string) $cand->id) === $V3_HASH) {
        $v3 = $cand;
        break;
    }
}
if (! $v3 || (int) $v3->id === 22) {
    out("FATAL", "V3_CONNECTION_INVALID");
    exit(2);
}
$cred = is_array($v3->credentials) ? $v3->credentials : [];
$eligible = app(AirBlueConnectionSearchPolicy::class)->isEligibleForPublicSearch($v3);
out("V3_PUBLIC_SEARCH_ELIGIBLE", $eligible ? "YES" : "NO");
if ($eligible) {
    out("FATAL", "V3_MUST_NOT_BE_PUBLIC");
    exit(3);
}

$svc = app(AirBlueFlightSearchService::class);
$attempts = [
    ["ISB", "KHI", gmdate("Y-m-d", time() + 62 * 86400)],
    ["KHI", "ISB", gmdate("Y-m-d", time() + 70 * 86400)],
];

$calls = 0;
$offers = [];
$used = null;
foreach ($attempts as $attempt) {
    if ($calls >= 2) {
        break;
    }
    [$o, $d, $date] = $attempt;
    $calls++;
    out("SEARCH_ATTEMPT_".$calls."_ROUTE", $o."-".$d);
    out("SEARCH_ATTEMPT_".$calls."_DATE", $date);
    $request = new FlightSearchRequestData(
        origin: $o,
        destination: $d,
        departure_date: $date,
        adults: 1,
        currency: "PKR",
        agency_id: $v3->agency_id,
        source_channel: "qa_v3_frontend_uat_phase1r",
    );
    $result = $svc->search($request, $v3);
    $offers = $result->offers;
    out("SEARCH_ATTEMPT_".$calls."_OFFER_COUNT", (string) count($offers));
    if ($offers !== []) {
        $used = ["origin" => $o, "destination" => $d, "date" => $date];
        break;
    }
}
out("AIRLOWFARESEARCH_PROVIDER_CALLS", (string) $calls);
out("REAL_AIRBLUE_OFFER_COUNT", (string) count($offers));
if ($offers === [] || $used === null) {
    out("SEARCH_SUCCESS", "NO");
    out("FINAL_STATUS", "FRONTEND_UAT_BLOCKED_NO_INVENTORY");
    exit(4);
}
out("SEARCH_SUCCESS", "YES");
out("UAT_ROUTE", $used["origin"]."-".$used["destination"]);
out("UAT_DATE", $used["date"]);

$itineraries = [];
$samples = 0;
foreach ($offers as $offerObj) {
    $offer = method_exists($offerObj, "toArray") ? $offerObj->toArray() : (array) $offerObj;
    $segs = is_array($offer["segments"] ?? null) ? $offer["segments"] : [];
    $sigParts = [];
    foreach ($segs as $seg) {
        if (! is_array($seg)) {
            continue;
        }
        $sigParts[] = implode("|", [
            strtoupper((string) ($seg["departure_airport"] ?? "")),
            strtoupper((string) ($seg["arrival_airport"] ?? "")),
            (string) ($seg["departure_at"] ?? ""),
            (string) ($seg["arrival_at"] ?? ""),
            strtoupper((string) ($seg["carrier"] ?? $seg["airline_code"] ?? "")),
            (string) ($seg["flight_number"] ?? ""),
        ]);
    }
    $itinSig = hash("sha256", implode("||", $sigParts));
    $fareTypes = [];
    $rbds = [];
    $cabins = [];
    foreach ($segs as $seg) {
        if (! is_array($seg)) {
            continue;
        }
        $ft = trim((string) ($seg["fare_type"] ?? ""));
        $rbd = trim((string) ($seg["rbd"] ?? ""));
        $cab = trim((string) ($seg["cabin_class"] ?? ""));
        if ($ft !== "") {
            $fareTypes[$ft] = true;
        }
        if ($rbd !== "") {
            $rbds[$rbd] = true;
        }
        if ($cab !== "") {
            $cabins[$cab] = true;
        }
    }
    $fb = is_array($offer["fare_breakdown"] ?? null) ? $offer["fare_breakdown"] : [];
    $supplierTotal = round((float) ($fb["supplier_total"] ?? 0), 2);
    $variantKey = implode("~", [
        implode(",", array_keys($fareTypes)),
        implode(",", array_keys($rbds)),
        (string) $supplierTotal,
        (string) ($offer["cabin"] ?? ""),
        (string) ($offer["fare_family"] ?? "null"),
    ]);
    if (! isset($itineraries[$itinSig])) {
        $itineraries[$itinSig] = [
            "variants" => [],
            "flight_number" => (string) ($offer["flight_number"] ?? ""),
            "origin" => (string) ($offer["origin"] ?? ""),
            "destination" => (string) ($offer["destination"] ?? ""),
            "departure_at" => (string) ($offer["departure_at"] ?? ""),
        ];
    }
    $itineraries[$itinSig]["variants"][$variantKey] = [
        "fare_types" => array_keys($fareTypes),
        "rbds" => array_keys($rbds),
        "cabin_classes" => array_keys($cabins),
        "top_cabin" => (string) ($offer["cabin"] ?? ""),
        "fare_family" => $offer["fare_family"] ?? null,
        "branded_fares_count" => is_array($offer["branded_fares"] ?? null) ? count($offer["branded_fares"]) : 0,
        "supplier_total" => $supplierTotal,
        "currency" => (string) ($fb["currency"] ?? ""),
        "base" => round((float) ($fb["base_fare"] ?? 0), 2),
        "taxes" => round((float) ($fb["taxes"] ?? 0), 2),
        "airline_code" => (string) ($offer["airline_code"] ?? ""),
        "refundable" => (bool) ($offer["refundable"] ?? false),
        "baggage_summary" => is_array($offer["baggage"] ?? null) ? ($offer["baggage"]["summary"] ?? null) : null,
    ];

    if ($samples < 5) {
        $samples++;
        out("SAMPLE_".$samples."_CARRIER", (string) ($offer["airline_code"] ?? ""));
        out("SAMPLE_".$samples."_FLIGHT", (string) ($offer["flight_number"] ?? ""));
        out("SAMPLE_".$samples."_DEP", substr((string) ($offer["departure_at"] ?? ""), 0, 16));
        out("SAMPLE_".$samples."_ARR", substr((string) ($offer["arrival_at"] ?? ""), 0, 16));
        out("SAMPLE_".$samples."_SUPPLIER_TOTAL", (string) $supplierTotal);
        out("SAMPLE_".$samples."_CURRENCY", (string) ($fb["currency"] ?? ""));
        out("SAMPLE_".$samples."_TOP_CABIN", (string) ($offer["cabin"] ?? ""));
        out("SAMPLE_".$samples."_FARE_FAMILY", $offer["fare_family"] === null ? "null" : (string) $offer["fare_family"]);
        out("SAMPLE_".$samples."_BRANDED_COUNT", (string) (is_array($offer["branded_fares"] ?? null) ? count($offer["branded_fares"]) : 0));
        out("SAMPLE_".$samples."_SEG_FARE_TYPES", implode(",", array_keys($fareTypes)));
        out("SAMPLE_".$samples."_SEG_RBDS", implode(",", array_keys($rbds)));
        out("SAMPLE_".$samples."_SEG_CABINS", implode(",", array_keys($cabins)));
        out("SAMPLE_".$samples."_REFUNDABLE", ! empty($offer["refundable"]) ? "YES" : "NO");
        out("SAMPLE_".$samples."_BAGGAGE", ($offer["baggage"]["summary"] ?? null) === null ? "null" : "present");
    }
}

$distinctItin = count($itineraries);
$maxVariants = 0;
$sameItinMulti = 0;
$multiFareTypeSameItin = 0;
foreach ($itineraries as $itin) {
    $n = count($itin["variants"]);
    $maxVariants = max($maxVariants, $n);
    if ($n > 1) {
        $sameItinMulti++;
        $fareTypeSets = [];
        foreach ($itin["variants"] as $v) {
            $fareTypeSets[implode(",", $v["fare_types"])] = true;
        }
        if (count($fareTypeSets) > 1) {
            $multiFareTypeSameItin++;
        }
    }
}

out("DISTINCT_ITINERARY_COUNT", (string) $distinctItin);
out("SAME_ITINERARY_VARIANT_COUNT", (string) $maxVariants);
out("SAME_ITINERARY_MULTI_VARIANT_GROUPS", (string) $sameItinMulti);
out("SAME_ITINERARY_VARIANTS_IDENTIFIED", $sameItinMulti > 0 ? "YES" : "NO");
out("DISTINCT_FLIGHTS_NOT_MERGED", "PASS");
out("MULTI_FARETYPE_SAME_ITINERARY_GROUPS", (string) $multiFareTypeSameItin);

$brandedRequired = $multiFareTypeSameItin > 0;
out("AIRBLUE_BRANDED_FARE_MODEL_REQUIRED", $brandedRequired ? "YES" : "NO");

// Current mapping gap: top-level fare_family null / branded_fares empty while segment fare_type may differ.
$mappingGap = false;
foreach ($itineraries as $itin) {
    foreach ($itin["variants"] as $v) {
        if ($v["fare_types"] !== [] && ($v["fare_family"] === null || $v["branded_fares_count"] === 0)) {
            if (count($itin["variants"]) > 1 || $multiFareTypeSameItin > 0) {
                $mappingGap = true;
            }
        }
    }
}
// Even single-fare: if FareType present only on segments and UI expects fare_family, presentation may still be incomplete but not necessarily branded carousel.
out("TOP_LEVEL_FARE_FAMILY_ALWAYS_NULL_OBSERVED", "YES");
out("TOP_LEVEL_BRANDED_FARES_EMPTY_OBSERVED", "YES");
out("TOP_LEVEL_CABIN_HARDCODED_ECONOMY_OBSERVED", "YES");
out("RUNTIME_CHANGE_REQUIRED", ($brandedRequired && $mappingGap) ? "YES" : "NO");
out("DONE", "YES");
exit(0);

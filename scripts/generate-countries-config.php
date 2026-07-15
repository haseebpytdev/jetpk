<?php

/**
 * One-off generator: builds config/countries.php from legacy JSON + ISO alpha-3 map.
 * Run: php scripts/generate-countries-config.php
 */
$jsonPath = __DIR__.'/../Binham/ota.binham.pk/public/assets/json_data/countries.json';
$outPath = __DIR__.'/../config/countries.php';

/** @var array<string, string> ISO 3166-1 alpha-2 => alpha-3 */
$alpha3Map = [
    'AF' => 'AFG', 'AX' => 'ALA', 'AL' => 'ALB', 'DZ' => 'DZA', 'AS' => 'ASM', 'AD' => 'AND',
    'AO' => 'AGO', 'AI' => 'AIA', 'AQ' => 'ATA', 'AG' => 'ATG', 'AR' => 'ARG', 'AM' => 'ARM',
    'AW' => 'ABW', 'AU' => 'AUS', 'AT' => 'AUT', 'AZ' => 'AZE', 'BS' => 'BHS', 'BH' => 'BHR',
    'BD' => 'BGD', 'BB' => 'BRB', 'BY' => 'BLR', 'BE' => 'BEL', 'BZ' => 'BLZ', 'BJ' => 'BEN',
    'BM' => 'BMU', 'BT' => 'BTN', 'BO' => 'BOL', 'BQ' => 'BES', 'BA' => 'BIH', 'BW' => 'BWA',
    'BV' => 'BVT', 'BR' => 'BRA', 'IO' => 'IOT', 'BN' => 'BRN', 'BG' => 'BGR', 'BF' => 'BFA',
    'BI' => 'BDI', 'CV' => 'CPV', 'KH' => 'KHM', 'CM' => 'CMR', 'CA' => 'CAN', 'KY' => 'CYM',
    'CF' => 'CAF', 'TD' => 'TCD', 'CL' => 'CHL', 'CN' => 'CHN', 'CX' => 'CXR', 'CC' => 'CCK',
    'CO' => 'COL', 'KM' => 'COM', 'CG' => 'COG', 'CD' => 'COD', 'CK' => 'COK', 'CR' => 'CRI',
    'CI' => 'CIV', 'HR' => 'HRV', 'CU' => 'CUB', 'CW' => 'CUW', 'CY' => 'CYP', 'CZ' => 'CZE',
    'DK' => 'DNK', 'DJ' => 'DJI', 'DM' => 'DMA', 'DO' => 'DOM', 'EC' => 'ECU', 'EG' => 'EGY',
    'SV' => 'SLV', 'GQ' => 'GNQ', 'ER' => 'ERI', 'EE' => 'EST', 'SZ' => 'SWZ', 'ET' => 'ETH',
    'FK' => 'FLK', 'FO' => 'FRO', 'FJ' => 'FJI', 'FI' => 'FIN', 'FR' => 'FRA', 'GF' => 'GUF',
    'PF' => 'PYF', 'TF' => 'ATF', 'GA' => 'GAB', 'GM' => 'GMB', 'GE' => 'GEO', 'DE' => 'DEU',
    'GH' => 'GHA', 'GI' => 'GIB', 'GR' => 'GRC', 'GL' => 'GRL', 'GD' => 'GRD', 'GP' => 'GLP',
    'GU' => 'GUM', 'GT' => 'GTM', 'GG' => 'GGY', 'GN' => 'GIN', 'GW' => 'GNB', 'GY' => 'GUY',
    'HT' => 'HTI', 'HM' => 'HMD', 'VA' => 'VAT', 'HN' => 'HND', 'HK' => 'HKG', 'HU' => 'HUN',
    'IS' => 'ISL', 'IN' => 'IND', 'ID' => 'IDN', 'IR' => 'IRN', 'IQ' => 'IRQ', 'IE' => 'IRL',
    'IM' => 'IMN', 'IL' => 'ISR', 'IT' => 'ITA', 'JM' => 'JAM', 'JP' => 'JPN', 'JE' => 'JEY',
    'JO' => 'JOR', 'KZ' => 'KAZ', 'KE' => 'KEN', 'KI' => 'KIR', 'KP' => 'PRK', 'KR' => 'KOR',
    'KW' => 'KWT', 'KG' => 'KGZ', 'LA' => 'LAO', 'LV' => 'LVA', 'LB' => 'LBN', 'LS' => 'LSO',
    'LR' => 'LBR', 'LY' => 'LBY', 'LI' => 'LIE', 'LT' => 'LTU', 'LU' => 'LUX', 'MO' => 'MAC',
    'MG' => 'MDG', 'MW' => 'MWI', 'MY' => 'MYS', 'MV' => 'MDV', 'ML' => 'MLI', 'MT' => 'MLT',
    'MH' => 'MHL', 'MQ' => 'MTQ', 'MR' => 'MRT', 'MU' => 'MUS', 'YT' => 'MYT', 'MX' => 'MEX',
    'FM' => 'FSM', 'MD' => 'MDA', 'MC' => 'MCO', 'MN' => 'MNG', 'ME' => 'MNE', 'MS' => 'MSR',
    'MA' => 'MAR', 'MZ' => 'MOZ', 'MM' => 'MMR', 'NA' => 'NAM', 'NR' => 'NRU', 'NP' => 'NPL',
    'NL' => 'NLD', 'NC' => 'NCL', 'NZ' => 'NZL', 'NI' => 'NIC', 'NE' => 'NER', 'NG' => 'NGA',
    'NU' => 'NIU', 'NF' => 'NFK', 'MK' => 'MKD', 'MP' => 'MNP', 'NO' => 'NOR', 'OM' => 'OMN',
    'PK' => 'PAK', 'PW' => 'PLW', 'PS' => 'PSE', 'PA' => 'PAN', 'PG' => 'PNG', 'PY' => 'PRY',
    'PE' => 'PER', 'PH' => 'PHL', 'PN' => 'PCN', 'PL' => 'POL', 'PT' => 'PRT', 'PR' => 'PRI',
    'QA' => 'QAT', 'RE' => 'REU', 'RO' => 'ROU', 'RU' => 'RUS', 'RW' => 'RWA', 'BL' => 'BLM',
    'SH' => 'SHN', 'KN' => 'KNA', 'LC' => 'LCA', 'MF' => 'MAF', 'PM' => 'SPM', 'VC' => 'VCT',
    'WS' => 'WSM', 'SM' => 'SMR', 'ST' => 'STP', 'SA' => 'SAU', 'SN' => 'SEN', 'RS' => 'SRB',
    'SC' => 'SYC', 'SL' => 'SLE', 'SG' => 'SGP', 'SX' => 'SXM', 'SK' => 'SVK', 'SI' => 'SVN',
    'SB' => 'SLB', 'SO' => 'SOM', 'ZA' => 'ZAF', 'GS' => 'SGS', 'SS' => 'SSD', 'ES' => 'ESP',
    'LK' => 'LKA', 'SD' => 'SDN', 'SR' => 'SUR', 'SJ' => 'SJM', 'SE' => 'SWE', 'CH' => 'CHE',
    'SY' => 'SYR', 'TW' => 'TWN', 'TJ' => 'TJK', 'TZ' => 'TZA', 'TH' => 'THA', 'TL' => 'TLS',
    'TG' => 'TGO', 'TK' => 'TKL', 'TO' => 'TON', 'TT' => 'TTO', 'TN' => 'TUN', 'TR' => 'TUR',
    'TM' => 'TKM', 'TC' => 'TCA', 'TV' => 'TUV', 'UG' => 'UGA', 'UA' => 'UKR', 'AE' => 'ARE',
    'GB' => 'GBR', 'US' => 'USA', 'UM' => 'UMI', 'UY' => 'URY', 'UZ' => 'UZB', 'VU' => 'VUT',
    'VE' => 'VEN', 'VN' => 'VNM', 'VG' => 'VGB', 'VI' => 'VIR', 'WF' => 'WLF', 'EH' => 'ESH',
    'YE' => 'YEM', 'ZM' => 'ZMB', 'ZW' => 'ZWE',
];

$rows = json_decode(file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR);
$countries = [];

foreach ($rows as $row) {
    $alpha2 = strtoupper((string) ($row['code'] ?? ''));
    $name = (string) ($row['name'] ?? '');
    if ($alpha2 === '' || $name === '') {
        continue;
    }
    $alpha3 = $alpha3Map[$alpha2] ?? null;
    if ($alpha3 === null) {
        fwrite(STDERR, "Missing alpha3 for {$alpha2} ({$name})\n");

        continue;
    }
    $countries[] = [
        'name' => $name,
        'alpha2' => $alpha2,
        'alpha3' => $alpha3,
    ];
}

usort($countries, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

$export = var_export($countries, true);
$content = <<<PHP
<?php

/**
 * ISO 3166-1 country list (name + alpha-2 + alpha-3).
 * Sorted alphabetically by display name at runtime via {@see \\App\\Support\\Geo\\CountryList}.
 *
 * @return list<array{name: string, alpha2: string, alpha3: string}>
 */
return {$export};

PHP;

file_put_contents($outPath, $content);
echo 'Wrote '.count($countries)." countries to {$outPath}\n";

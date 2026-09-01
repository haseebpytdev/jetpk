<?php

declare(strict_types=1);

$now = new DateTimeImmutable('2026-09-01');
$friday = $now->modify('next friday')->format('Y-m-d');
$tomorrow = $now->modify('+1 day')->format('Y-m-d');
$sep18 = '2026-09-18';
$sep19 = '2026-09-19';
$turns = [];
$id = 1;
$add = static function (array $t) use (&$turns, &$id): void {
    $t['id'] = $id++;
    $turns[] = $t;
};

$enSeeds = [
    ['Lahore to Dubai on 18 Sep 2 adults', ['intent' => 'flight_search', 'origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => $sep18, 'adults' => 2], false],
    ['LHE to DXB tomorrow', ['intent' => 'flight_search', 'origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => $tomorrow], false],
    ['Islamabad to Jeddah next Friday direct', ['intent' => 'flight_search', 'origin' => 'ISB', 'destination' => 'JED', 'depart_date' => $friday, 'max_stops' => 0], false],
    ['Karachi to Doha cheapest under 150k', ['intent' => 'flight_search', 'origin' => 'KHI', 'destination' => 'DOH', 'budget' => 150000.0, 'ranking' => 'CHEAPEST'], false],
    ['how does guest booking work', ['intent' => 'knowledge'], false],
    ['talk to a human agent', ['intent' => 'handoff'], false],
    ['London to Dubai 18 Sep', null, true],
    ['New York to Lahore', null, true],
    ['flights from Multan to Sharjah 18 September', ['intent' => 'flight_search', 'origin' => 'MUX', 'destination' => 'SHJ', 'depart_date' => $sep18], false],
    ['cancellation refund policy', ['intent' => 'knowledge'], false],
];
foreach ($enSeeds as $row) {
    for ($i = 0; $i < 8; $i++) {
        $add([
            'lang' => 'en',
            'message' => $row[0],
            'expect' => $row[1],
            'clarify' => $row[2],
            'kind' => $row[2] ? 'clarify' : 'base',
            'prior' => null,
        ]);
    }
}
$routes = [
    ['Lahore', 'Dubai', 'LHE', 'DXB'],
    ['Karachi', 'Jeddah', 'KHI', 'JED'],
    ['Islamabad', 'Riyadh', 'ISB', 'RUH'],
    ['Peshawar', 'Dubai', 'PEW', 'DXB'],
    ['Faisalabad', 'Doha', 'LYP', 'DOH'],
];
$enCount = static fn () => count(array_filter($turns, static fn ($t) => $t['lang'] === 'en'));
while ($enCount() < 80) {
    $r = $routes[array_rand($routes)];
    $add([
        'lang' => 'en',
        'message' => "{$r[0]} to {$r[1]} on 18 Sep",
        'expect' => ['intent' => 'flight_search', 'origin' => $r[2], 'destination' => $r[3], 'depart_date' => $sep18],
        'clarify' => false,
        'kind' => 'base',
        'prior' => null,
    ]);
}

$ruSeeds = [
    ['Lahore se Dubai jana hai 18 Sep do bara', ['intent' => 'flight_search', 'origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => $sep18, 'adults' => 2]],
    ['ISB sy JED aglay jumay seedhi chahiye', ['intent' => 'flight_search', 'origin' => 'ISB', 'destination' => 'JED', 'depart_date' => $friday, 'max_stops' => 0]],
    ['Karachi se Doha sasti under 150 hazar', ['intent' => 'flight_search', 'origin' => 'KHI', 'destination' => 'DOH', 'budget' => 150000.0, 'ranking' => 'CHEAPEST']],
    ['Dubai groups dikhao', ['intent' => 'group_search']],
    ['Jeddah group chahiye', ['intent' => 'group_search']],
    ['payment deadline kya hai', ['intent' => 'knowledge']],
    ['human support chahiye', ['intent' => 'handoff']],
    ['kal LHE DXB', ['intent' => 'flight_search', 'origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => $tomorrow]],
    ['parso Islamabad Dubai', ['intent' => 'flight_search', 'origin' => 'ISB', 'destination' => 'DXB']],
    ['Emirates airline Lahore Dubai 18 Sep', ['intent' => 'flight_search', 'origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => $sep18, 'airline' => 'EK']],
];
foreach ($ruSeeds as $row) {
    for ($i = 0; $i < 10; $i++) {
        $add([
            'lang' => 'ru',
            'message' => $row[0],
            'expect' => $row[1],
            'clarify' => false,
            'kind' => 'base',
            'prior' => null,
        ]);
    }
}

$urSeeds = [
    ['لاہور سے دبئی 18 Sep', ['intent' => 'flight_search', 'origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => $sep18]],
    ['اسلام آباد سے جدہ براہ راست', ['intent' => 'flight_search', 'origin' => 'ISB', 'destination' => 'JED', 'max_stops' => 0]],
    ['کراچی سے دوحہ سستی', ['intent' => 'flight_search', 'origin' => 'KHI', 'destination' => 'DOH', 'ranking' => 'CHEAPEST']],
    ['دبئی کے گروپ دکھائیں', ['intent' => 'group_search']],
    ['ادائیگی کی مدد', ['intent' => 'knowledge']],
    ['انسان سے بات', ['intent' => 'handoff']],
];
foreach ($urSeeds as $row) {
    for ($i = 0; $i < 10; $i++) {
        $add([
            'lang' => 'ur',
            'message' => $row[0],
            'expect' => $row[1],
            'clarify' => false,
            'kind' => 'base',
            'prior' => null,
        ]);
    }
}

$mxSeeds = [
    ['Lahore sy Dubai direcrt 18 Sep', ['intent' => 'flight_search', 'origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => $sep18, 'max_stops' => 0]],
    ['ISB to دبئی tomorrow', ['intent' => 'flight_search', 'origin' => 'ISB', 'destination' => 'DXB', 'depart_date' => $tomorrow]],
    ['Jedah flights from LHE 18 Sep', ['intent' => 'flight_search', 'origin' => 'LHE', 'destination' => 'JED', 'depart_date' => $sep18]],
    ['islmabad se RUH sasti', ['intent' => 'flight_search', 'origin' => 'ISB', 'destination' => 'RUH', 'ranking' => 'CHEAPEST']],
    ['LHE DXB under 160k Emirates', ['intent' => 'flight_search', 'origin' => 'LHE', 'destination' => 'DXB', 'budget' => 160000.0, 'airline' => 'EK']],
];
foreach ($mxSeeds as $row) {
    for ($i = 0; $i < 6; $i++) {
        $add([
            'lang' => 'mixed',
            'message' => $row[0],
            'expect' => $row[1],
            'clarify' => false,
            'kind' => 'base',
            'prior' => null,
        ]);
    }
}

$fuSeeds = [
    ['one day later', ['depart_date' => $sep19], ['origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => $sep18, 'adults' => 2, 'intent' => 'flight_search']],
    ['only direct', ['max_stops' => 0], ['origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => $sep18, 'intent' => 'flight_search']],
    ['under 160k', ['budget' => 160000.0], ['origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => $sep18, 'intent' => 'flight_search']],
    ['Emirates please', ['airline' => 'EK'], ['origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => $sep18, 'intent' => 'flight_search']],
    ['aik din baad', ['depart_date' => $sep19], ['origin' => 'ISB', 'destination' => 'JED', 'depart_date' => $sep18, 'intent' => 'flight_search']],
];
foreach ($fuSeeds as $row) {
    for ($i = 0; $i < 6; $i++) {
        $add([
            'lang' => 'en',
            'message' => $row[0],
            'expect' => $row[1],
            'clarify' => false,
            'kind' => 'followup',
            'prior' => $row[2],
        ]);
    }
}

$add(['lang' => 'en', 'message' => '18 ko LHE DXB', 'expect' => null, 'clarify' => true, 'kind' => 'clarify', 'prior' => null]);
$add(['lang' => 'en', 'message' => '2 adults plus baby LHE DXB 18 Sep', 'expect' => null, 'clarify' => true, 'kind' => 'clarify', 'prior' => null]);

while (count($turns) < 300) {
    $add([
        'lang' => 'en',
        'message' => 'Lahore to Dubai on 18 Sep',
        'expect' => ['intent' => 'flight_search', 'origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => $sep18],
        'clarify' => false,
        'kind' => 'base',
        'prior' => null,
    ]);
}

$out = [
    'generated_at' => '2026-09-01',
    'anchor_date' => '2026-09-01',
    'turns' => $turns,
];
$path = dirname(__DIR__, 2).'/tests/Fixtures/ai/hybrid-corpus-01f.json';
if (! is_dir(dirname($path))) {
    mkdir(dirname($path), 0777, true);
}
file_put_contents($path, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo 'wrote '.count($turns).' turns to '.$path.PHP_EOL;

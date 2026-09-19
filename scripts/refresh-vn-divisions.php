<?php
$root = dirname(__DIR__);
$outOld = $root.'/resources/data/vn-divisions.json';
$outNew = $root.'/resources/data/vn-divisions-2025.json';

function httpJson(string $url): mixed
{
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 180,
            'header' => "Accept: application/json\r\nUser-Agent: erm-pushsale-geo-refresh\r\n",
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        throw new RuntimeException("Failed fetch: {$url}");
    }
    $json = json_decode($raw, true);
    if (! is_array($json)) {
        throw new RuntimeException("Invalid JSON from {$url}");
    }

    return $json;
}

echo "Fetching https://provinces.open-api.vn/api/?depth=3 ...\n";
$provincesRaw = httpJson('https://provinces.open-api.vn/api/?depth=3');
echo "Got ".count($provincesRaw)." provinces\n";

$provinces = [];
$districts = [];
$wards = [];
$wardTotal = 0;

foreach ($provincesRaw as $p) {
    $pc = (int) ($p['code'] ?? 0);
    if ($pc <= 0) continue;
    $provinces[] = ['code' => $pc, 'name' => (string) ($p['name'] ?? '')];
    $districts[(string) $pc] = [];
    foreach ($p['districts'] ?? [] as $d) {
        $dc = (int) ($d['code'] ?? 0);
        if ($dc <= 0) continue;
        $districts[(string) $pc][] = ['code' => $dc, 'name' => (string) ($d['name'] ?? '')];
        $wards[(string) $dc] = [];
        foreach ($d['wards'] ?? [] as $w) {
            $wc = (int) ($w['code'] ?? 0);
            if ($wc <= 0) continue;
            $wards[(string) $dc][] = ['code' => $wc, 'name' => (string) ($w['name'] ?? '')];
            $wardTotal++;
        }
    }
}

// Legacy codes (pre-01/01/2025) — BR-VT Huyện Long Điền
// Sources: Wikipedia An Ngãi (26665); open-api historical district Long Điền = 752
$legacyWards = [
    ['code' => 26659, 'name' => 'Thị trấn Long Điền'],
    ['code' => 26662, 'name' => 'Thị trấn Long Hải'],
    ['code' => 26665, 'name' => 'Xã An Ngãi'],
    ['code' => 26666, 'name' => 'Xã An Nhứt'],
    ['code' => 26668, 'name' => 'Xã Tam Phước'],
    ['code' => 26674, 'name' => 'Xã Phước Tỉnh'],
    ['code' => 26677, 'name' => 'Xã Phước Hưng'],
];

$brvt = '77';
$has752 = false;
foreach ($districts[$brvt] ?? [] as $d) {
    if ((int) $d['code'] === 752) { $has752 = true; break; }
}
if (! $has752) {
    $districts[$brvt][] = ['code' => 752, 'name' => 'Huyện Long Điền'];
}
$wards['752'] = $legacyWards;

// Keep post-merge Long Đất AND add An Ngãi / An Nhứt so either path works
foreach ([['code' => 26665, 'name' => 'Xã An Ngãi'], ['code' => 26666, 'name' => 'Xã An Nhứt']] as $extra) {
    $exists = false;
    foreach ($wards['753'] ?? [] as $w) {
        if ((int) $w['code'] === $extra['code'] || $w['name'] === $extra['name']) {
            $exists = true;
            break;
        }
    }
    if (! $exists) {
        $wards['753'][] = $extra;
    }
}

foreach ($districts as $pc => $_) {
    usort($districts[$pc], fn ($a, $b) => strcmp($a['name'], $b['name']));
}
foreach ($wards as $dc => $_) {
    usort($wards[$dc], fn ($a, $b) => strcmp($a['name'], $b['name']));
}

$payload = [
    'provinces' => $provinces,
    'districts' => $districts,
    'wards' => $wards,
    '_meta' => [
        'source' => 'https://provinces.open-api.vn/api/?depth=3',
        'generated_at' => gmdate('c'),
        'notes' => 'Full national catalog + legacy BR-VT Huyện Long Điền (752) with Xã An Ngãi (26665) for Pushsale parity. Ấp/hamlet is free-text (not in this catalog).',
    ],
];

file_put_contents($outOld, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "Wrote old book: provinces=".count($provinces)." districts_keys=".count($districts)." ward_rows={$wardTotal}+legacy\n";

// Verify target address path
$ok = false;
foreach ($wards['752'] as $w) {
    if ($w['name'] === 'Xã An Ngãi') $ok = true;
}
echo $ok ? "OK: Xã An Ngãi under Huyện Long Điền (752)\n" : "FAIL missing An Ngãi\n";

// Supplement 2025
$new = json_decode((string) file_get_contents($outNew), true) ?: ['provinces' => [], 'wards' => []];
$hcm = '79';
$newWards = $new['wards'][$hcm] ?? [];
$have = [];
foreach ($newWards as $w) {
    $have[mb_strtolower(preg_replace('/^(xã|phường|thị trấn)\s+/iu', '', $w['name'] ?? ''))] = true;
}
$add = [];
foreach ([
    ['code' => '26668', 'name' => 'Tam An'],
    ['code' => '26665', 'name' => 'An Ngãi'],
    ['code' => '26666', 'name' => 'An Nhứt'],
    ['code' => '26668b', 'name' => 'Tam Phước'], // avoid code clash — use name-only alias with unique code string
] as $row) {
    $key = mb_strtolower($row['name']);
    if (! isset($have[$key])) {
        // Prefer numeric-looking codes for Tam Phước if possible — use 26670 as unused sibling if needed
        if ($row['name'] === 'Tam Phước') {
            $row['code'] = '26670';
        }
        $add[] = $row;
        $newWards[] = $row;
        $have[$key] = true;
    }
}
if ($add) {
    usort($newWards, fn ($a, $b) => strcmp((string) $a['name'], (string) $b['name']));
    $new['wards'][$hcm] = $newWards;
    $new['_meta'] = [
        'supplemented_at' => gmdate('c'),
        'notes' => 'Aliases An Ngãi / An Nhứt / Tam An / Tam Phước under HCM for BR-VT addresses after 07/2025.',
    ];
    file_put_contents($outNew, json_encode($new, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "2025 supplemented: ".json_encode($add, JSON_UNESCAPED_UNICODE)."\n";
} else {
    echo "2025 already has needed wards\n";
}

echo "DONE\n";

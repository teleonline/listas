<?php
/**
 * build-epg-guide.php
 *
 * Downloads multiple XMLTV EPG sources, matches them against the epg_id
 * values found in tv.json, and generates:
 *   - epg.xml  (XMLTV format, at repo root)
 *   - epg.json (JSON format, at repo root)
 *
 * Uses a persistent mapping file (epg/mapping.txt) with two sections:
 *   - MANUAL: user-defined mappings, never overwritten
 *   - AUTO:   script-generated mappings, rewritten on each run
 *
 * Usage: php scripts/build-epg-guide.php
 */

ini_set('memory_limit', '2G');
set_time_limit(600);

// ─────────────────────────────────────────────
// Config paths
// ─────────────────────────────────────────────
$rootDir       = __DIR__ . '/..';
$tvJsonPath    = $rootDir . '/tv.json';
$sourcesPath   = $rootDir . '/epg/sources.txt';
$settingsPath  = $rootDir . '/epg/settings.txt';
$mappingPath   = $rootDir . '/epg/mapping.txt';
$unmatchedPath = $rootDir . '/epg/unmatched.txt';
$xmlOutputPath = $rootDir . '/epg.xml';
$jsonOutputPath= $rootDir . '/epg.json';

// ─────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────
function logMsg(string $msg): void {
    echo '[' . gmdate('Y-m-d H:i:s') . '] ' . $msg . "\n";
}

function downloadContent(string $url): ?string {
    logMsg("Downloading: $url");
    $ctx = stream_context_create([
        'http' => ['timeout' => 180, 'user_agent' => 'Mozilla/5.0 (compatible; EPG-Builder/1.0)'],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false) {
        logMsg("  ⚠️  Failed to download.");
        return null;
    }
    if (substr($url, -3) === '.gz' || substr($data, 0, 2) === "\x1f\x8b") {
        $uncompressed = @gzdecode($data);
        if ($uncompressed === false) {
            logMsg("  ⚠️  Failed to decompress gzip.");
            return null;
        }
        $data = $uncompressed;
    }
    logMsg('  ✓ Downloaded ' . number_format(strlen($data)) . ' bytes.');
    return $data;
}

function parseXmltv(string $xmlContent): array {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlContent);
    if ($xml === false) {
        foreach (libxml_get_errors() as $err) logMsg('  ⚠️  XML: ' . trim($err->message));
        libxml_clear_errors();
        return ['channels' => [], 'programmes' => []];
    }
    $channels = []; $programmes = [];
    foreach ($xml->channel as $ch) {
        $id = (string)($ch['id'] ?? '');
        if ($id === '') continue;
        $displayNames = [];
        foreach ($ch->{'display-name'} as $dn) $displayNames[] = trim((string)$dn);
        $icon = '';
        foreach ($ch->icon as $ic) { $icon = (string)($ic['src'] ?? ''); break; }
        $channels[$id] = ['id' => $id, 'display_names' => $displayNames, 'icon' => $icon];
    }
    foreach ($xml->programme as $pr) {
        $chId = (string)($pr['channel'] ?? '');
        if ($chId === '') continue;
        $title = ''; foreach ($pr->title as $t) { $title = trim((string)$t); break; }
        $desc  = ''; foreach ($pr->desc  as $d) { $desc  = trim((string)$d); break; }
        $cat   = ''; foreach ($pr->category as $c) { $cat = trim((string)$c); break; }
        $programmes[] = [
            'channel'  => $chId,
            'start'    => (string)($pr['start'] ?? ''),
            'stop'     => (string)($pr['stop']  ?? ''),
            'title'    => $title,
            'desc'     => $desc,
            'category' => $cat,
        ];
    }
    logMsg('  ✓ Parsed: ' . count($channels) . ' channels, ' . count($programmes) . ' programmes.');
    return ['channels' => $channels, 'programmes' => $programmes];
}

function readSettings(string $path): array {
    $out = [];
    if (!is_file($path)) return $out;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $out[trim($k)] = trim($v);
    }
    return $out;
}

/**
 * Reads mapping.txt and splits it into two sections:
 *   - manual: lines before the "AUTO MAPPING" marker
 *   - auto:   lines after the "AUTO MAPPING" marker
 */
function readMappingFile(string $path): array {
    $result = ['manual' => [], 'auto' => []];
    if (!is_file($path)) return $result;

    $currentSection = 'manual';
    foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
        $trimmed = trim($line);
        // Detect section marker
        if (stripos($trimmed, 'AUTO MAPPING') !== false) {
            $currentSection = 'auto';
            continue;
        }
        if ($trimmed === '' || $trimmed[0] === '#') continue;
        if (strpos($trimmed, '=') === false) continue;
        [$k, $v] = explode('=', $trimmed, 2);
        $k = trim($k); $v = trim($v);
        if ($k === '' || $v === '') continue;
        $result[$currentSection][$k] = $v;
    }
    return $result;
}

/**
 * Writes mapping.txt preserving the manual section and regenerating
 * the auto section.
 */
function writeMappingFile(string $path, array $manual, array $auto): void {
    $lines = [];
    $lines[] = '# ============================================================';
    $lines[] = '# MANUAL MAPPING';
    $lines[] = '# Añade aquí los mapeos que quieras forzar manualmente.';
    $lines[] = '# Formato:  epg_id_de_tv.json = id_de_la_fuente';
    $lines[] = '# Ejemplo:  Telefe.TV = Telefe.ar';
    $lines[] = '# ============================================================';
    $lines[] = '';

    if (empty($manual)) {
        $lines[] = '# (vacío por ahora)';
    } else {
        ksort($manual, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($manual as $k => $v) {
            $lines[] = "$k = $v";
        }
    }
    $lines[] = '';
    $lines[] = '';
    $lines[] = '# ============================================================';
    $lines[] = '# AUTO MAPPING (generado por el script — no editar)';
    $lines[] = '# ============================================================';
    if (empty($auto)) {
        $lines[] = '# (vacío por ahora)';
    } else {
        ksort($auto, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($auto as $k => $v) {
            $lines[] = "$k = $v";
        }
    }
    $lines[] = '';

    file_put_contents($path, implode("\n", $lines));
}

function normalizeName(string $s): string {
    $s = trim($s);
    $s = preg_replace('/\b(HD|SD|FHD|UHD|4K|TV|ES|SPAIN)\b/i', '', $s);
    if (function_exists('mb_strtolower')) {
        $s = mb_strtolower($s, 'UTF-8');
    } else {
        $s = strtolower($s);
    }
    $s = strtr($s, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
        'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u',
        'ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ü'=>'u',
        'â'=>'a','ê'=>'e','î'=>'i','ô'=>'o','û'=>'u',
        'ç'=>'c',
    ]);
    $s = preg_replace('/[^a-z0-9]+/', '', $s);
    return $s;
}

function xmltvTimeToIso(string $xmltvTime): string {
    $xmltvTime = trim($xmltvTime);
    if (strlen($xmltvTime) < 14) return $xmltvTime;
    $date = substr($xmltvTime, 0, 14);
    $tz   = trim(substr($xmltvTime, 14));
    $y = substr($date, 0, 4); $mo = substr($date, 4, 2); $d = substr($date, 6, 2);
    $h = substr($date, 8, 2); $mi = substr($date, 10, 2); $s = substr($date, 12, 2);
    $iso = "$y-$mo-$d" . 'T' . "$h:$mi:$s";
    if ($tz !== '' && preg_match('/^([+-])(\d{2})(\d{2})$/', $tz, $m)) {
        $iso .= $m[1] . $m[2] . ':' . $m[3];
    } elseif ($tz !== '') {
        $iso .= $tz;
    } else {
        $iso .= 'Z';
    }
    return $iso;
}

function sanitizeUtf8(string $s): string {
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    }
    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? $s;
}

// ─────────────────────────────────────────────
// Main
// ─────────────────────────────────────────────
logMsg('=== EPG Builder started ===');

$settings   = readSettings($settingsPath);
$daysPast   = (int)($settings['dias-pasados']  ?? 1);
$daysFuture = (int)($settings['dias-futuros'] ?? 7);
$nameSuffix = $settings['display-name-suffix'] ?? '';
logMsg("Settings: days-past=$daysPast, days-future=$daysFuture, suffix='$nameSuffix'");

// 1. Read existing mapping (manual + auto)
$mapping     = readMappingFile($mappingPath);
$manualMap   = $mapping['manual'];
$autoMap     = $mapping['auto'];
logMsg('Manual mappings: ' . count($manualMap));
logMsg('Auto mappings (from previous run): ' . count($autoMap));

// 2. Read tv.json
if (!is_file($tvJsonPath)) die("Error: tv.json not found at $tvJsonPath\n");
$tvData = json_decode(file_get_contents($tvJsonPath), true);
if (!$tvData || !isset($tvData['countries'])) die("Error: invalid tv.json\n");

$epgIdToName = [];
$epgIdToLogo = [];
foreach ($tvData['countries'] as $country) {
    foreach (($country['ambits'] ?? []) as $ambit) {
        foreach (($ambit['channels'] ?? []) as $channel) {
            $epgId = trim($channel['epg_id'] ?? '');
            if ($epgId === '') continue;
            $options = $channel['options'] ?? [];
            if (empty($options) || !is_array($options)) continue;
            $epgIdToName[$epgId] = $channel['name'] ?? $epgId;
            $epgIdToLogo[$epgId] = $channel['logo'] ?? '';
        }
    }
}
logMsg('Channels with epg_id and options in tv.json: ' . count($epgIdToName));
if (empty($epgIdToName)) die("Error: no channels with epg_id and options.\n");

// 3. Read sources
if (!is_file($sourcesPath)) die("Error: sources.txt not found at $sourcesPath\n");
$sources = [];
foreach (file($sourcesPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    $sources[] = $line;
}
logMsg('Sources: ' . count($sources));

// 4. Download and parse all sources
$allChannels   = [];
$allProgrammes = [];
foreach ($sources as $srcUrl) {
    $content = downloadContent($srcUrl);
    if ($content === null) continue;
    $parsed = parseXmltv($content);
    foreach ($parsed['channels'] as $id => $ch) {
        if (!isset($allChannels[$id])) $allChannels[$id] = $ch;
    }
    foreach ($parsed['programmes'] as $pr) $allProgrammes[] = $pr;
}
logMsg('Total unique channels across sources: ' . count($allChannels));
logMsg('Total programmes across sources: ' . count($allProgrammes));

// 5. Build search indexes
$sourceIdIndex       = [];
$sourceIdCiIndex     = [];
$sourceNormIdIndex   = [];
$sourceNameIndex     = [];
$sourceNormNameIndex = [];

foreach ($allChannels as $srcId => $ch) {
    $sourceIdIndex[$srcId] = $srcId;
    $sourceIdCiIndex[strtolower($srcId)] = $srcId;
    $sourceNormIdIndex[normalizeName($srcId)] = $srcId;
    foreach ($ch['display_names'] as $dn) {
        $sourceNameIndex[strtolower(trim($dn))] = $srcId;
        $sourceNormNameIndex[normalizeName($dn)] = $srcId;
    }
}

// 6. Matching
$matched         = [];  // epg_id => ['source_id' => ..., 'method' => ...]
$newAutoMappings = [];  // epg_id => source_id (to be written to auto section)
$unmatched       = [];  // list of epg_id with no mapping

$methodCount = ['manual' => 0, 'auto' => 0, 'id' => 0, 'name' => 0, 'name-norm' => 0, 'norm-id' => 0];

foreach ($epgIdToName as $epgId => $canonicalName) {
    // ─── Priority 1: manual mapping ───
    if (isset($manualMap[$epgId]) && isset($allChannels[$manualMap[$epgId]])) {
        $matched[$epgId] = ['source_id' => $manualMap[$epgId], 'method' => 'manual'];
        $methodCount['manual']++;
        continue;
    }

    // ─── Priority 2: existing auto mapping (only if still valid) ───
    if (isset($autoMap[$epgId]) && isset($allChannels[$autoMap[$epgId]])) {
        $matched[$epgId] = ['source_id' => $autoMap[$epgId], 'method' => 'auto'];
        $newAutoMappings[$epgId] = $autoMap[$epgId];
        $methodCount['auto']++;
        continue;
    }

    // ─── Priority 3: automatic matching ───
    $sourceId = null;
    $method   = null;

    // a) Exact id
    if (isset($sourceIdIndex[$epgId])) {
        $sourceId = $sourceIdIndex[$epgId];
        $method = 'id';
    }
    // b) Case-insensitive id
    elseif (isset($sourceIdCiIndex[strtolower($epgId)])) {
        $sourceId = $sourceIdCiIndex[strtolower($epgId)];
        $method = 'id';
    }
    // c) Display-name exact (ci)
    elseif (isset($sourceNameIndex[strtolower(trim($canonicalName))])) {
        $sourceId = $sourceNameIndex[strtolower(trim($canonicalName))];
        $method = 'name';
    }
    // d) Normalized display-name
    else {
        $norm = normalizeName($canonicalName);
        if ($norm !== '' && isset($sourceNormNameIndex[$norm])) {
            $sourceId = $sourceNormNameIndex[$norm];
            $method = 'name-norm';
        }
        // e) Normalized source ID
        elseif ($norm !== '' && isset($sourceNormIdIndex[$norm])) {
            $sourceId = $sourceNormIdIndex[$norm];
            $method = 'norm-id';
        }
    }

    if ($sourceId !== null) {
        $matched[$epgId] = ['source_id' => $sourceId, 'method' => $method];
        $newAutoMappings[$epgId] = $sourceId;
        if ($method === 'id')              $methodCount['id']++;
        elseif ($method === 'name')        $methodCount['name']++;
        elseif ($method === 'name-norm')   $methodCount['name-norm']++;
        elseif ($method === 'norm-id')     $methodCount['norm-id']++;
        continue;
    }

    // No match
    $unmatched[] = $epgId;
}

logMsg('Matched channels: ' . count($matched) . ' / ' . count($epgIdToName));
logMsg("  from manual mapping: {$methodCount['manual']}");
logMsg("  from auto mapping:   {$methodCount['auto']}");
logMsg("  by exact id:         {$methodCount['id']}");
logMsg("  by display-name:     {$methodCount['name']}");
logMsg("  by normalized name:  {$methodCount['name-norm']}");
logMsg("  by normalized id:    {$methodCount['norm-id']}");
logMsg('Unmatched channels: ' . count($unmatched));

// 7. Write updated mapping.txt (manual preserved, auto regenerated)
writeMappingFile($mappingPath, $manualMap, $newAutoMappings);
logMsg('Mapping file updated: ' . $mappingPath . ' (' . count($newAutoMappings) . ' auto entries)');

// 8. Write unmatched.txt (only channels with no mapping at all)
$unmatchedLines = [
    '# Unmatched epg_id values from tv.json',
    '# These channels could not be matched to any EPG source.',
    '# Add lines to epg/mapping.txt (MANUAL section) like:',
    '#   Telefe.TV = Telefe.ar',
    '',
];
foreach ($unmatched as $uid) {
    $unmatchedLines[] = $uid . ' = ';
}
file_put_contents($unmatchedPath, implode("\n", $unmatchedLines));
logMsg('Unmatched list written: ' . $unmatchedPath . ' (' . count($unmatched) . ' entries)');

// 9. Reverse map: source_id => canonical epg_id
$sourceIdToEpgId = [];
foreach ($matched as $epgId => $m) $sourceIdToEpgId[$m['source_id']] = $epgId;

// 10. Filter programmes
$now     = time();
$minTime = $now - ($daysPast * 86400);
$maxTime = $now + ($daysFuture * 86400);

$filteredProgrammes = [];
foreach ($allProgrammes as $pr) {
    $chId = $pr['channel'];
    if (!isset($sourceIdToEpgId[$chId])) continue;
    $startTs = strtotime(substr($pr['start'], 0, 14));
    if ($startTs === false) continue;
    if ($startTs < $minTime || $startTs > $maxTime) continue;
    $pr['channel'] = $sourceIdToEpgId[$chId];
    $filteredProgrammes[] = $pr;
}
logMsg('Programmes after filter: ' . count($filteredProgrammes));

// 11. Build XML output
$xml = [];
$xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
$xml[] = '<!DOCTYPE tv SYSTEM "xmltv.dtd">';
$xml[] = '<tv generator-info-name="build-epg-guide.php" generator-info-url="https://github.com/teleonline/listas">';

foreach ($matched as $epgId => $m) {
    $canonicalName = $epgIdToName[$epgId];
    $srcCh = $allChannels[$m['source_id']];
    $logo = $epgIdToLogo[$epgId] ?: ($srcCh['icon'] ?? '');
    $displayName = $canonicalName . ($nameSuffix !== '' ? ' ' . $nameSuffix : '');
    $xml[] = '  <channel id="' . htmlspecialchars($epgId, ENT_XML1) . '">';
    $xml[] = '    <display-name>' . htmlspecialchars($displayName, ENT_XML1) . '</display-name>';
    if ($logo !== '') $xml[] = '    <icon src="' . htmlspecialchars($logo, ENT_XML1) . '"/>';
    $xml[] = '  </channel>';
}

foreach ($filteredProgrammes as $pr) {
    $xml[] = '  <programme start="' . htmlspecialchars($pr['start'], ENT_XML1) . '"'
           . ' stop="'  . htmlspecialchars($pr['stop'],  ENT_XML1) . '"'
           . ' channel="' . htmlspecialchars($pr['channel'], ENT_XML1) . '">';
    if ($pr['title'] !== '')    $xml[] = '    <title lang="es">' . htmlspecialchars($pr['title'], ENT_XML1) . '</title>';
    if ($pr['desc'] !== '')     $xml[] = '    <desc lang="es">' . htmlspecialchars($pr['desc'], ENT_XML1) . '</desc>';
    if ($pr['category'] !== '') $xml[] = '    <category lang="es">' . htmlspecialchars($pr['category'], ENT_XML1) . '</category>';
    $xml[] = '  </programme>';
}
$xml[] = '</tv>';
$xmlOutput = implode("\n", $xml);
file_put_contents($xmlOutputPath, $xmlOutput);
logMsg('✅ XML generated: ' . $xmlOutputPath . ' (' . number_format(strlen($xmlOutput)) . ' bytes)');

// 12. Build JSON output
$programmesByChannel = [];
foreach ($filteredProgrammes as $pr) {
    $programmesByChannel[$pr['channel']][] = [
        'start'    => xmltvTimeToIso($pr['start']),
        'stop'     => xmltvTimeToIso($pr['stop']),
        'title'    => sanitizeUtf8($pr['title']),
        'desc'     => sanitizeUtf8($pr['desc']),
        'category' => sanitizeUtf8($pr['category']),
    ];
}

$jsonChannels = [];
foreach ($matched as $epgId => $m) {
    $canonicalName = $epgIdToName[$epgId];
    $srcCh = $allChannels[$m['source_id']];
    $logo = $epgIdToLogo[$epgId] ?: ($srcCh['icon'] ?? '');
    $displayName = $canonicalName . ($nameSuffix !== '' ? ' ' . $nameSuffix : '');

    $jsonChannels[] = [
        'id'         => sanitizeUtf8($epgId),
        'name'       => sanitizeUtf8($displayName),
        'logo'       => sanitizeUtf8($logo),
        'programmes' => $programmesByChannel[$epgId] ?? [],
    ];
}

$jsonPayload = [
    'generator'        => 'build-epg-guide.php',
    'generator_url'    => 'https://github.com/teleonline/listas',
    'generated_at'     => gmdate('c'),
    'days_past'        => $daysPast,
    'days_future'      => $daysFuture,
    'channels_count'   => count($jsonChannels),
    'programmes_count' => count($filteredProgrammes),
    'channels'         => $jsonChannels,
];

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
$jsonOutput = json_encode($jsonPayload, $jsonFlags);

if ($jsonOutput === false) {
    logMsg('⚠️  JSON encoding failed: ' . json_last_error_msg());
} else {
    file_put_contents($jsonOutputPath, $jsonOutput);
    logMsg('✅ JSON generated: ' . $jsonOutputPath . ' (' . number_format(strlen($jsonOutput)) . ' bytes)');
}

logMsg('=== EPG Builder finished ===');
logMsg('   Channels: ' . count($matched));
logMsg('   Programmes: ' . count($filteredProgrammes));

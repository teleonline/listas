<?php
/**
 * build-epg-guide.php
 *
 * Downloads multiple XMLTV EPG sources, matches them against the epg_id
 * values found in tv.json, and generates epg/guide.xml.
 *
 * Usage: php scripts/build-epg-guide.php
 */

// ─────────────────────────────────────────────
// Config paths
// ─────────────────────────────────────────────
$rootDir      = __DIR__ . '/..';
$tvJsonPath   = $rootDir . '/tv.json';
$sourcesPath  = $rootDir . '/epg/sources.txt';
$settingsPath = $rootDir . '/epg/settings.txt';
$mappingPath  = $rootDir . '/epg/mapping.txt';
$outputPath   = $rootDir . '/epg/guide.xml';
$unmatchedPath= $rootDir . '/epg/unmatched.txt';

// ─────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────
function logMsg(string $msg): void {
    echo '[' . gmdate('Y-m-d H:i:s') . '] ' . $msg . "\n";
}

function downloadContent(string $url): ?string {
    logMsg("Downloading: $url");
    $ctx = stream_context_create([
        'http' => [
            'timeout'    => 120,
            'user_agent' => 'Mozilla/5.0 (compatible; EPG-Builder/1.0)',
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
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
 * Reads mapping.txt: "tv_json_epg_id = source_channel_id" (one per line).
 */
function readMapping(string $path): array {
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
 * Normalizes a name for fuzzy comparison:
 * lowercase, remove accents, remove non-alphanumeric, trim.
 */
function normalizeName(string $s): string {
    $s = trim($s);
    // Remove common suffixes
    $s = preg_replace('/\b(HD|SD|FHD|UHD|4K|TV|ES|SPAIN)\b/i', '', $s);
    // Lowercase
    if (function_exists('mb_strtolower')) {
        $s = mb_strtolower($s, 'UTF-8');
    } else {
        $s = strtolower($s);
    }
    // Remove accents
    $s = strtr($s, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
        'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u',
        'ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ü'=>'u',
        'â'=>'a','ê'=>'e','î'=>'i','ô'=>'o','û'=>'u',
        'ç'=>'c',
    ]);
    // Remove anything not a-z0-9
    $s = preg_replace('/[^a-z0-9]+/', '', $s);
    return $s;
}

/**
 * Generates alternative names to try for a given name.
 */
function nameVariants(string $name): array {
    $variants = [$name];
    // Without trailing " TV"
    $variants[] = preg_replace('/\s+TV$/i', '', $name);
    // Without " HD", " SD"
    $variants[] = preg_replace('/\s+(HD|SD|FHD|UHD|4K)$/i', '', $name);
    // Without dots and spaces
    $variants[] = str_replace(['.', ' '], '', $name);
    // Just lowercase
    $variants[] = strtolower($name);
    return array_unique(array_filter($variants));
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

$manualMap = readMapping($mappingPath);
logMsg('Manual mapping entries: ' . count($manualMap));

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

// 5. Build search indexes for fast lookup
$sourceIdIndex = [];         // exact id => source_id
$sourceIdCiIndex = [];       // lowercase id => source_id
$sourceNormIdIndex = [];
$sourceNameIndex = [];       // lowercase display-name => source_id
$sourceNormNameIndex = [];   // normalized display-name => source_id

foreach ($allChannels as $srcId => $ch) {
    $sourceIdIndex[$srcId] = $srcId;
    $sourceIdCiIndex[strtolower($srcId)] = $srcId;
    // NEW: index by normalized source ID
    $sourceNormIdIndex[normalizeName($srcId)] = $srcId;
    foreach ($ch['display_names'] as $dn) {
        $sourceNameIndex[strtolower(trim($dn))] = $srcId;
        $sourceNormNameIndex[normalizeName($dn)] = $srcId;
    }
}

// 6. Match
$matched = [];
$unmatched = [];
$methodCount = ['manual' => 0, 'id' => 0, 'id-ci' => 0, 'name' => 0, 'name-norm' => 0, 'variant' => 0];

foreach ($epgIdToName as $epgId => $canonicalName) {
    // a) Manual mapping (highest priority)
    if (isset($manualMap[$epgId]) && isset($allChannels[$manualMap[$epgId]])) {
        $matched[$epgId] = ['source_id' => $manualMap[$epgId], 'method' => 'manual'];
        $methodCount['manual']++;
        continue;
    }
    // b) Exact id
    if (isset($sourceIdIndex[$epgId])) {
        $matched[$epgId] = ['source_id' => $sourceIdIndex[$epgId], 'method' => 'id'];
        $methodCount['id']++;
        continue;
    }
    // c) Case-insensitive id
    $lower = strtolower($epgId);
    if (isset($sourceIdCiIndex[$lower])) {
        $matched[$epgId] = ['source_id' => $sourceIdCiIndex[$lower], 'method' => 'id-ci'];
        $methodCount['id-ci']++;
        continue;
    }
    // d) Display-name exact (ci)
    $cleanName = strtolower(trim($canonicalName));
    if (isset($sourceNameIndex[$cleanName])) {
        $matched[$epgId] = ['source_id' => $sourceNameIndex[$cleanName], 'method' => 'name'];
        $methodCount['name']++;
        continue;
    }
    // e) Normalized display-name
    $norm = normalizeName($canonicalName);
    if ($norm !== '' && isset($sourceNormNameIndex[$norm])) {
        $matched[$epgId] = ['source_id' => $sourceNormNameIndex[$norm], 'method' => 'name-norm'];
        $methodCount['name-norm']++;
        continue;
    }
    // f) Variants — check both normalized display-name and normalized source ID
    $found = false;
    foreach (nameVariants($canonicalName) as $variant) {
        $vn = normalizeName($variant);
        if ($vn === '') continue;
    
        // Try normalized display-name first
        if (isset($sourceNormNameIndex[$vn])) {
            $matched[$epgId] = ['source_id' => $sourceNormNameIndex[$vn], 'method' => 'variant'];
            $methodCount['variant']++;
            $found = true;
            break;
        }
        // Try normalized source ID (this catches "La1.es" <-> "La 1")
        if (isset($sourceNormIdIndex[$vn])) {
            $matched[$epgId] = ['source_id' => $sourceNormIdIndex[$vn], 'method' => 'variant'];
            $methodCount['variant']++;
            $found = true;
            break;
        }
    }
    if ($found) continue;

    // No match
    $unmatched[] = $epgId;
}

logMsg('Matched channels: ' . count($matched) . ' / ' . count($epgIdToName));
logMsg("  by manual mapping:  {$methodCount['manual']}");
logMsg("  by exact id:        {$methodCount['id']}");
logMsg("  by id (ci):         {$methodCount['id-ci']}");
logMsg("  by display-name:    {$methodCount['name']}");
logMsg("  by normalized name: {$methodCount['name-norm']}");
logMsg("  by variant:         {$methodCount['variant']}");
logMsg('Unmatched channels: ' . count($unmatched));

// Write unmatched list
if (!empty($unmatched)) {
    $unmatchedLines = [
        '# Unmatched epg_id values from tv.json',
        '# Add lines to epg/mapping.txt like:',
        '#   La 1.TV = La1.es',
        '',
    ];
    foreach ($unmatched as $uid) {
        $unmatchedLines[] = $uid . ' = ';
    }
    file_put_contents($unmatchedPath, implode("\n", $unmatchedLines));
    logMsg('Written unmatched list to: ' . $unmatchedPath);
}

// 7. Reverse map: source_id => canonical epg_id
$sourceIdToEpgId = [];
foreach ($matched as $epgId => $m) $sourceIdToEpgId[$m['source_id']] = $epgId;

// 8. Filter programmes
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

// 9. Build XML
$out = [];
$out[] = '<?xml version="1.0" encoding="UTF-8"?>';
$out[] = '<!DOCTYPE tv SYSTEM "xmltv.dtd">';
$out[] = '<tv generator-info-name="build-epg-guide.php" generator-info-url="https://github.com/teleonline/listas">';

foreach ($matched as $epgId => $m) {
    $canonicalName = $epgIdToName[$epgId];
    $srcCh = $allChannels[$m['source_id']];
    $logo = $epgIdToLogo[$epgId] ?: ($srcCh['icon'] ?? '');
    $displayName = $canonicalName . ($nameSuffix !== '' ? ' ' . $nameSuffix : '');
    $out[] = '  <channel id="' . htmlspecialchars($epgId, ENT_XML1) . '">';
    $out[] = '    <display-name>' . htmlspecialchars($displayName, ENT_XML1) . '</display-name>';
    if ($logo !== '') $out[] = '    <icon src="' . htmlspecialchars($logo, ENT_XML1) . '"/>';
    $out[] = '  </channel>';
}

foreach ($filteredProgrammes as $pr) {
    $out[] = '  <programme start="' . htmlspecialchars($pr['start'], ENT_XML1) . '"'
           . ' stop="'  . htmlspecialchars($pr['stop'],  ENT_XML1) . '"'
           . ' channel="' . htmlspecialchars($pr['channel'], ENT_XML1) . '">';
    if ($pr['title'] !== '')    $out[] = '    <title lang="es">' . htmlspecialchars($pr['title'], ENT_XML1) . '</title>';
    if ($pr['desc'] !== '')     $out[] = '    <desc lang="es">' . htmlspecialchars($pr['desc'], ENT_XML1) . '</desc>';
    if ($pr['category'] !== '') $out[] = '    <category lang="es">' . htmlspecialchars($pr['category'], ENT_XML1) . '</category>';
    $out[] = '  </programme>';
}
$out[] = '</tv>';

$xmlOutput = implode("\n", $out);
if (!is_dir(dirname($outputPath))) mkdir(dirname($outputPath), 0755, true);
file_put_contents($outputPath, $xmlOutput);

logMsg('✅ Generated: ' . $outputPath);
logMsg('   Size: ' . number_format(strlen($xmlOutput)) . ' bytes');
logMsg('   Channels: ' . count($matched));
logMsg('   Programmes: ' . count($filteredProgrammes));
logMsg('=== EPG Builder finished ===');

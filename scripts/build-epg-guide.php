<?php
/**
 * build-epg-guide.php
 *
 * Downloads multiple XMLTV EPG sources, filters channels based on the
 * epg_id values found in tv.json, and generates a merged epg/guide.xml.
 *
 * Usage: php scripts/build-epg-guide.php
 */

// ─────────────────────────────────────────────
// Config paths
// ─────────────────────────────────────────────
$rootDir     = __DIR__ . '/..';
$tvJsonPath  = $rootDir . '/tv.json';
$sourcesPath = $rootDir . '/epg/sources.txt';
$settingsPath= $rootDir . '/epg/settings.txt';
$outputPath  = $rootDir . '/epg/guide.xml';

// ─────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────
function logMsg(string $msg): void {
    echo '[' . gmdate('Y-m-d H:i:s') . '] ' . $msg . "\n";
}

/**
 * Downloads a URL and returns its content (decompressing .gz if needed).
 */
function downloadContent(string $url): ?string {
    logMsg("Downloading: $url");

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 60,
            'user_agent' => 'Mozilla/5.0 (compatible; EPG-Builder/1.0)',
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);

    $data = @file_get_contents($url, false, $ctx);
    if ($data === false) {
        logMsg("  ⚠️  Failed to download.");
        return null;
    }

    // If the URL ends with .gz, decompress
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

/**
 * Parses an XMLTV string and returns arrays of channels and programmes.
 */
function parseXmltv(string $xmlContent): array {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlContent);
    if ($xml === false) {
        foreach (libxml_get_errors() as $err) {
            logMsg('  ⚠️  XML error: ' . trim($err->message));
        }
        libxml_clear_errors();
        return ['channels' => [], 'programmes' => []];
    }

    $channels   = [];
    $programmes = [];

    // Register namespaces
    $namespaces = $xml->getNamespaces(true);
    $rootName   = $xml->getName();

    // Channels
    foreach ($xml->channel as $ch) {
        $id = (string)($ch['id'] ?? '');
        if ($id === '') continue;

        $displayNames = [];
        foreach ($ch->{'display-name'} as $dn) {
            $displayNames[] = trim((string)$dn);
        }
        $icon = '';
        foreach ($ch->icon as $ic) {
            $icon = (string)($ic['src'] ?? '');
            break;
        }
        $channels[$id] = [
            'id'            => $id,
            'display_names' => $displayNames,
            'icon'          => $icon,
        ];
    }

    // Programmes
    foreach ($xml->programme as $pr) {
        $channelId = (string)($pr['channel'] ?? '');
        if ($channelId === '') continue;

        $start = (string)($pr['start'] ?? '');
        $stop  = (string)($pr['stop']  ?? '');

        $title = '';
        foreach ($pr->title as $t) { $title = trim((string)$t); break; }

        $desc = '';
        foreach ($pr->desc as $d) { $desc = trim((string)$d); break; }

        $category = '';
        foreach ($pr->category as $c) { $category = trim((string)$c); break; }

        $programmes[] = [
            'channel'  => $channelId,
            'start'    => $start,
            'stop'     => $stop,
            'title'    => $title,
            'desc'     => $desc,
            'category' => $category,
        ];
    }

    logMsg('  ✓ Parsed: ' . count($channels) . ' channels, ' . count($programmes) . ' programmes.');
    return ['channels' => $channels, 'programmes' => $programmes];
}

/**
 * Reads settings.txt into an associative array (key=value).
 */
function readSettings(string $path): array {
    $settings = [];
    if (!is_file($path)) return $settings;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $settings[trim($k)] = trim($v);
    }
    return $settings;
}

/**
 * Converts a timestamp to XMLTV format: YYYYMMDDHHMMSS +0000
 */
function toXmltvTime(int $timestamp): string {
    return gmdate('YmdHis', $timestamp) . ' +0000';
}

// ─────────────────────────────────────────────
// Main
// ─────────────────────────────────────────────
logMsg('=== EPG Builder started ===');

// 1. Read settings
$settings = readSettings($settingsPath);
$daysPast   = (int)($settings['dias-pasados']  ?? 1);
$daysFuture = (int)($settings['dias-futuros'] ?? 7);
$nameSuffix = $settings['display-name-suffix'] ?? '';
logMsg("Settings: days-past=$daysPast, days-future=$daysFuture, suffix='$nameSuffix'");

// 2. Read tv.json to build the epg_id -> canonical name mapping
if (!is_file($tvJsonPath)) {
    die("Error: tv.json not found at $tvJsonPath\n");
}
$tvData = json_decode(file_get_contents($tvJsonPath), true);
if (!$tvData || !isset($tvData['countries'])) {
    die("Error: invalid tv.json\n");
}

$epgIdToName = [];  // epg_id => canonical name
$epgIdToLogo = [];  // epg_id => logo URL
foreach ($tvData['countries'] as $country) {
    foreach (($country['ambits'] ?? []) as $ambit) {
        foreach (($ambit['channels'] ?? []) as $channel) {
            $epgId = trim($channel['epg_id'] ?? '');
            if ($epgId === '') continue;

            // Only include channels that have at least one playable option
            $options = $channel['options'] ?? [];
            if (empty($options) || !is_array($options)) continue;

            $epgIdToName[$epgId] = $channel['name'] ?? $epgId;
            $epgIdToLogo[$epgId] = $channel['logo'] ?? '';
        }
    }
}
logMsg('Channels with epg_id and options in tv.json: ' . count($epgIdToName));

if (empty($epgIdToName)) {
    die("Error: no channels with epg_id and options found in tv.json.\n");
}

// 3. Read sources
if (!is_file($sourcesPath)) {
    die("Error: sources.txt not found at $sourcesPath\n");
}
$sources = [];
foreach (file($sourcesPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    $sources[] = $line;
}
logMsg('Sources: ' . count($sources));

// 4. Download and parse all sources
$allChannels   = [];  // id => channel data (first occurrence wins)
$allProgrammes = [];  // list of programmes

foreach ($sources as $srcUrl) {
    $content = downloadContent($srcUrl);
    if ($content === null) continue;

    $parsed = parseXmltv($content);

    foreach ($parsed['channels'] as $id => $ch) {
        if (!isset($allChannels[$id])) {
            $allChannels[$id] = $ch;
        }
    }
    foreach ($parsed['programmes'] as $pr) {
        $allProgrammes[] = $pr;
    }
}

logMsg('Total unique channels across sources: ' . count($allChannels));
logMsg('Total programmes across sources: ' . count($allProgrammes));

// 5. Match channels from tv.json to source channels
$matched = []; // epg_id => source channel data
foreach ($epgIdToName as $epgId => $canonicalName) {
    // Try exact match by ID first
    if (isset($allChannels[$epgId])) {
        $matched[$epgId] = $allChannels[$epgId];
        continue;
    }

    // Try case-insensitive match by ID
    foreach ($allChannels as $srcId => $ch) {
        if (strcasecmp($srcId, $epgId) === 0) {
            $matched[$epgId] = $ch;
            break;
        }
    }
    if (isset($matched[$epgId])) continue;

    // Try match by display-name (case-insensitive)
    $cleanName = strtolower(trim($epgId));
    foreach ($allChannels as $srcId => $ch) {
        foreach ($ch['display_names'] as $dn) {
            if (strtolower(trim($dn)) === $cleanName) {
                $matched[$epgId] = $ch;
                break 2;
            }
        }
    }
}

logMsg('Matched channels: ' . count($matched) . ' / ' . count($epgIdToName));

if (empty($matched)) {
    logMsg('⚠️  No channels matched. Generating guide with channels only (no programmes).');
}

// 6. Filter programmes by time window
$now = time();
$minTime = $now - ($daysPast * 86400);
$maxTime = $now + ($daysFuture * 86400);

$matchedIds = array_keys($matched);
$matchedIdsLower = array_map('strtolower', $matchedIds);

$filteredProgrammes = [];
foreach ($allProgrammes as $pr) {
    $chId = $pr['channel'];
    // Check if this programme belongs to one of our matched channels
    $isOurs = in_array($chId, $matchedIds, true);
    if (!$isOurs) {
        $isOurs = in_array(strtolower($chId), $matchedIdsLower, true);
    }
    if (!$isOurs) continue;

    // Parse start time to check the window
    $startTs = strtotime(substr($pr['start'], 0, 14));
    if ($startTs === false) continue;
    if ($startTs < $minTime || $startTs > $maxTime) continue;

    $filteredProgrammes[] = $pr;
}
logMsg('Programmes after time filter: ' . count($filteredProgrammes));

// 7. Build the output XMLTV
$out = [];
$out[] = '<?xml version="1.0" encoding="UTF-8"?>';
$out[] = '<!DOCTYPE tv SYSTEM "xmltv.dtd">';
$out[] = '<tv generator-info-name="build-epg-guide.php" generator-info-url="https://github.com/teleonline/listas">';

// Channels
foreach ($matched as $epgId => $srcCh) {
    $canonicalName = $epgIdToName[$epgId];
    $logo = $epgIdToLogo[$epgId] ?: ($srcCh['icon'] ?? '');
    $displayName = $canonicalName . ($nameSuffix !== '' ? ' ' . $nameSuffix : '');

    $out[] = '  <channel id="' . htmlspecialchars($epgId, ENT_XML1) . '">';
    $out[] = '    <display-name>' . htmlspecialchars($displayName, ENT_XML1) . '</display-name>';
    if ($logo !== '') {
        $out[] = '    <icon src="' . htmlspecialchars($logo, ENT_XML1) . '"/>';
    }
    $out[] = '  </channel>';
}

// Programmes
foreach ($filteredProgrammes as $pr) {
    $out[] = '  <programme start="' . htmlspecialchars($pr['start'], ENT_XML1) . '"'
           . ' stop="'  . htmlspecialchars($pr['stop'],  ENT_XML1) . '"'
           . ' channel="' . htmlspecialchars($pr['channel'], ENT_XML1) . '">';
    if ($pr['title'] !== '') {
        $out[] = '    <title lang="es">' . htmlspecialchars($pr['title'], ENT_XML1) . '</title>';
    }
    if ($pr['desc'] !== '') {
        $out[] = '    <desc lang="es">' . htmlspecialchars($pr['desc'], ENT_XML1) . '</desc>';
    }
    if ($pr['category'] !== '') {
        $out[] = '    <category lang="es">' . htmlspecialchars($pr['category'], ENT_XML1) . '</category>';
    }
    $out[] = '  </programme>';
}

$out[] = '</tv>';

$xmlOutput = implode("\n", $out);

// 8. Write output
$outDir = dirname($outputPath);
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

file_put_contents($outputPath, $xmlOutput);
logMsg('✅ Generated: ' . $outputPath);
logMsg('   Size: ' . number_format(strlen($xmlOutput)) . ' bytes');
logMsg('   Channels: ' . count($matched));
logMsg('   Programmes: ' . count($filteredProgrammes));
logMsg('=== EPG Builder finished ===');

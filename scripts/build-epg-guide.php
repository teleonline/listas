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
$rootDir      = __DIR__ . '/..';
$tvJsonPath   = $rootDir . '/tv.json';
$sourcesPath  = $rootDir . '/epg/sources.txt';
$settingsPath = $rootDir . '/epg/settings.txt';
$outputPath   = $rootDir . '/epg/guide.xml';

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
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ],
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
        foreach (libxml_get_errors() as $err) {
            logMsg('  ⚠️  XML error: ' . trim($err->message));
        }
        libxml_clear_errors();
        return ['channels' => [], 'programmes' => []];
    }

    $channels   = [];
    $programmes = [];

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
 * Normalizes a name for fuzzy comparison:
 * lowercase, remove non-alphanumeric, trim.
 */
function normalizeName(string $s): string {
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/u', '', $s);
    return $s;
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

// 2. Read tv.json -> build epg_id -> canonical name/logo
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
if (empty($epgIdToName)) die("Error: no channels with epg_id and options found in tv.json.\n");

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
$allChannels   = [];   // source_id => channel data (first occurrence wins)
$allProgrammes = [];   // flat list

foreach ($sources as $srcUrl) {
    $content = downloadContent($srcUrl);
    if ($content === null) continue;
    $parsed = parseXmltv($content);

    foreach ($parsed['channels'] as $id => $ch) {
        if (!isset($allChannels[$id])) $allChannels[$id] = $ch;
    }
    foreach ($parsed['programmes'] as $pr) {
        $allProgrammes[] = $pr;
    }
}
logMsg('Total unique channels across sources: ' . count($allChannels));
logMsg('Total programmes across sources: ' . count($allProgrammes));

// 5. Match channels from tv.json to source channels
// Strategy (in order): exact id, case-insensitive id, display-name exact, display-name normalized.
// We store the ORIGINAL source_id so we can filter programmes correctly.
$matched = [];  // epg_id => ['channel' => source channel data, 'source_id' => original id, 'method' => how]

foreach ($epgIdToName as $epgId => $canonicalName) {
    // a) exact id
    if (isset($allChannels[$epgId])) {
        $matched[$epgId] = ['channel' => $allChannels[$epgId], 'source_id' => $epgId, 'method' => 'id'];
        continue;
    }
    // b) case-insensitive id
    foreach ($allChannels as $srcId => $ch) {
        if (strcasecmp($srcId, $epgId) === 0) {
            $matched[$epgId] = ['channel' => $ch, 'source_id' => $srcId, 'method' => 'id-ci'];
            break;
        }
    }
    if (isset($matched[$epgId])) continue;

    // c) display-name exact (case-insensitive)
    $cleanName = strtolower(trim($canonicalName));
    foreach ($allChannels as $srcId => $ch) {
        foreach ($ch['display_names'] as $dn) {
            if (strtolower(trim($dn)) === $cleanName) {
                $matched[$epgId] = ['channel' => $ch, 'source_id' => $srcId, 'method' => 'name'];
                break 2;
            }
        }
    }
    if (isset($matched[$epgId])) continue;

    // d) normalized display-name (strip spaces, accents, punctuation)
    $normName = normalizeName($canonicalName);
    foreach ($allChannels as $srcId => $ch) {
        foreach ($ch['display_names'] as $dn) {
            if (normalizeName($dn) === $normName) {
                $matched[$epgId] = ['channel' => $ch, 'source_id' => $srcId, 'method' => 'name-norm'];
                break 2;
            }
        }
    }
}

// Report match stats
$methodCount = ['id' => 0, 'id-ci' => 0, 'name' => 0, 'name-norm' => 0];
foreach ($matched as $m) $methodCount[$m['method']]++;
logMsg('Matched channels: ' . count($matched) . ' / ' . count($epgIdToName));
logMsg("  by exact id:        {$methodCount['id']}");
logMsg("  by id (ci):         {$methodCount['id-ci']}");
logMsg("  by display-name:    {$methodCount['name']}");
logMsg("  by normalized name: {$methodCount['name-norm']}");

// 6. Build reverse map: source_id => epg_id (canonical)
$sourceIdToEpgId = [];
foreach ($matched as $epgId => $m) {
    $sourceIdToEpgId[$m['source_id']] = $epgId;
}

// 7. Filter programmes by time window AND by belonging to our channels
$now     = time();
$minTime = $now - ($daysPast * 86400);
$maxTime = $now + ($daysFuture * 86400);

$filteredProgrammes = [];
foreach ($allProgrammes as $pr) {
    $chId = $pr['channel'];

    // Only keep programmes whose source_id belongs to our matched channels
    if (!isset($sourceIdToEpgId[$chId])) continue;

    $startTs = strtotime(substr($pr['start'], 0, 14));
    if ($startTs === false) continue;
    if ($startTs < $minTime || $startTs > $maxTime) continue;

    // Rewrite channel to canonical epg_id
    $pr['channel'] = $sourceIdToEpgId[$chId];
    $filteredProgrammes[] = $pr;
}
logMsg('Programmes after filter: ' . count($filteredProgrammes));

// 8. Build the output XMLTV
$out = [];
$out[] = '<?xml version="1.0" encoding="UTF-8"?>';
$out[] = '<!DOCTYPE tv SYSTEM "xmltv.dtd">';
$out[] = '<tv generator-info-name="build-epg-guide.php" generator-info-url="https://github.com/teleonline/listas">';

foreach ($matched as $epgId => $m) {
    $canonicalName = $epgIdToName[$epgId];
    $logo = $epgIdToLogo[$epgId] ?: ($m['channel']['icon'] ?? '');
    $displayName = $canonicalName . ($nameSuffix !== '' ? ' ' . $nameSuffix : '');

    $out[] = '  <channel id="' . htmlspecialchars($epgId, ENT_XML1) . '">';
    $out[] = '    <display-name>' . htmlspecialchars($displayName, ENT_XML1) . '</display-name>';
    if ($logo !== '') {
        $out[] = '    <icon src="' . htmlspecialchars($logo, ENT_XML1) . '"/>';
    }
    $out[] = '  </channel>';
}

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

$outDir = dirname($outputPath);
if (!is_dir($outDir)) mkdir($outDir, 0755, true);
file_put_contents($outputPath, $xmlOutput);

logMsg('✅ Generated: ' . $outputPath);
logMsg('   Size: ' . number_format(strlen($xmlOutput)) . ' bytes');
logMsg('   Channels: ' . count($matched));
logMsg('   Programmes: ' . count($filteredProgrammes));
logMsg('=== EPG Builder finished ===');

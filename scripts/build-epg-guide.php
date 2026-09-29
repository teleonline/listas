<?php
/**
 * build-epg-guide.php
 *
 * Downloads multiple XMLTV EPG sources and generates a complete merged guide:
 *   - epg.xml.gz  (XMLTV format, gzip compressed)
 *   - epg.json.gz (JSON format, gzip compressed)
 *
 * Includes ALL channels from all sources (no filtering by tv.json).
 * Channels can be excluded via epg/exclusions.txt.
 *
 * Usage: php scripts/build-epg-guide.php
 */

ini_set('memory_limit', '4G');
set_time_limit(1800);

// ─────────────────────────────────────────────
// Config paths
// ─────────────────────────────────────────────
$rootDir         = __DIR__ . '/..';
$sourcesPath     = $rootDir . '/epg/sources.txt';
$settingsPath    = $rootDir . '/epg/settings.txt';
$exclusionsPath  = $rootDir . '/epg/exclusions.txt';
$xmlOutputPath   = $rootDir . '/epg.xml.gz';
$jsonOutputPath  = $rootDir . '/epg.json.gz';

// ─────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────
function logMsg(string $msg): void {
    echo '[' . gmdate('Y-m-d H:i:s') . '] ' . $msg . "\n";
}

function downloadContent(string $url): ?string {
    logMsg("Downloading: $url");
    $ctx = stream_context_create([
        'http' => ['timeout' => 300, 'user_agent' => 'Mozilla/5.0 (compatible; EPG-Builder/1.0)'],
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

function readPatterns(string $path): array {
    $out = [];
    if (!is_file($path)) return $out;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $out[] = strtolower($line);
    }
    return $out;
}

function isExcluded(string $id, array $patterns): bool {
    $idLower = strtolower($id);
    foreach ($patterns as $p) {
        if (strpos($idLower, $p) !== false) return true;
    }
    return false;
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

/**
 * Writes a string to a gzip file (maximum compression).
 */
function writeGzip(string $path, string $content): int {
    $fp = gzopen($path, 'wb9');
    if ($fp === false) return 0;
    gzwrite($fp, $content);
    gzclose($fp);
    return filesize($path) ?: 0;
}

/**
 * Parses XMLTV content using XMLReader (streaming, low memory).
 */
function parseXmltvStreaming(string $xmlContent, array &$channels, array &$programmes, int $minTime, int $maxTime): void {
    $reader = new XMLReader();
    if (!$reader->XML($xmlContent)) {
        logMsg("  ⚠️  XMLReader failed to load XML.");
        return;
    }

    $currentChannel = null;
    $currentProgramme = null;
    $chCount = 0;
    $prCount = 0;

    while ($reader->read()) {
        if ($reader->nodeType === XMLReader::ELEMENT) {
            if ($reader->name === 'channel') {
                $id = $reader->getAttribute('id');
                if ($id !== null) {
                    $currentChannel = ['id' => $id, 'names' => [], 'icon' => ''];
                }
            } elseif ($reader->name === 'display-name' && $currentChannel !== null) {
                $reader->read();
                if ($reader->nodeType === XMLReader::TEXT || $reader->nodeType === XMLReader::CDATA) {
                    $currentChannel['names'][] = trim($reader->value);
                }
            } elseif ($reader->name === 'icon' && $currentChannel !== null) {
                $src = $reader->getAttribute('src');
                if ($src !== null && $currentChannel['icon'] === '') {
                    $currentChannel['icon'] = $src;
                }
            } elseif ($reader->name === 'programme') {
                $start = $reader->getAttribute('start') ?? '';
                $stop  = $reader->getAttribute('stop')  ?? '';
                $ch    = $reader->getAttribute('channel') ?? '';
                if ($ch === '' || $start === '') continue;

                $startTs = strtotime(substr($start, 0, 14));
                if ($startTs === false || $startTs < $minTime || $startTs > $maxTime) {
                    $reader->next();
                    continue;
                }

                $currentProgramme = [
                    'channel'  => $ch,
                    'start'    => $start,
                    'stop'     => $stop,
                    'title'    => '',
                    'desc'     => '',
                    'category' => '',
                ];
            } elseif ($reader->name === 'title' && $currentProgramme !== null) {
                $reader->read();
                if ($reader->nodeType === XMLReader::TEXT || $reader->nodeType === XMLReader::CDATA) {
                    if ($currentProgramme['title'] === '') $currentProgramme['title'] = trim($reader->value);
                }
            } elseif ($reader->name === 'desc' && $currentProgramme !== null) {
                $reader->read();
                if ($reader->nodeType === XMLReader::TEXT || $reader->nodeType === XMLReader::CDATA) {
                    if ($currentProgramme['desc'] === '') $currentProgramme['desc'] = trim($reader->value);
                }
            } elseif ($reader->name === 'category' && $currentProgramme !== null) {
                $reader->read();
                if ($reader->nodeType === XMLReader::TEXT || $reader->nodeType === XMLReader::CDATA) {
                    if ($currentProgramme['category'] === '') $currentProgramme['category'] = trim($reader->value);
                }
            }
        } elseif ($reader->nodeType === XMLReader::END_ELEMENT) {
            if ($reader->name === 'channel' && $currentChannel !== null) {
                $id = $currentChannel['id'];
                if (!isset($channels[$id])) {
                    $channels[$id] = $currentChannel;
                    $chCount++;
                }
                $currentChannel = null;
            } elseif ($reader->name === 'programme' && $currentProgramme !== null) {
                $programmes[] = $currentProgramme;
                $prCount++;
                $currentProgramme = null;
            }
        }
    }
    $reader->close();
    logMsg("  ✓ Parsed: $chCount new channels, $prCount programmes in window.");
}

// ─────────────────────────────────────────────
// Main
// ─────────────────────────────────────────────
logMsg('=== EPG Builder started (FULL mode, gzip output) ===');

$settings   = readSettings($settingsPath);
$daysPast   = (int)($settings['dias-pasados']  ?? 1);
$daysFuture = (int)($settings['dias-futuros'] ?? 7);
logMsg("Settings: days-past=$daysPast, days-future=$daysFuture");

$exclusions = readPatterns($exclusionsPath);
logMsg('Exclusion patterns: ' . count($exclusions));

if (!is_file($sourcesPath)) die("Error: sources.txt not found at $sourcesPath\n");
$sources = [];
foreach (file($sourcesPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    $sources[] = $line;
}
logMsg('Sources: ' . count($sources));

$now     = time();
$minTime = $now - ($daysPast * 86400);
$maxTime = $now + ($daysFuture * 86400);

$allChannels   = [];
$allProgrammes = [];

foreach ($sources as $srcUrl) {
    $content = downloadContent($srcUrl);
    if ($content === null) continue;
    parseXmltvStreaming($content, $allChannels, $allProgrammes, $minTime, $maxTime);
    unset($content);
}

logMsg('Total unique channels: ' . count($allChannels));
logMsg('Total programmes: ' . count($allProgrammes));

// Apply exclusions
$excludedCount = 0;
foreach ($allChannels as $id => $ch) {
    if (isExcluded($id, $exclusions)) {
        unset($allChannels[$id]);
        $excludedCount++;
    }
}
logMsg("Channels excluded: $excludedCount");
logMsg('Channels after exclusion: ' . count($allChannels));

// Filter programmes
$finalProgrammes = [];
$orphanProgrammes = 0;
foreach ($allProgrammes as $pr) {
    if (!isset($allChannels[$pr['channel']])) {
        $orphanProgrammes++;
        continue;
    }
    $finalProgrammes[] = $pr;
}
logMsg('Programmes dropped (excluded channels): ' . $orphanProgrammes);
logMsg('Final programmes: ' . count($finalProgrammes));
unset($allProgrammes);

ksort($allChannels, SORT_NATURAL | SORT_FLAG_CASE);

// ─────────────────────────────────────────────
// XML output
// ─────────────────────────────────────────────
$xml = [];
$xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
$xml[] = '<!DOCTYPE tv SYSTEM "xmltv.dtd">';
$xml[] = '<tv generator-info-name="build-epg-guide.php" generator-info-url="https://github.com/teleonline/listas">';

foreach ($allChannels as $id => $ch) {
    $xml[] = '  <channel id="' . htmlspecialchars($id, ENT_XML1) . '">';
    foreach ($ch['names'] as $n) {
        if ($n !== '') $xml[] = '    <display-name>' . htmlspecialchars($n, ENT_XML1) . '</display-name>';
    }
    if ($ch['icon'] !== '') {
        $xml[] = '    <icon src="' . htmlspecialchars($ch['icon'], ENT_XML1) . '"/>';
    }
    $xml[] = '  </channel>';
}

foreach ($finalProgrammes as $pr) {
    $xml[] = '  <programme start="' . htmlspecialchars($pr['start'], ENT_XML1) . '"'
           . ' stop="'  . htmlspecialchars($pr['stop'],  ENT_XML1) . '"'
           . ' channel="' . htmlspecialchars($pr['channel'], ENT_XML1) . '">';
    if ($pr['title'] !== '')    $xml[] = '    <title>' . htmlspecialchars($pr['title'], ENT_XML1) . '</title>';
    if ($pr['desc'] !== '')     $xml[] = '    <desc>' . htmlspecialchars($pr['desc'], ENT_XML1) . '</desc>';
    if ($pr['category'] !== '') $xml[] = '    <category>' . htmlspecialchars($pr['category'], ENT_XML1) . '</category>';
    $xml[] = '  </programme>';
}
$xml[] = '</tv>';

$xmlOutput = implode("\n", $xml);
$xmlSize = strlen($xmlOutput);
unset($xml);

$xmlGzSize = writeGzip($xmlOutputPath, $xmlOutput);
unset($xmlOutput);
logMsg('✅ XML generated: ' . $xmlOutputPath);
logMsg('   Uncompressed: ' . number_format($xmlSize) . ' bytes');
logMsg('   Compressed:   ' . number_format($xmlGzSize) . ' bytes');

// ─────────────────────────────────────────────
// JSON output
// ─────────────────────────────────────────────
$programmesByChannel = [];
foreach ($finalProgrammes as $pr) {
    $programmesByChannel[$pr['channel']][] = [
        'start'    => xmltvTimeToIso($pr['start']),
        'stop'     => xmltvTimeToIso($pr['stop']),
        'title'    => sanitizeUtf8($pr['title']),
        'desc'     => sanitizeUtf8($pr['desc']),
        'category' => sanitizeUtf8($pr['category']),
    ];
}
unset($finalProgrammes);

$jsonChannels = [];
foreach ($allChannels as $id => $ch) {
    $jsonChannels[] = [
        'id'         => sanitizeUtf8($id),
        'name'       => sanitizeUtf8($ch['names'][0] ?? $id),
        'logo'       => sanitizeUtf8($ch['icon']),
        'programmes' => $programmesByChannel[$id] ?? [],
    ];
}
unset($programmesByChannel, $allChannels);

$jsonPayload = [
    'generator'        => 'build-epg-guide.php',
    'generator_url'    => 'https://github.com/teleonline/listas',
    'generated_at'     => gmdate('c'),
    'days_past'        => $daysPast,
    'days_future'      => $daysFuture,
    'channels_count'   => count($jsonChannels),
    'channels'         => $jsonChannels,
];

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
$jsonOutput = json_encode($jsonPayload, $jsonFlags);

if ($jsonOutput === false) {
    logMsg('⚠️  JSON encoding failed: ' . json_last_error_msg());
} else {
    $jsonSize = strlen($jsonOutput);
    $jsonGzSize = writeGzip($jsonOutputPath, $jsonOutput);
    unset($jsonOutput);
    logMsg('✅ JSON generated: ' . $jsonOutputPath);
    logMsg('   Uncompressed: ' . number_format($jsonSize) . ' bytes');
    logMsg('   Compressed:   ' . number_format($jsonGzSize) . ' bytes');
}

logMsg('=== EPG Builder finished ===');

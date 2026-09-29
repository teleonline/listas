<?php
/**
 * build-epg-guide.php
 *
 * Downloads multiple XMLTV EPG sources and generates a complete merged guide:
 *   - epg.xml      (uncompressed, for GitHub Release)
 *   - epg.json     (uncompressed, for GitHub Release)
 *   - epg.xml.gz   (compressed, committed to repo)
 *   - epg.json.gz  (compressed, committed to repo)
 *
 * Includes ALL channels from all sources (no filtering by tv.json).
 * Adds automatic display-name variants (HD, SD, .TV, base name).
 * Channels can be excluded via epg/exclusions.txt.
 * Every channel gets a country (attribute country="es" in epg.xml, "country" in epg.json):
 *   1) epg/countries.txt exceptions ("channel id = cc")
 *   2) explicit suffix (.es .fr .uk .de .it .pt .ar) or prefix ("DE | ...", "FR · ...") in the id
 *   3) default country of the source, taken from its header comment in epg/sources.txt
 *      ("# --- Espana ---") or from an explicit "es | https://..." line.
 * Programmes keep their image (<icon src="...">) in both epg.xml and epg.json.
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
$countriesPath   = $rootDir . '/epg/countries.txt';
$xmlPath         = $rootDir . '/epg.xml';
$jsonPath        = $rootDir . '/epg.json';
$xmlGzPath       = $rootDir . '/epg.xml.gz';
$jsonGzPath      = $rootDir . '/epg.json.gz';

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
        logMsg("  Failed to download.");
        return null;
    }
    if (substr($url, -3) === '.gz' || substr($data, 0, 2) === "\x1f\x8b") {
        $uncompressed = @gzdecode($data);
        if ($uncompressed === false) {
            logMsg("  Failed to decompress gzip.");
            return null;
        }
        $data = $uncompressed;
    }
    logMsg('  Downloaded ' . number_format(strlen($data)) . ' bytes.');
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

// ─────────────────────────────────────────────
// Countries
// ─────────────────────────────────────────────
const KNOWN_COUNTRIES = ['es', 'fr', 'uk', 'de', 'it', 'pt', 'ar'];

/** "España", "Reino Unido", "Latinoamérica"... -> country code ('' if unknown) */
function countryFromLabel(string $label): string {
    $l = strtolower(trim($label));
    $l = strtr($l, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    $map = [
        'espana' => 'es', 'spain' => 'es', 'es' => 'es',
        'francia' => 'fr', 'france' => 'fr', 'fr' => 'fr',
        'reino unido' => 'uk', 'united kingdom' => 'uk', 'uk' => 'uk', 'gb' => 'uk',
        'alemania' => 'de', 'germany' => 'de', 'de' => 'de',
        'italia' => 'it', 'italy' => 'it', 'it' => 'it',
        'portugal' => 'pt', 'pt' => 'pt',
        'latinoamerica' => 'ar', 'argentina' => 'ar', 'ar' => 'ar',
        'otros' => 'otros', 'other' => 'otros', 'otros paises' => 'otros',
    ];
    return $map[$l] ?? '';
}

/**
 * sources.txt: one URL per line. The country of a source is (in this order):
 *   - an explicit prefix:  es | https://...
 *   - the last header comment that names a country:  # ─── España ───
 * @return array<int, array{url:string, country:string}>
 */
function readSources(string $path): array {
    $out = [];
    $current = '';
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if ($line[0] === '#') {
            $label = trim(preg_replace('/[^\p{L}\s]+/u', ' ', $line));
            $label = preg_replace('/\s+/', ' ', $label);
            $c = countryFromLabel($label);
            if ($c !== '') $current = $c;
            continue;
        }
        $country = $current;
        if (preg_match('/^([A-Za-z]{2,12})\s*\|\s*(\S.*)$/', $line, $m) && countryFromLabel($m[1]) !== '') {
            $country = countryFromLabel($m[1]);
            $line = trim($m[2]);
        }
        $out[] = ['url' => $line, 'country' => $country];
    }
    return $out;
}

/** epg/countries.txt:  "channel id = cc"  (exact id, case-insensitive; # comments) */
function readCountryOverrides(string $path): array {
    $out = [];
    if (!is_file($path)) return $out;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $p = strrpos($line, '=');
        if ($p === false) continue;
        $id = strtolower(trim(substr($line, 0, $p)));
        $c  = countryFromLabel(substr($line, $p + 1));
        if ($id !== '' && $c !== '') $out[$id] = $c;
    }
    return $out;
}

/** Country of a channel id: explicit suffix / prefix first, otherwise the source default. */
function detectCountry(string $id, string $default): string {
    $p = strrpos($id, '.');
    if ($p !== false) {
        $suf = strtolower(substr($id, $p + 1));
        if (in_array($suf, KNOWN_COUNTRIES, true)) return $suf;
    }
    if (preg_match('/^(ES|FR|PT|UK|GB|DE|IT|AR)\s*[|·]\s*/u', $id, $m)) {
        $c = strtolower($m[1]);
        return $c === 'gb' ? 'uk' : $c;
    }
    return $default;
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
 * Escapes a string for XML, both for text nodes and for attribute values.
 *
 * IMPORTANT: htmlspecialchars($s, ENT_XML1) does NOT escape double quotes (ENT_XML1 only
 * selects the doctype; the quote style needs ENT_QUOTES). A URL containing a " inside
 * <icon src="..."> then produced invalid XML, and any strict XML parser stopped at that
 * line. ENT_QUOTES fixes it. Characters not allowed in XML 1.0 are also removed.
 */
function xe(string $s): string {
    $clean = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $s);
    if ($clean !== null) $s = $clean;
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
}

function writeGzip(string $path, string $content): int {
    $fp = gzopen($path, 'wb9');
    if ($fp === false) return 0;
    gzwrite($fp, $content);
    gzclose($fp);
    return filesize($path) ?: 0;
}

/**
 * Generates display-name variants for a channel.
 * Returns the list of display-names: original + variants, deduplicated.
 */
function buildDisplayNames(array $originalNames): array {
    $names = [];

    // 1. Original names (trimmed, non-empty)
    foreach ($originalNames as $n) {
        $n = trim($n);
        if ($n !== '') $names[] = $n;
    }

    // 2. Automatic variants from each original name
    foreach ($originalNames as $n) {
        $n = trim($n);
        if ($n === '') continue;

        // Generate base (strip suffixes)
        $base = preg_replace('/\s+(HD|SD|FHD|UHD|4K|720|1080)$/i', '', $n);
        $base = preg_replace('/\.TV$/i', '', $base);
        $base = trim($base);

        if ($base === '') continue;

        // Add variants based on base
        $names[] = $base;
        $names[] = $base . '.TV';
        $names[] = $base . ' HD';
        $names[] = $base . ' SD';
        $names[] = $base . ' FHD';
    }

    // 3. Deduplicate (case-insensitive), preserve order
    $out = [];
    $seen = [];
    foreach ($names as $n) {
        $n = trim($n);
        if ($n === '') continue;
        $key = strtolower($n);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $out[] = $n;
    }
    return $out;
}

/**
 * Parses XMLTV content using XMLReader (streaming, low memory).
 */
function parseXmltvStreaming(string $xmlContent, array &$channels, array &$programmes, int $minTime, int $maxTime, string $defaultCountry = ''): void {
    $reader = new XMLReader();
    if (!$reader->XML($xmlContent)) {
        logMsg("  XMLReader failed to load XML.");
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
                    $currentChannel = ['id' => $id, 'names' => [], 'icon' => '', 'country' => detectCountry($id, $defaultCountry)];
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
                    'icon'     => '',
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
            } elseif ($reader->name === 'icon' && $currentProgramme !== null) {
                // Imagen del programa: <icon src="https://..."/>
                $src = $reader->getAttribute('src');
                if ($src !== null && $currentProgramme['icon'] === '') {
                    $currentProgramme['icon'] = trim($src);
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
    logMsg("  Parsed: $chCount new channels, $prCount programmes in window.");
}

// ─────────────────────────────────────────────
// Main
// ─────────────────────────────────────────────
logMsg('=== EPG Builder started (universal mode with variants) ===');

$settings   = readSettings($settingsPath);
$daysPast   = (int)($settings['dias-pasados']  ?? 1);
$daysFuture = (int)($settings['dias-futuros'] ?? 7);
logMsg("Settings: days-past=$daysPast, days-future=$daysFuture");

$exclusions = readPatterns($exclusionsPath);
logMsg('Exclusion patterns: ' . count($exclusions));

if (!is_file($sourcesPath)) die("Error: sources.txt not found at $sourcesPath\n");
$sources = readSources($sourcesPath);
logMsg('Sources: ' . count($sources));

$now     = time();
$minTime = $now - ($daysPast * 86400);
$maxTime = $now + ($daysFuture * 86400);

$allChannels   = [];
$allProgrammes = [];

foreach ($sources as $src) {
    $content = downloadContent($src['url']);
    if ($content === null) continue;
    logMsg('  Source country: ' . ($src['country'] !== '' ? $src['country'] : '(none)'));
    parseXmltvStreaming($content, $allChannels, $allProgrammes, $minTime, $maxTime, $src['country']);
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

// Country exceptions (epg/countries.txt) and summary
$countryOverrides = readCountryOverrides($countriesPath);
$byCountry = [];
foreach ($allChannels as $id => &$ch) {
    $ov = $countryOverrides[strtolower($id)] ?? null;
    if ($ov !== null) $ch['country'] = $ov;
    $k = $ch['country'] !== '' ? $ch['country'] : '(none)';
    $byCountry[$k] = ($byCountry[$k] ?? 0) + 1;
}
unset($ch);
arsort($byCountry);
logMsg('Country exceptions loaded: ' . count($countryOverrides));
logMsg('Channels by country: ' . implode(', ', array_map(fn($k, $v) => "$k=$v", array_keys($byCountry), $byCountry)));

// Filter programmes
$finalProgrammes = [];
$orphanProgrammes = 0;
$withIcon = 0;
foreach ($allProgrammes as $pr) {
    if (!isset($allChannels[$pr['channel']])) {
        $orphanProgrammes++;
        continue;
    }
    if ($pr['icon'] !== '') $withIcon++;
    $finalProgrammes[] = $pr;
}
logMsg('Programmes dropped (excluded channels): ' . $orphanProgrammes);
logMsg('Final programmes: ' . count($finalProgrammes) . " ($withIcon with image)");
unset($allProgrammes);

ksort($allChannels, SORT_NATURAL | SORT_FLAG_CASE);

// ─────────────────────────────────────────────
// XML output
// ─────────────────────────────────────────────
$xml = [];
$xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
$xml[] = '<!DOCTYPE tv SYSTEM "xmltv.dtd">';
$xml[] = '<tv generator-info-name="build-epg-guide.php" generator-info-url="https://github.com/teleonline/listas">';

$totalDisplayNames = 0;
foreach ($allChannels as $id => $ch) {
    $displayNames = buildDisplayNames($ch['names']);
    $totalDisplayNames += count($displayNames);

    $xml[] = '  <channel id="' . xe($id) . '"' . ($ch['country'] !== '' ? ' country="' . xe($ch['country']) . '"' : '') . '>';
    foreach ($displayNames as $n) {
        $xml[] = '    <display-name>' . xe($n) . '</display-name>';
    }
    if ($ch['icon'] !== '') {
        $xml[] = '    <icon src="' . xe($ch['icon']) . '"/>';
    }
    $xml[] = '  </channel>';
}
logMsg("Total display-names generated: $totalDisplayNames");

foreach ($finalProgrammes as $pr) {
    $xml[] = '  <programme start="' . xe($pr['start']) . '"'
           . ' stop="'  . xe($pr['stop']) . '"'
           . ' channel="' . xe($pr['channel']) . '">';
    if ($pr['title'] !== '')    $xml[] = '    <title>' . xe($pr['title']) . '</title>';
    if ($pr['desc'] !== '')     $xml[] = '    <desc>' . xe($pr['desc']) . '</desc>';
    if ($pr['category'] !== '') $xml[] = '    <category>' . xe($pr['category']) . '</category>';
    if ($pr['icon'] !== '')     $xml[] = '    <icon src="' . xe($pr['icon']) . '"/>';
    $xml[] = '  </programme>';
}
$xml[] = '</tv>';

$xmlOutput = implode("\n", $xml);
$xmlSize = strlen($xmlOutput);
unset($xml);

file_put_contents($xmlPath, $xmlOutput);
logMsg('XML raw written: ' . $xmlPath . ' (' . number_format($xmlSize) . ' bytes)');

$xmlGzSize = writeGzip($xmlGzPath, $xmlOutput);
unset($xmlOutput);
logMsg('XML gz written: ' . $xmlGzPath . ' (' . number_format($xmlGzSize) . ' bytes)');

// ─────────────────────────────────────────────
// JSON output
// ─────────────────────────────────────────────
$programmesByChannel = [];
foreach ($finalProgrammes as $pr) {
    $item = [
        'start'    => xmltvTimeToIso($pr['start']),
        'stop'     => xmltvTimeToIso($pr['stop']),
        'title'    => sanitizeUtf8($pr['title']),
        'desc'     => sanitizeUtf8($pr['desc']),
        'category' => sanitizeUtf8($pr['category']),
    ];
    // Imagen del programa (solo si existe, para no engordar el JSON)
    if ($pr['icon'] !== '') $item['icon'] = sanitizeUtf8($pr['icon']);
    $programmesByChannel[$pr['channel']][] = $item;
}
unset($finalProgrammes);

$jsonChannels = [];
foreach ($allChannels as $id => $ch) {
    $displayNames = buildDisplayNames($ch['names']);
    $jsonChannels[] = [
        'id'            => sanitizeUtf8($id),
        'name'          => sanitizeUtf8($displayNames[0] ?? $id),
        'display_names' => array_map('sanitizeUtf8', $displayNames),
        'logo'          => sanitizeUtf8($ch['icon']),
        'country'       => $ch['country'],
        'programmes'    => $programmesByChannel[$id] ?? [],
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
    logMsg('JSON encoding failed: ' . json_last_error_msg());
} else {
    $jsonSize = strlen($jsonOutput);

    file_put_contents($jsonPath, $jsonOutput);
    logMsg('JSON raw written: ' . $jsonPath . ' (' . number_format($jsonSize) . ' bytes)');

    $jsonGzSize = writeGzip($jsonGzPath, $jsonOutput);
    unset($jsonOutput);
    logMsg('JSON gz written: ' . $jsonGzPath . ' (' . number_format($jsonGzSize) . ' bytes)');
}

logMsg('=== EPG Builder finished ===');

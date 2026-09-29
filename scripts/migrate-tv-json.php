<?php
/**
 * migrate-tv-json.php
 *
 * Updates tv.json epg_id values to match the real IDs in epg.xml.
 * Match strategy: by current epg_id (normalized), then by name (normalized).
 * Only migrates when the match is unique (no ambiguity).
 *
 * Usage: php scripts/migrate-tv-json.php
 *
 * Outputs:
 *   - tv.json          (updated in place)
 *   - migration-report.txt
 */

ini_set('memory_limit', '2G');
set_time_limit(600);

$rootDir    = __DIR__ . '/..';
$tvJsonPath = $rootDir . '/tv.json';
$epgPath    = $rootDir . '/epg.xml';
$reportPath = $rootDir . '/migration-report.txt';

function logMsg(string $msg): void { echo $msg . "\n"; }

function normalizeName(string $s): string {
    $s = trim($s);
    if (function_exists('mb_strtolower')) $s = mb_strtolower($s, 'UTF-8');
    else $s = strtolower($s);
    $s = strtr($s, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
        'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u',
        'ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ü'=>'u',
        'â'=>'a','ê'=>'e','î'=>'i','ô'=>'o','û'=>'u','ç'=>'c',
    ]);
    $s = preg_replace('/\b(HD|SD|FHD|UHD|4K|720|1080|TV)\b/i', '', $s);
    $s = preg_replace('/[^a-z0-9]+/', '', $s);
    return $s;
}

// ─────────────────────────────────────────────
// 1. Load EPG channels (streaming)
// ─────────────────────────────────────────────
if (!is_file($epgPath)) die("Error: epg.xml not found at $epgPath\n");
logMsg('Loading EPG channels...');

$epgIndex = []; // normalized key => list of channel ids

$reader = new XMLReader();
$reader->open($epgPath);

$current = null;
while ($reader->read()) {
    if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'channel') {
        $id = $reader->getAttribute('id');
        $current = ['id' => $id, 'names' => []];
    } elseif ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'display-name' && $current !== null) {
        $reader->read();
        if ($reader->nodeType === XMLReader::TEXT || $reader->nodeType === XMLReader::CDATA) {
            $current['names'][] = trim($reader->value);
        }
    } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'channel' && $current !== null) {
        $keys = [];
        foreach ($current['names'] as $n) {
            $k = normalizeName($n);
            if ($k !== '') $keys[$k] = true;
        }
        $k = normalizeName($current['id']);
        if ($k !== '') $keys[$k] = true;

        foreach (array_keys($keys) as $k) {
            if (!isset($epgIndex[$k])) $epgIndex[$k] = [];
            if (!in_array($current['id'], $epgIndex[$k], true)) {
                $epgIndex[$k][] = $current['id'];
            }
        }
        $current = null;
    }
}
$reader->close();

logMsg('EPG index: ' . count($epgIndex) . ' keys');

// ─────────────────────────────────────────────
// 2. Load tv.json
// ─────────────────────────────────────────────
$tvData = json_decode(file_get_contents($tvJsonPath), true);
if (!$tvData || !isset($tvData['countries'])) die("Error: invalid tv.json\n");

// ─────────────────────────────────────────────
// 3. Migrate
// ─────────────────────────────────────────────
$changes   = [];
$ambiguous = [];
$notFound  = [];
$unchanged = 0;

foreach ($tvData['countries'] as $ci => &$country) {
    foreach (($country['ambits'] ?? []) as $ai => &$ambit) {
        foreach (($ambit['channels'] ?? []) as $chi => &$channel) {
            $name  = trim($channel['name'] ?? '');
            $oldId = trim($channel['epg_id'] ?? '');
            if ($name === '' || $oldId === '') continue;

            // Match ONLY by epg_id
            $candidates = null;
            $k = normalizeName($oldId);
            if ($k !== '' && isset($epgIndex[$k])) $candidates = $epgIndex[$k];

            if ($candidates === null) {
                $notFound[] = ['name' => $name, 'old' => $oldId];
                continue;
            }

            if (count($candidates) === 1) {
                $newId = $candidates[0];
                if ($newId === $oldId) { $unchanged++; }
                else {
                    $channel['epg_id'] = $newId;
                    $changes[] = ['name' => $name, 'old' => $oldId, 'new' => $newId];
                }
            } else {
                $ambiguous[] = ['name' => $name, 'old' => $oldId, 'candidates' => $candidates];
            }
        }
    }
}
unset($country, $ambit, $channel);

// ─────────────────────────────────────────────
// 4. Write tv.json
// ─────────────────────────────────────────────
$json = json_encode($tvData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
if ($json === false) die("Error encoding JSON: " . json_last_error_msg() . "\n");
file_put_contents($tvJsonPath, $json);
logMsg('tv.json updated: ' . $tvJsonPath);

// ─────────────────────────────────────────────
// 5. Report
// ─────────────────────────────────────────────
$r = [];
$r[] = '=== TV.JSON MIGRATION REPORT ===';
$r[] = 'Generated: ' . gmdate('Y-m-d H:i:s') . ' UTC';
$r[] = '';
$r[] = 'SUMMARY:';
$r[] = '  Migrated:   ' . count($changes);
$r[] = '  Unchanged:  ' . $unchanged;
$r[] = '  Ambiguous:  ' . count($ambiguous);
$r[] = '  Not found:  ' . count($notFound);
$r[] = '';

if ($changes) {
    $r[] = '--- MIGRATED ---';
    foreach ($changes as $c) $r[] = sprintf('  %-45s  %-30s -> %s', $c['name'], $c['old'], $c['new']);
    $r[] = '';
}
if ($ambiguous) {
    $r[] = '--- AMBIGUOUS (not changed) ---';
    foreach ($ambiguous as $a) {
        $r[] = sprintf('  %-45s  %s', $a['name'], $a['old']);
        $r[] = '      Candidates: ' . implode(', ', $a['candidates']);
    }
    $r[] = '';
}
if ($notFound) {
    $r[] = '--- NOT FOUND (not changed) ---';
    foreach ($notFound as $n) $r[] = sprintf('  %-45s  %s', $n['name'], $n['old']);
    $r[] = '';
}
file_put_contents($reportPath, implode("\n", $r));

logMsg('');
logMsg('=== SUMMARY ===');
logMsg('  Migrated:   ' . count($changes));
logMsg('  Unchanged:  ' . $unchanged);
logMsg('  Ambiguous:  ' . count($ambiguous));
logMsg('  Not found:  ' . count($notFound));
logMsg('');
logMsg('Report: ' . $reportPath);

<?php
/**
 * migrate-tv-json.php
 *
 * Migrates tv.json epg_id values to match the real EPG channel IDs.
 * Run this ONCE to align tv.json with the generated EPG.
 *
 * Usage: php scripts/migrate-tv-json.php
 *
 * Outputs:
 *   - tv.json.new       : the migrated tv.json (review before replacing)
 *   - migration-report.txt : detailed report of changes
 */

ini_set('memory_limit', '2G');
set_time_limit(600);

$rootDir      = __DIR__ . '/..';
$tvJsonPath   = $rootDir . '/tv.json';
$epgPath      = $rootDir . '/epg.xml';
$outputPath   = $rootDir . '/tv.json.new';
$reportPath   = $rootDir . '/migration-report.txt';

function logMsg(string $msg): void {
    echo $msg . "\n";
}

function normalizeName(string $s): string {
    $s = trim($s);
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
    $s = preg_replace('/\b(HD|SD|FHD|UHD|4K|720|1080|TV)\b/i', '', $s);
    $s = preg_replace('/[^a-z0-9]+/', '', $s);
    return $s;
}

// ─────────────────────────────────────────────
// 1. Load EPG channels
// ─────────────────────────────────────────────
if (!is_file($epgPath)) {
    die("Error: epg.xml not found at $epgPath. Run build-epg-guide.php first.\n");
}

logMsg('Loading EPG channels...');
$epgChannels = []; // normalized display-name => list of channel ids

$reader = new XMLReader();
$reader->open($epgPath);

$currentChannel = null;
while ($reader->read()) {
    if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'channel') {
        $id = $reader->getAttribute('id');
        $currentChannel = ['id' => $id, 'names' => []];
    } elseif ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'display-name' && $currentChannel !== null) {
        $reader->read();
        if ($reader->nodeType === XMLReader::TEXT || $reader->nodeType === XMLReader::CDATA) {
            $currentChannel['names'][] = trim($reader->value);
        }
    } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'channel' && $currentChannel !== null) {
        $id = $currentChannel['id'];
        foreach ($currentChannel['names'] as $n) {
            $norm = normalizeName($n);
            if ($norm === '') continue;
            if (!isset($epgChannels[$norm])) $epgChannels[$norm] = [];
            if (!in_array($id, $epgChannels[$norm], true)) {
                $epgChannels[$norm][] = $id;
            }
        }
        // Also index by the ID itself
        $normId = normalizeName($id);
        if ($normId !== '') {
            if (!isset($epgChannels[$normId])) $epgChannels[$normId] = [];
            if (!in_array($id, $epgChannels[$normId], true)) {
                $epgChannels[$normId][] = $id;
            }
        }
        $currentChannel = null;
    }
}
$reader->close();

logMsg('EPG channels indexed: ' . count($epgChannels) . ' keys');

// ─────────────────────────────────────────────
// 2. Load tv.json
// ─────────────────────────────────────────────
if (!is_file($tvJsonPath)) die("Error: tv.json not found at $tvJsonPath\n");

$tvData = json_decode(file_get_contents($tvJsonPath), true);
if (!$tvData || !isset($tvData['countries'])) die("Error: invalid tv.json\n");

// ─────────────────────────────────────────────
// 3. Migrate
// ─────────────────────────────────────────────
$changes    = [];  // ['name' => ..., 'old' => ..., 'new' => ...]
$ambiguous  = [];  // ['name' => ..., 'old' => ..., 'candidates' => [...]]
$notFound   = [];  // ['name' => ..., 'old' => ...]
$unchanged  = 0;

foreach ($tvData['countries'] as $ci => $country) {
    foreach (($country['ambits'] ?? []) as $ai => $ambit) {
        foreach (($ambit['channels'] ?? []) as $chi => $channel) {
            $name   = trim($channel['name'] ?? '');
            $oldId  = trim($channel['epg_id'] ?? '');
            if ($name === '' || $oldId === '') continue;

            // Try to find the channel in EPG by normalized name
            $norm = normalizeName($name);
            if ($norm === '' || !isset($epgChannels[$norm])) {
                $notFound[] = ['name' => $name, 'old' => $oldId];
                continue;
            }

            $candidates = $epgChannels[$norm];

            if (count($candidates) === 1) {
                $newId = $candidates[0];
                if ($newId === $oldId) {
                    $unchanged++;
                } else {
                    $tvData['countries'][$ci]['ambits'][$ai]['channels'][$chi]['epg_id'] = $newId;
                    $changes[] = ['name' => $name, 'old' => $oldId, 'new' => $newId];
                }
            } else {
                // Ambiguous: more than one EPG channel matches
                $ambiguous[] = ['name' => $name, 'old' => $oldId, 'candidates' => $candidates];
            }
        }
    }
}

// ─────────────────────────────────────────────
// 4. Write migrated tv.json
// ─────────────────────────────────────────────
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
$output = json_encode($tvData, $jsonFlags);

if ($output === false) {
    die("Error encoding JSON: " . json_last_error_msg() . "\n");
}

file_put_contents($outputPath, $output);
logMsg('Migrated tv.json written: ' . $outputPath);

// ─────────────────────────────────────────────
// 5. Write report
// ─────────────────────────────────────────────
$report = [];
$report[] = '=== TV.JSON MIGRATION REPORT ===';
$report[] = 'Generated: ' . gmdate('Y-m-d H:i:s') . ' UTC';
$report[] = '';
$report[] = 'SUMMARY:';
$report[] = '  Migrated (epg_id changed): ' . count($changes);
$report[] = '  Unchanged (already matched): ' . $unchanged;
$report[] = '  Ambiguous (multiple EPG candidates): ' . count($ambiguous);
$report[] = '  Not found in EPG: ' . count($notFound);
$report[] = '';

if (!empty($changes)) {
    $report[] = '--- MIGRATED CHANNELS ---';
    $report[] = '';
    foreach ($changes as $c) {
        $report[] = sprintf('  %-40s  %-30s -> %s', $c['name'], $c['old'], $c['new']);
    }
    $report[] = '';
}

if (!empty($ambiguous)) {
    $report[] = '--- AMBIGUOUS (not changed, review manually) ---';
    $report[] = '';
    foreach ($ambiguous as $a) {
        $report[] = sprintf('  %-40s  %s', $a['name'], $a['old']);
        $report[] = '      Candidates: ' . implode(', ', $a['candidates']);
    }
    $report[] = '';
}

if (!empty($notFound)) {
    $report[] = '--- NOT FOUND IN EPG (not changed) ---';
    $report[] = '';
    foreach ($notFound as $n) {
        $report[] = sprintf('  %-40s  %s', $n['name'], $n['old']);
    }
    $report[] = '';
}

file_put_contents($reportPath, implode("\n", $report));
logMsg('Report written: ' . $reportPath);

// ─────────────────────────────────────────────
// 6. Console summary
// ─────────────────────────────────────────────
logMsg('');
logMsg('=== SUMMARY ===');
logMsg('  Migrated:   ' . count($changes));
logMsg('  Unchanged:  ' . $unchanged);
logMsg('  Ambiguous:  ' . count($ambiguous));
logMsg('  Not found:  ' . count($notFound));
logMsg('');
logMsg('Review ' . $reportPath . ' and then:');
logMsg('  mv tv.json.new tv.json');

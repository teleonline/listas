<?php
/**
 * build-channels-list.php
 *
 * Downloads the channels JSON, filters those with at least one playable
 * option, and generates varios/canales.txt grouped by category.
 *
 * Usage: php scripts/build-channels-list.php
 */

$jsonUrl = 'https://raw.githubusercontent.com/teleonline/listas/main/tv.json';

$json = file_get_contents($jsonUrl);
if ($json === false) {
    die("Error: could not download JSON from $jsonUrl\n");
}

$data = json_decode($json, true);
if ($data === null) {
    die("Error: invalid JSON. " . json_last_error_msg() . "\n");
}

if (!isset($data['countries']) || !is_array($data['countries'])) {
    die("Error: 'countries' key not found in JSON.\n");
}

/**
 * Removes emojis, flags and Unicode symbols from a string.
 */
function stripEmojis(string $text): string {
    $text = preg_replace(
        '/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}\x{FE00}-\x{FE0F}\x{1F1E6}-\x{1F1FF}\x{200D}]/u',
        '',
        $text
    );
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

/**
 * Classifies a "stream" URL to determine if it's a real player.
 * Returns: 'youtube', 'twitch', 'dailymotion', 'otro' or false (plain website).
 */
function classifyStream(string $url): string|false {
    $u = strtolower(trim($url));
    if ($u === '') return false;

    if (strpos($u, 'youtube.com') !== false || strpos($u, 'youtu.be') !== false) return 'youtube';
    if (strpos($u, 'twitch.tv') !== false) return 'twitch';
    if (strpos($u, 'dailymotion.com') !== false) return 'dailymotion';

    $patterns = [
        'player.viloud.tv',
        'viloud.tv/embed',
        'watchity.com',
        'castr.com',
        'ipcamlive.com',
        'player.',
        '/embed',
        '/player',
        'livestream',
        'stream',
        '.m3u8',
        '.mpd',
    ];
    foreach ($patterns as $p) {
        if (strpos($u, $p) !== false) return 'otro';
    }

    return false;
}

$byCategory = [];
$total = 0;

foreach ($data['countries'] as $country) {
    $countryName = stripEmojis($country['name'] ?? 'No category');

    if (empty($country['ambits']) || !is_array($country['ambits'])) {
        continue;
    }

    foreach ($country['ambits'] as $ambit) {
        $ambitName = stripEmojis(trim($ambit['name'] ?? ''));
        $category  = $ambitName !== '' ? $ambitName : $countryName;

        if (empty($ambit['channels']) || !is_array($ambit['channels'])) {
            continue;
        }

        foreach ($ambit['channels'] as $channel) {
            $options = $channel['options'] ?? [];
            if (empty($options) || !is_array($options)) {
                continue;
            }

            $name = $channel['name'] ?? null;
            if (!$name) {
                continue;
            }

            $hasYoutube = false;
            $hasTwitch  = false;
            $isPlayable = false;

            foreach ($options as $opt) {
                if (!is_array($opt)) continue;

                $type = strtolower(trim($opt['format'] ?? ''));
                $url  = trim($opt['url'] ?? '');

                if ($type === '') continue;

                if ($type === 'm3u8') {
                    $isPlayable = true;
                } elseif ($type === 'youtube') {
                    $isPlayable = true;
                    $hasYoutube = true;
                } elseif ($type === 'stream') {
                    $kind = classifyStream($url);
                    if ($kind !== false) {
                        $isPlayable = true;
                        if ($kind === 'youtube') $hasYoutube = true;
                        if ($kind === 'twitch')  $hasTwitch  = true;
                    }
                }
            }

            if (!$isPlayable) continue;

            $suffix = '';
            if ($hasYoutube && $hasTwitch) {
                $suffix = ' (Canal Youtube/Twitch)';
            } elseif ($hasYoutube) {
                $suffix = ' (Canal Youtube)';
            } elseif ($hasTwitch) {
                $suffix = ' (Canal Twitch)';
            }

            if (!isset($byCategory[$category])) {
                $byCategory[$category] = [];
            }

            $channelLine = $name . $suffix;
            if (!in_array($channelLine, $byCategory[$category], true)) {
                $byCategory[$category][] = $channelLine;
                $total++;
            }
        }
    }
}

// Sort categories and channels alphabetically
ksort($byCategory, SORT_NATURAL | SORT_FLAG_CASE);
foreach ($byCategory as &$c) {
    sort($c, SORT_NATURAL | SORT_FLAG_CASE);
}
unset($c);

// Build final content
$lines = [];
foreach ($byCategory as $category => $channels) {
    $lines[] = $category;
    foreach ($channels as $channel) {
        $lines[] = "- $channel";
    }
    $lines[] = '';
}

// Output path: ../varios/canales.txt (one level above scripts/)
$outputPath = __DIR__ . '/../varios/canales.txt';

$outputDir = dirname($outputPath);
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

file_put_contents($outputPath, implode(PHP_EOL, $lines));

echo "✅ Generated $outputPath with $total channels in " . count($byCategory) . " categories.\n";

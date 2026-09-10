<?php

/**
 * Rebuilds srd-5.2.1-creatures.json from the SRD 5.2.1 creature markdown.
 *
 * The markdown is not committed here; database/srd/README.md says where to fetch it
 * and which revision this dataset was built from. Run it with the directory holding
 * monsters-A-Z.md and animals.md:
 *
 *     php database/srd/build-dataset.php /path/to/srd/markdown
 *
 * The JSON lands beside the markdown. Copy it here and update the checksum in
 * config/compendium.php in the same commit, or CompendiumDatasetTest fails.
 */
$dir = rtrim($argv[1] ?? __DIR__, '/');
// The two source files hang their headings at different levels: a creature in
// animals.md is an h2 with h3 sections, and one in monsters-A-Z.md is an h3 under a
// group h2, with h4 sections.
$files = [
    'monsters-A-Z.md' => ['creature' => '###', 'section' => '####'],
    'animals.md' => ['creature' => '##', 'section' => '###'],
];

const SECTIONS = ['Traits', 'Actions', 'Bonus Actions', 'Reactions', 'Legendary Actions'];

function clean(string $text): string
{
    $text = str_replace(["\u{2212}", '&emsp;'], ['-', ''], $text);

    return trim(preg_replace('/[ \t]+/', ' ', $text));
}

function paragraphText(string $paragraph): string
{
    $segments = [];
    $current = [];

    foreach (explode("\n", $paragraph) as $line) {
        $isContinuation = str_starts_with(ltrim($line), '&emsp;');
        $line = clean(str_replace('<br>', '', $line));

        if ($line === '') {
            continue;
        }

        if ($isContinuation && $current !== []) {
            $segments[] = implode(' ', $current);
            $current = [];
        }

        $current[] = $line;
    }

    if ($current !== []) {
        $segments[] = implode(' ', $current);
    }

    return implode("\n\n", $segments);
}

/** @return list<array{name: string|null, text: string}> */
function parseSection(string $body): array
{
    $body = preg_replace('/^\s*<hr>\s*$/m', '', $body);
    $entries = [];

    foreach (preg_split('/\n\s*\n/', $body) as $paragraph) {
        $text = paragraphText($paragraph);

        if ($text === '') {
            continue;
        }

        // A creature's last section runs to the next group's heading, so that heading
        // lands in this body. It belongs to the next creature and everything after it
        // does too, so the section stops here rather than skipping one paragraph.
        // Without this, 176 creatures ended a section with an entry reading "## Oni".
        if (preg_match('/^#{1,6}\s/', $text) === 1) {
            break;
        }

        if (preg_match('/^\*\*_(.+?)\.?_\*\*\s*(.*)$/s', $text, $m) === 1) {
            $entries[] = ['name' => trim($m[1]), 'text' => trim($m[2])];

            continue;
        }

        $entries[] = ['name' => null, 'text' => $text];
    }

    return $entries;
}

function field(string $chunk, string $label): ?string
{
    if (preg_match('/^\*\*'.preg_quote($label, '/').'\*\*\s*(.+?)\s*(?:<br>)?\s*$/m', $chunk, $m) !== 1) {
        return null;
    }

    $value = clean($m[1]);

    return $value === '' ? null : $value;
}

function slugify(string $name): string
{
    $slug = strtolower(str_replace(["'", '’'], '', $name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

    return trim($slug, '-');
}

$creatures = [];
$seen = [];

foreach ($files as $file => $levels) {
    $raw = file_get_contents($dir.'/'.$file);
    $creaturePattern = '/^'.$levels['creature'].' (?!#)(.+)$/m';
    $sectionPattern = '/^'.$levels['section'].' (?!#)('.implode('|', SECTIONS).')$/m';
    $parts = preg_split($creaturePattern, $raw, -1, PREG_SPLIT_DELIM_CAPTURE);

    for ($i = 1; $i < count($parts); $i += 2) {
        $name = clean($parts[$i]);
        $chunk = $parts[$i + 1];

        if (! str_contains($chunk, '**AC**') || ! str_contains($chunk, '**CR**')) {
            continue;
        }

        $slug = slugify($name);

        if (isset($seen[$slug])) {
            fwrite(STDERR, "duplicate slug skipped: {$slug}\n");

            continue;
        }

        $seen[$slug] = true;

        // _Large Aberration (Shapechanger), Lawful Evil_
        $size = $creatureType = $subtype = $alignment = $typeLine = null;
        $isSwarm = false;

        if (preg_match('/^_([^_]+)_$/m', $chunk, $m) === 1) {
            $meta = clean($m[1]);
            $comma = strrpos($meta, ',');
            $shape = $comma === false ? $meta : substr($meta, 0, $comma);
            $alignment = $comma === false ? null : trim(substr($meta, $comma + 1));

            if (preg_match('/\(([^)]+)\)/', $shape, $sub) === 1) {
                $subtype = trim($sub[1]);
                $shape = trim(str_replace($sub[0], '', $shape));
            }

            $typeLine = $meta;
            $words = preg_split('/\s+/', trim($shape));
            $creatureType = array_pop($words);
            $size = implode(' ', $words) ?: null;

            // "Large Swarm of Tiny Beasts" is one Large swarm made of Beasts. Filtering
            // wants the size and the creature; the page prints the line as the book does.
            if (str_contains($shape, 'Swarm of')) {
                $isSwarm = true;
                $size = $words[0] ?? null;
                $creatureType = rtrim($creatureType, 's');
            }
        }

        preg_match('/\*\*AC\*\*\s*(\d+)/', $chunk, $ac);
        preg_match('/\*\*Initiative\*\*\s*([+\-\x{2212}]?\d+)/u', $chunk, $init);
        preg_match('/\*\*HP\*\*\s*([\d,]+)\s*(?:\(([^)]*)\))?/', $chunk, $hp);
        preg_match('/\*\*CR\*\*\s*(\S+)\s*\((?:XP\s*([\d,]+)|([\d,]+)\s*XP)([^)]*)\)/', $chunk, $cr);
        $crXp = ($cr[2] ?? '') !== '' ? $cr[2] : ($cr[3] ?? '');
        $crNote = $cr[4] ?? '';

        $abilities = [];

        foreach (['str', 'dex', 'con', 'int', 'wis', 'cha'] as $ability) {
            $pattern = '/<strong>'.strtoupper($ability).'<\/strong><\/td>\s*<td>(\d+)<\/td>\s*<td>([^<]*)<\/td>\s*<td>([^<]*)<\/td>/';

            if (preg_match($pattern, $chunk, $m) === 1) {
                $abilities[$ability] = [
                    'score' => (int) $m[1],
                    'mod' => clean($m[2]),
                    'save' => clean($m[3]),
                ];
            }
        }

        $sections = [];
        $split = preg_split($sectionPattern, $chunk, -1, PREG_SPLIT_DELIM_CAPTURE);

        for ($s = 1; $s < count($split); $s += 2) {
            $key = strtolower(str_replace(' ', '_', clean($split[$s])));
            $sections[$key] = parseSection($split[$s + 1]);
        }

        $crToken = $cr[1] ?? null;
        $crValue = null;

        if ($crToken !== null) {
            $crValue = str_contains($crToken, '/')
                ? (float) explode('/', $crToken)[0] / (float) explode('/', $crToken)[1]
                : (float) $crToken;
        }

        $creatures[] = [
            'slug' => $slug,
            'name' => $name,
            'type_line' => $typeLine,
            'is_swarm' => $isSwarm,
            'size' => $size,
            'creature_type' => $creatureType,
            'subtype' => $subtype,
            'alignment' => $alignment,
            'ac' => isset($ac[1]) ? (int) $ac[1] : null,
            'initiative_bonus' => isset($init[1]) ? (int) str_replace("\u{2212}", '-', $init[1]) : null,
            'hp' => isset($hp[1]) ? (int) str_replace(',', '', $hp[1]) : null,
            'hit_dice' => isset($hp[2]) && $hp[2] !== '' ? clean($hp[2]) : null,
            'speed' => field($chunk, 'Speed'),
            'ability_scores' => $abilities === [] ? null : $abilities,
            'skills' => field($chunk, 'Skills'),
            'senses' => field($chunk, 'Senses'),
            'languages' => field($chunk, 'Languages'),
            'gear' => field($chunk, 'Gear'),
            'resistances' => field($chunk, 'Resistances'),
            'immunities' => field($chunk, 'Immunities'),
            'vulnerabilities' => field($chunk, 'Vulnerabilities'),
            'cr' => $crToken,
            'cr_value' => $crValue,
            'xp' => $crXp !== '' ? (int) str_replace(',', '', $crXp) : null,
            'cr_note' => trim($crNote, ' ;,') !== '' ? clean(trim($crNote, ' ;,')) : null,
            'traits' => $sections['traits'] ?? null,
            'actions' => $sections['actions'] ?? null,
            'bonus_actions' => $sections['bonus_actions'] ?? null,
            'reactions' => $sections['reactions'] ?? null,
            'legendary_actions' => $sections['legendary_actions'] ?? null,
        ];
    }
}

usort($creatures, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

$document = [
    'ruleset' => 'srd-5e-2024',
    'source' => 'SRD 5.2.1',
    'license' => 'CC-BY-4.0',
    'creatures' => $creatures,
];

file_put_contents(
    $dir.'/srd-5.2.1-creatures.json',
    json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n"
);

fwrite(STDERR, 'creatures: '.count($creatures)."\n");

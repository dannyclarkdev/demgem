# The SRD dataset

`srd-5.2.1-creatures.json` holds the 330 creature stat blocks of System Reference
Document 5.2.1. `php artisan demgem:import-srd` loads it into the `stat_blocks` table.
Nothing else writes that table.

Read `ATTRIBUTION.md` before you touch any of this.

## Provenance

| | |
|---|---|
| Document | System Reference Document 5.2.1, English |
| Publisher | Wizards of the Coast LLC |
| Published | 2025-05-01 |
| Licence | CC BY 4.0 |
| Official source | https://www.dndbeyond.com/resources/1781-system-reference-document-5-2-1 |
| Transcription used | `data/raw/5.2.1/monsters-A-Z.md` and `animals.md` from https://github.com/azemoning/omni-5e, itself a copy of https://github.com/downfallx/dnd-5e-srd-markdown |
| Retrieved | 2026-09-09 |
| Built by | `build-dataset.php` |
| Creatures | 330 (235 from monsters-A-Z.md, 95 from animals.md) |

The official release is a PDF. The transcription above is a Markdown rendering of the
same document, and it states SRD 5.2.1 as its only source and CC BY 4.0 as its licence.
That chain is why it is recorded here rather than assumed.

## Rebuilding it

```sh
mkdir -p /tmp/srd && cd /tmp/srd
curl -O https://raw.githubusercontent.com/azemoning/omni-5e/main/data/raw/5.2.1/monsters-A-Z.md
curl -O https://raw.githubusercontent.com/azemoning/omni-5e/main/data/raw/5.2.1/animals.md
php /path/to/demgem/database/srd/build-dataset.php /tmp/srd
```

Copy the JSON it writes over `srd-5.2.1-creatures.json`, then put its SHA-256 in
`config/compendium.php`. `CompendiumDatasetTest` compares the two and fails when the
file on disk stops matching the checksum in configuration, so the dataset cannot be
replaced quietly.

## What the parser does not carry

Spells, magic items, classes, equipment, and the rules glossary are all in the source
document and none of them are here. The compendium is creatures only, and widening it
is a slice of its own.

Lair actions and regional effects are printed outside the stat block in the SRD and are
not parsed. Habitat and treasure tables are likewise left behind.

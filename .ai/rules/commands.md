---
paths:
  - 'database/srd/**, app/Models/StatBlock.php, config/compendium.php, app/Console/Commands/ImportSrdCommand.php'
---

# Commands

## The compendium is global, read-only, and licensed
stat_blocks has no campaign_id on purpose: one install, one copy, every campaign reads the same rows. That keeps it out of the export, so ExportCoverageTest needs no entry for it — do not add one.

demgem:import-srd is the only writer. It checks the file against compendium.dataset_sha256 before reading a row, upserts on (ruleset, slug) so ids never move, and never deletes: a row a newer dataset drops is reported and left, because removing reference data under a live fight is a surprise.

The prose is CC BY 4.0 SRD material. The notice in config('compendium.attribution') must render wherever it appears — both screens and every API document. CC BY grants no trademark rights, so no user-visible string says D&D or Dungeons & Dragons; CompendiumLicensingTest holds that. Only SRD content goes in the dataset, and database/srd/README.md records where it came from.

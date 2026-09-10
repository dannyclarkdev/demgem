---
paths:
  - 'database/srd/**, app/Models/StatBlock.php, config/compendium.php, app/Console/Commands/ImportSrdCommand.php, app/Actions/Compendium/**'
---

# Commands

## stat_blocks holds two kinds of row, and campaign_id is the whole difference
Slice 15 recorded that stat_blocks had no campaign_id on purpose. Slice 17 gave it one, and both halves of the original decision survive as conditions rather than as facts about the table.

**campaign_id is null: shipped reference data.** One install, one copy, every campaign on that ruleset reads the same rows. demgem:import-srd is its only writer. It never leaves in an export — a campaign that references one exports {ruleset, slug}, never the id and never the prose. Every query in the loader is scoped with `shipped()`, or it would update, orphan or count a creature a GM wrote whose slug happens to match.

**campaign_id is set: one campaign's own creature.** A campaign row in every sense: its GMs write it, no other campaign can read it, it is a top-level export section carrying its prose, and its id is remapped through IdMap like any other. It cascades with the campaign.

Two partial unique indexes, not one over three columns. `(ruleset, slug) where campaign_id is null` is the loader's upsert key; `(campaign_id, slug)` keeps one campaign's own distinct. A single index over all three would not work — Postgres treats NULLs as distinct, so it would let the loader write a second shipped goblin.

A slug is set at creation and never moves, including on a rename. Everything here is addressed by slug on the web and in the API, and .ai/rules/api.md gives the reason a moving slug is not an address. This is deliberately the opposite call from an entity.

## The licence follows the words, not the table
The shipped prose is CC BY 4.0 SRD material. The notice in config('compendium.attribution') must render wherever it appears — both screens and every API document. CC BY grants no trademark rights, so no user-visible string says D&D or Dungeons & Dragons; CompendiumLicensingTest holds that. Only SRD content goes in the dataset, and database/srd/README.md records where it came from.

A creature a GM wrote is not SRD material and must carry no notice: printing one under their words would credit the wrong author. CopyStatBlock is the case in between — copying CC BY material into a campaign is what the licence allows, and carrying the source and licence with the copy is what it asks in return, so a copied creature still shows the notice. Both screens and StatBlockResource decide on `license`, never on whether the row has a campaign.

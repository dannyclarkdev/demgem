---
paths:
  - 'app/Actions/Campaigns/ExportCampaign.php, app/Actions/Campaigns/ImportCampaign.php, app/Actions/Campaigns/ReadCampaignFile.php'
---

# Actions Campaigns

## A stat_block reference is one of two things, and the column it uses says which
entities.stat_block_id and combatants.stat_block_id point at stat_blocks, which since slice 17 holds both shipped reference data and creatures a campaign wrote. The export writes a different reference for each, and the reader accepts either.

**A shipped row exports as a natural key, never through IdMap.** The export writes `stat_block: {ruleset, slug}` and never the id and never the prose: an id means nothing on the install that reads the file, and copying licensed text would put it in every GM's export with no notice attached. newFor() throws on an id it does not know, and there is nothing to remap — the pair either names a row this install has or it does not. ReadCampaignFile resolves it and counts the misses into ImportReport::$statBlocks so the GM reads the number before pressing the button; ImportCampaign leaves the column null. Same shape as RSVPs exported by member name.

**A campaign's own row exports as `stat_block_id`, remapped like every other id.** It is a campaign row, so rule one of .ai/rules/campaigns.md applies without exception, and the creature itself travels in the top-level stat_blocks section with its prose. ReadCampaignFile checks the reference against the rows the file carries rather than against this install, and refuses a file that points at a creature it does not hold. The stat_blocks section is read first for exactly that reason, and imported first because entities and encounters both point at it.

Exactly one of the two keys is filled on any row. RoundTripTest drops `stat_block_id` with the other remapped ids and compares `stat_block` in full; HomebrewRoundTripTest is what holds the own-creature link.

The encounters section streams with lazy(100), not cursor(): combatants.statBlock is a nested eager load and cursor() loads one level only.

---
paths:
  - 'app/Actions/Campaigns/ExportCampaign.php, app/Actions/Campaigns/ImportCampaign.php, app/Actions/Campaigns/ReadCampaignFile.php'
---

# Actions Campaigns

## A global row exports as a natural key, never through IdMap
entities.stat_block_id and combatants.stat_block_id point at stat_blocks, which belongs to the install rather than to the campaign. The export writes {ruleset, slug}, never the id and never the prose: an id means nothing on the install that reads the file, and copying licensed text would put it in every GM's export with no notice attached.

Never put such a reference through IdMap. newFor() throws on an id it does not know, and there is nothing to remap — the pair either names a row this install has or it does not. ReadCampaignFile resolves it and counts the misses into ImportReport::$statBlocks so the GM reads the number before pressing the button; ImportCampaign leaves the column null. Same shape as RSVPs exported by member name.

The encounters section streams with lazy(100), not cursor(): combatants.statBlock is a nested eager load and cursor() loads one level only.

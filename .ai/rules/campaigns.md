---
paths:
  - 'app/Actions/Campaigns/**'
---

# Campaigns

## A new campaign-scoped table joins the export in the same commit
ExportCampaign holds three lists: SECTION_TABLES (a top-level section), NESTED_TABLES (exported inside another section), and EXCLUDED_TABLES (left out, with the reason).

ExportCoverageTest reads the schema, keeps every table with a campaign_id column, and fails when one appears in none of the three. The failure mode it guards is silent: a table added later, a GM's export missing it, and nobody finding out for a year.

The export never carries email addresses, invite tokens, soft-deleted rows, or the derived mentions index.

## An import creates a campaign and never updates one
ImportCampaign always builds a new campaign in one transaction. There is no merge, no "import into this campaign", and no conflict resolution, because merging needs a rule for every row in the graph and the wrong rule silently overwrites a year of notes.

Three rules follow from that, and none of them are optional.

1. Every id is remapped through IdMap, always. Reuse works right up until a GM restores their own export into the install it came from, which is the most likely restore there is. IdMap::newFor() throws rather than returning null: a null written into a foreign key is a quiet wrong answer.

2. Writes go through forceFill, not create. `id` is in no model's Fillable list, and create() silently drops what it cannot assign, so every remapped id would be replaced by a fresh one and the whole map would point at rows that do not exist.

3. Nothing is ever made more visible than the file says. Visibility::Selected imports as Dm because its viewer list names users this install does not have. Widening it to Players would undo three slices of visibility work in one line.

The importer never fetches a URL out of an uploaded file. That is SSRF, and no scheme check makes it acceptable in a self-hosted app whose network we cannot see. Media is counted in the report and left behind.

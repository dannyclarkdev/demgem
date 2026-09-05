---
paths:
  - 'app/Actions/Campaigns/**'
  - app/Actions/Campaigns/ExportCampaign.php
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

## No string from an archive is ever used as a path
ReadCampaignArchive never calls extractTo(), never joins an entry name onto a directory, and names every file it writes with Str::ulid(). Only the entries campaign.json already named are read at all, so an entry called ../../../.env is not dangerous — it is one nothing asks for. The traversal test asserts the entry is inert rather than that it is caught, because the design is not a check.

The same rule runs the other way. A file_name from the document goes through basename() and a slug before Media Library sees it, and BuildCampaignArchive generates every entry name from an ordinal and a slug rather than taking the uploaded filename.

The three risks that survive it have numbers, not hopes: numFiles capped at 2,000; the uncompressed total summed from the central directory before a byte is read; every extracted file sniffed with finfo against Entity::FILE_MIME_TYPES, which is the allow-list the upload form uses. An archive can put nothing on this disk a GM could not have uploaded through the browser.

A file that fails either check is dropped and counted, never fatal. The campaign is the point; the picture is not.

Media attaches AFTER the import transaction commits. Files are not transactional in any database, so attaching inside would move bytes a rollback cannot take back.

## An added key does not bump the export version
The format policy, settled in slice 9: adding a key to `demgem.campaign` does not move `VERSION`; removing or changing the meaning of one does.

`archive_path` landed on media objects under that rule, so slice 8's importer reads a slice 9 archive's document perfectly well and an older demgem ignores the key it has never heard of.

`forArchive()` returns a CLONE rather than setting a flag. ExportCampaign is resolved from the container, and a flag left on a shared instance would leak archive_path into a plain JSON download, which must stay byte-for-byte what it has been since slice 4. Two tests hold that line, one of them by running an archive export first and then a plain one.

---
title: "feat: A ceiling on a campaign's files, and a bar that shows it"
type: feat
date: 2026-09-11
status: planned
brainstorm: docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md
follows: docs/plans/2026-09-11-feat-discord-login-plan.md
---

# feat: A ceiling on a campaign's files, and a bar that shows it

## Overview

The brainstorm's platform table says "per-campaign storage quotas, P2, needed before hosted launch". Every upload today is capped per file and unbounded per campaign. This slice puts one ceiling on a campaign's files and shows the GM how close they are to it.

| Feature | What it adds |
|---|---|
| One limit per install | `CAMPAIGN_STORAGE_MB`, 500 by default. Every campaign on the install gets the same ceiling. |
| The measure | The bytes of every file a campaign holds: its cover, every entity's image, every handout's files. Summed from the media rows on every read, never stored. |
| The refusals | An upload that would cross the ceiling is refused on the form with the numbers in the sentence. An archive import stops attaching files at the ceiling and says how many it left. |
| The bar | Campaign settings shows what is used of what is allowed. |

When this slice is done a GM with a 500 MB campaign uploads a 12 MB map scan and reads "That file would put the campaign over its 500 MB. 8 MB is free." A hosted install sets the number in its environment and every campaign obeys it.

**On scope.** Phase 0 is the measure and the refusals. Phase 1 is the import and the bar. Phase 2 is the rules, the README, and the pass.

## Problem Statement

**A per-file cap is not a quota.** A handout takes ten files of ten megabytes each, and a campaign takes as many handouts as a GM cares to make. Nothing today says when to stop, so a hosted install has no answer to one campaign filling the disk.

**The GM cannot see the number.** A limit nobody can read is a surprise on the day it is hit. The number belongs on the settings page beside the other facts about the campaign.

**An archive is the biggest upload there is.** Slice 9 caps what an archive may unpack. It does not ask whether the campaign that results is allowed to hold that much. The import must ask before it promises.

## Proposed Solution

**A quota is a config value, and the measure is a sum.** `config/campaigns.php` holds the ceiling. `CampaignStorage` answers the three questions: the limit, what a campaign uses, and whether a given number of bytes still fits. The use is summed from the `media` table across the campaign's own row and its entities, on every read. A stored total is the second source of truth that drifts, and the media table already has the sizes.

**A refusal is a validation error with the numbers in it.** The entity form and the campaign settings form both add the bytes of what was just uploaded to what is used, before anything is written. Over the ceiling is an error on the field that names the ceiling and what is free. Nothing is half-saved: the check runs before the entity row is created.

**The import trims to fit and says so.** `ReadCampaignArchive` already unpacks the files a document names and drops the ones that are not what they claim. After that pass it walks the restored files in order, sums their sizes, and stops at the ceiling; the rest are unlinked and counted. The report the GM reads before pressing the button gains a line for them, so the promise on the screen is the truth of what will attach.

## Technical Approach

### No new dependency

Nothing to install.

### What slices 1 to 22 give us for free

| Piece | Reuse |
|---|---|
| The `media` table | `size` on every row, and `model_type`/`model_id` to find a campaign's. |
| `Entities\Form` and `Campaigns\Settings` | The two places a file enters, both already validating a per-file cap. |
| `ReadCampaignArchive` and `ImportReport` | The unpack pass and the losses list the GM reads before an import. |
| `.ai/rules/campaigns.md` | Every risk in an archive has a number, not a hope; this is one more number. |

### The data

No migration. The measure is a query over `media`.

```
config/campaigns.php
  'storage_limit_mb' => env('CAMPAIGN_STORAGE_MB', 500)
```

### Scope and decisions

| Question | Decision |
|---|---|
| One limit or per campaign? | One, per install. A per-campaign limit is a hosted-plan question, and hosted plans are P2 or P3. The config is the seam. |
| What counts | Every media row the campaign owns: the cover, and every entity's `image` and `files`. Conversions are not counted; they are derived from the originals and the limit is for what the GM chose to keep. |
| Zero means | Off. A limit of 0 disables the check and the bar, for an install that does not want one. |
| When the check runs | Before any write, on the form. A refused upload leaves nothing behind. |
| Deleting frees space | Immediately, because the measure is a sum over rows that are gone. |
| The import | Trims at the ceiling in document order: the cover first, then entities in export order. Counted into the report before the GM commits. |
| The API | No file endpoint exists. Nothing to do. |
| Where the bar lives | Campaign settings only. A GM who wants the number knows where the campaign's facts are. |

### Screens

- **Campaign settings** gains a "Storage" line under the cover: used of allowed, with a bar, or "No limit on this install" when the limit is zero.
- **The entity form and the settings form** gain nothing visible until a file crosses the ceiling, and then an error on the field.
- **The import preview** gains a line in the losses list when files were left behind for space.

## Verification

    php artisan test --compact --filter=Storage
    php artisan test --compact tests/Feature/Media tests/Feature/Campaigns/ImportArchiveScreenTest.php tests/Feature/Campaigns/ReadArchiveTest.php
    vendor/bin/pint --dirty --format agent
    vendor/bin/phpstan analyse

Then the full suite with `memory_limit=1G`. The browser tool was failing at the end of slice 22; if it is back, a look at the settings bar. If not, the tests hold it.

## Open Questions

1. **Should the bar be on the dashboard as well?** Recommendation: not until a hosted plan needs it there.
2. **Should a GM be told at 80 percent?** Recommendation: no notice in this slice. The bar is the notice.
3. **Should the limit count conversions?** Recommendation: no, as decided above; they are derived and can be regenerated.

## References

- `.ai/rules/campaigns.md` — the three risks in an archive have numbers; this adds a fourth.
- `.ai/rules/models.md` — every media conversion names its collection, which is why conversions are easy to leave out of the count.
- `docs/plans/2026-09-04-feat-campaign-archive-plan.md` — the unpack pass this slice trims after.

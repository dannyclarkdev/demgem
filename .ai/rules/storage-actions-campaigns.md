---
paths:
  - 'app/Support/Storage/**, app/Actions/Campaigns/ReadCampaignArchive.php'
---

# Storage Actions Campaigns

## The storage ceiling is one config value, a sum over media rows, and a check before any write
`config('campaigns.storage_limit_mb')` (env `CAMPAIGN_STORAGE_MB`, 500 by default, fractional allowed, 0 turns it off) is the one ceiling for every campaign on the install. `CampaignStorage::usedBytes()` sums `media.size` over the campaign's own row and its entities' rows on every read; conversions are not counted and nothing is stored. Every place a file enters checks `canStore()` before writing anything: the entity form (image and handout files, summed) and the settings form (cover), each with `refusal()` as the error text. `ReadCampaignArchive::trimToQuota()` walks the unpacked files in document order, drops the ones past the ceiling, and counts them into `ImportReport::$filesOverQuota` before the GM commits, so the confirm screen promises exactly what will attach. A new upload path must call `canStore()` first; a stored total or a per-campaign limit column is deliberately absent until a hosted plan needs one.

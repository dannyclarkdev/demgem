---
paths:
  - 'tests/Feature/Campaigns/**'
---

# Feature Campaigns

## The round trip compares documents, not rows
RoundTripTest exports a campaign, imports it, exports the copy, and compares the two documents. Comparing documents rather than rows states the promise in the same language the feature does, and it fails the day the export grows a field the importer ignores.

Its section loop is driven by ExportCampaign::SECTION_TABLES, so a new section joins the comparison without anybody remembering to add it. Keep it that way.

Everything removed before the comparison is a documented loss: ids and timestamps, the media keys, the person columns, and the dice log. Do not add a key to that list to make a failing test pass — a new exclusion means the importer lost something the plan did not say it would.

The members section is asserted to hold exactly the importing user rather than being blanked, because "the members section is empty" would pass while the importer was not a member at all.

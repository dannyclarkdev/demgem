---
paths:
  - 'app/Actions/Campaigns/**'
---

# Campaigns

## A new campaign-scoped table joins the export in the same commit
ExportCampaign holds three lists: SECTION_TABLES (a top-level section), NESTED_TABLES (exported inside another section), and EXCLUDED_TABLES (left out, with the reason).

ExportCoverageTest reads the schema, keeps every table with a campaign_id column, and fails when one appears in none of the three. The failure mode it guards is silent: a table added later, a GM's export missing it, and nobody finding out for a year.

The export never carries email addresses, invite tokens, soft-deleted rows, or the derived mentions index.

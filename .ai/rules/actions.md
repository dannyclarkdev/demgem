---
paths:
  - 'app/Actions/**'
---

# Actions

## Sort nulls last with a case expression, not "nulls last"
`orderByRaw('... nulls last')` is Postgres-only. SQLite accepts different syntax, so a local suite passes and CI fails, which this project has already been bitten by once with ulidMorphs.

Use the portable form:

    ->orderByRaw('case when initiative is null then 1 else 0 end')
    ->orderByDesc('initiative')

SortByInitiative is the live example.

---
paths:
  - 'app/Livewire/**'
---

# Livewire

## Nested components re-check membership themselves
`InteractsWithCampaign::hydrateInteractsWithCampaign()` is what re-verifies membership on every Livewire round trip, and it runs per component, not per page. A nested component that writes (see `Sessions\LiveNotes`) must use the trait and call `enterCampaign()` in its own `mount()`, or a member removed mid-session keeps saving.

Policies need the matching fallback: copy `EntityPolicy::roleFor()`, which reads `CurrentCampaign` first and falls back to a database lookup when it is not set.

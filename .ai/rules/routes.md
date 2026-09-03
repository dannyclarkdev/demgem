---
paths:
  - routes/web.php
---

# Routes

## Do not name a route parameter after a model
`{encounter}` is claimed by Livewire's implicit route binding, which resolves it before mount() and therefore before enterCampaign(). Inside the scopeBindings() campaign group that becomes a call to Campaign::encounters() and a 500.

Every campaign-scoped screen resolves its own record in mount() after enterCampaign(). Name the parameter so binding cannot claim it: `{encounterId}`, `{tableId}`. Sessions (`{number}`) and entities (`{slug}`) escape this only because their keys are not model names.

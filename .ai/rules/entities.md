---
paths:
  - 'app/Livewire/Entities/**'
---

# Entities

## sheet_url is the one user URL rendered outside the Markdown renderer
Every other piece of user prose reaches the page through MarkdownRenderer, which strips raw HTML and blocks unsafe links. A character sheet link does not: it is written straight into an href.

`url:http,https` at write time is what stops `javascript:` from becoming a link the whole party can click, and the entity page renders it with target="_blank" rel="noopener noreferrer nofollow". A second field of this kind needs both halves, and a test that names the payload.

The character fields (character_class, level, sheet_url) are not DM fields: they sit outside the canEditDmFields block, so the owning player may edit their own PC.

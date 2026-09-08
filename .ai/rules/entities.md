---
paths:
  - 'app/Livewire/Entities/**'
---

# Entities

## sheet_url is the one user URL rendered outside the Markdown renderer
Every other piece of user prose reaches the page through MarkdownRenderer, which strips raw HTML and blocks unsafe links. A character sheet link does not: it is written straight into an href.

`url:http,https` at write time is what stops `javascript:` from becoming a link the whole party can click, and the entity page renders it with target="_blank" rel="noopener noreferrer nofollow". A second field of this kind needs both halves, and a test that names the payload.

The character fields (character_class, level, sheet_url) are not DM fields: they sit outside the canEditDmFields block, so the owning player may edit their own PC.

## Relationships are a GM's to write, not a player's
`EntityPolicy::manageRelations()` is GM roles only, even on a PC the player may edit: a relationship is a wiki fact rather than a sheet fact, and the visibility toggle on it is a reveal. The card's target picker goes through `Entity::visibleTo()` and `whereKeyNot` the page itself, and `Relations::relation()` looks a row up only through the page's own two lists, so an id from another page is a 404 rather than a write.

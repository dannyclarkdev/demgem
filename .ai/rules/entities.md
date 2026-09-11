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

## Templates are GM-owned starting bodies, applied only to new entities
Entity templates belong to one campaign and one entity type, carry only a name and body, and are never player-visible. ApplyEntityTemplate resolves the campaign/type/policy gates for both the form and API. Selecting an option alone does not change a draft; applying it confirms before replacing nonempty prose. Keep the picker and its unavailable error visible when the last selected template is deleted. Created entities retain independent copies.

## A journal is the one entity a player creates, and player_user_id is its author
`EntityType::Journal` is the only type `isPlayerWritable()`. `EntityPolicy::create()` takes the type as a third argument; pass it everywhere (`[Entity::class, $campaign, $type]`), because a create check without one stays a GM's. The author is `player_user_id`, set at birth from the actor and never moved: the form strips the DM card's `player_user_id`/`is_pc` on a journal so a GM's edit cannot wipe it. No new gate: `Entity::visibleTo()` already shows a row to its player, so Dm means "me and the GM" and Players means "the party". The author sets that one switch through its own rule set (`Rule::enum(Visibility)->only([Dm, Players])`), outside `updateDmFields()`, in the form and in `EntityController::rules()`. Selected stays a DM-card decision. The author may delete their own; other DM fields from a non-DM are never read, as on a PC's form.

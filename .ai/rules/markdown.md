---
paths:
  - 'app/Markdown/**'
---

# Markdown

## A :::secret fence is stripped from a player's text before anything reads it, in six places
`SecretBlocks::strip()` is the one function that removes a `:::secret` … `:::` fence, and every reader a player has calls it before the text goes anywhere: `MarkdownRenderer::render()` (stripped unless `WikiLinkRenderer::revealsSecrets()`, which is `role->isDm()`; no renderer at all means stripped), `GameSession::recapExcerpt()`, `EntityResource`/`SessionResource` for a non-DM key, `Search` (a non-DM hit must match outside the fence, checked in PHP over the hits because both Scout drivers search the stored column), `SyncMentions` (the fenced links are indexed under `field:secret`, a name no player-visible list carries), and the entity form/API update for a non-DM (the editor holds `strip(body)`; `SecretBlocks::merge()` re-appends the stored fences on save, at the end of the page). Only a GM's renderer parses the fence, through `SecretBlockExtension`, into `<aside class="secret-block">`. A new Markdown field or a new reader of prose must call strip() for a non-DM; a new player-visible mention field must not end in `:secret`. `mentions.source_field` is string(20), so a field name plus `:secret` must fit.

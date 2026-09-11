---
paths:
  - 'app/Livewire/Factions/**'
---

# Factions

## A faction's standing is two sums, and the party reads only the revealed one
`reputation_changes` is a log per faction with `player_visible` on each row, gated in the query by `ReputationChange::visibleTo()`. The party's standing is the sum of the revealed rows; the truth is the sum of all of them; both are computed on every read and never stored. `Factions\Reputation` shows a GM both numbers when they differ and a player only the first; `Entities\Show` mounts the card for a player only when at least one row is revealed, and `Entities\Index` badges a faction only when the viewer's sum has rows behind it, through one grouped query. The delta is never zero and never edited (delete and rewrite, the ledger's rule); the reason is. The bands and their words are `config/reputation.php`, demgem's own, read through `Support\Reputation\Standing`.

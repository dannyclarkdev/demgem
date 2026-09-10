---
paths:
  - 'app/Support/Encounters/**'
---

# Encounters

## The encounter budget is demgem's own, derived from the SRD's XP ladder
The SRD prices every creature and that data ships under CC BY 4.0. It publishes no encounter building budget, and the book that does is not ours to copy. So config/encounters.php states one rule and derives the rest: a character of level N affords a quarter, a half and three quarters of the XP of a CR N creature, summed over the party.

Do not replace these numbers with a published table. The read-out names the scale as demgem's own on screen for exactly this reason.

The CR-to-XP ladder is read out of the shipped dataset — the XP most creatures of a rating print, ties going higher — and EncounterBudgetTest fails when config stops agreeing with the data. The SRD names no creature at CR 18 or 25-29, so Budget::xpForChallenge() reads between rungs in a straight line. Only a row with a stat_block_id can be priced; hand-typed rows are counted and the band is labelled a floor.

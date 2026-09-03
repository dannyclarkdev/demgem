---
paths:
  - 'app/Support/Dice/**'
---

# Dice

## Dice limits live in the parser, not the form
DiceFormula::parse() enforces 100 dice, 1000 sides, 10 terms, and the keep count. Anything that rolls dice therefore gets the same answer about what is too big, and 999d999 is refused before a row is written.

There is one grammar and no aliases. There is deliberately no "adv" keyword: DiceFormula::withAdvantage() rewrites a leading d20 into 2d20kh1 before parsing. Do not add a second syntax for the same roll.

DiceRoller takes an injected Random\Randomizer so tests bind a seeded Mt19937 and assert exact faces.

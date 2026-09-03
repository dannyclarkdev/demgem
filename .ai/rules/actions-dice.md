---
paths:
  - 'app/Actions/Dice/**'
---

# Actions Dice

## A private roll broadcasts like any other
RollDice dispatches DiceRolled for every roll, private ones included. The event carries the campaign id and nothing else, so every screen re-renders, reads the log through DiceRoll::visibleTo() under its own viewer, and finds nothing new. Do not add a branch that skips the broadcast for a private roll, and do not add a second channel: both would be a filter where there is nothing to filter.

Two answers live in the action rather than in the tray, so every surface gets the same one:
- Only a GM role may set private. A player's roll is never private, and a forged request is downgraded silently.
- The limit is 30 logged rolls a minute per user, through the RateLimiter, checked before the parse so a refused roll writes nothing. RollDice::roll() (no logging) is not throttled: it is one GM click rolling for a dozen combatants.

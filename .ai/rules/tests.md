---
paths:
  - 'tests/**'
---

# Tests

## Never assert a bare number against output that carries a ULID
assertDontSee('59') and expect($payload)->not->toContain('59') both fail at random. Every id in this app is a ULID, Crockford base32 includes every digit, and a two-digit run turns up in one often enough to redden CI on an unlucky draw. It has cost two debugging sessions.

Assert something that cannot appear by chance instead: an exact array match, a name with a space in it, or a marker from the markup that proves the component did not render (the tracker's wire:poll, for example). Words work because Crockford base32 drops I, L, O, and U.

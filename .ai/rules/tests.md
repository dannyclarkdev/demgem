---
paths:
  - 'tests/**'
---

# Tests

## Never assert a bare number against output that carries a ULID
assertDontSee('59') and expect($payload)->not->toContain('59') both fail at random. Every id in this app is a ULID, Crockford base32 includes every digit, and a two-digit run turns up in one often enough to redden CI on an unlucky draw. It has cost two debugging sessions.

Assert something that cannot appear by chance instead: an exact array match, a name with a space in it, or a marker from the markup that proves the component did not render (the tracker's wire:poll, for example). Words work because Crockford base32 drops I, L, O, and U.

## A fake PDF needs real bytes
UploadedFile::fake()->create('x.pdf', 400, 'application/pdf') truncates a file to a size and writes nothing. Media Library sniffs the file rather than trusting the declared mime, reads application/x-empty, and the acceptsMimeTypes collection refuses it — which is the check working, not a bug.

Write the magic number instead:

    $handle = tmpfile();
    fwrite($handle, "%PDF-1.4\n...\n%%EOF\n");
    return new Illuminate\Http\Testing\File('ledger.pdf', $handle);

Testing\File, not UploadedFile: Livewire's ->set() on a file property reads a public $name that only the testing subclass has.

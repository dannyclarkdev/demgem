---
paths:
  - bootstrap/app.php
---

# Bootstrap

## Do not trim Markdown bodies at the request boundary
TrimStrings excludes body and updates.body so API and Livewire saves preserve indentation and trailing Markdown whitespace. EntityController maps body without trim; the importer likewise preserves exact body text. Empty text normalizes to null in entity/template write actions; nonempty body text must never be reformatted implicitly.

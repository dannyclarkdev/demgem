---
paths:
  - 'resources/views/**'
---

# Views

## @disabled and friends break inside x- component tags
`@disabled(...)`, `@checked(...)`, and `@selected(...)` only work on plain HTML elements. Inside a `<x-ui.button ...>` tag they compile to a stray `endif` and the whole view dies with a ParseError that names a compiled file, not your Blade file.

Pass the attribute instead: `:disabled="$loop->first"`. The attribute bag renders `true` and drops `false`.

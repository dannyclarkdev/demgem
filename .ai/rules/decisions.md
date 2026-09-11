---
paths:
  - 'app/Livewire/Decisions/**'
---

# Decisions

## Decisions\Log is one component in three places, and Index is only the page around it
`Decisions\Log` renders the log on `/decisions` (inside `Decisions\Index`, which is a page header and nothing else), on the session page, and on the run screen. Passed a `GameSession` it lists that session's rows, hides the session select, and records under that session. Do not fork it into a panel and a page: the write methods, the gate, and the session-link query would drift apart. It is nested and it writes, so it calls `enterCampaign()` in its own `mount()` (.ai/rules/livewire.md). The choice and the consequence are rendered to HTML in `render()` and handed to the Blade keyed by id; the Blade prints what it was given and never reads a row the query did not load.

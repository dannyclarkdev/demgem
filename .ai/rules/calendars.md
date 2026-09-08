---
paths:
  - 'app/Livewire/Calendars/**'
---

# Calendars

## The timeline and the month grid gate in the query and merge in PHP
Timeline and the calendar grid each run two queries: events through `Entity::visibleTo()` and sessions through `GameSession::scopeVisibleTo()`, then merge and sort the triples in PHP and hand the view a finished list. The Blade never asks who may see a row. The leak test is tests/Feature/Calendars/TimelineTest.php, and it checks both screens from a player's seat.

The calendar row itself and today's date are world facts every member reads; only `CampaignPolicy::update` (GM roles) may edit the shape, advance the day, or set it. `Calendars\Show::advance()` clamps the step to ±366 because the number arrives from the client.

---
description: Pull main and build the next slice in the P3 queue with the repo's slice workflow, then the one after it
---

Pull main first, then keep going with the slice workflow this repo has used since slice 1.

## Where things stand

Every P2 row of docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md is built except localization. The last six plans under docs/plans are dated 2026-09-11 and show the current shape of a slice. Read the newest plan's front matter to find the latest slice number, and read the README's slice paragraphs to learn what already exists before planning anything.

## The queue

Build these four P3 rows, one slice each, in this order. Skip a row that the README says is already done.

1. **Player screen for a second display.** A route like `/campaigns/{campaign}/screen` that shows what the GM pushes: the current handout, a map, the turn order as the party may see it, and the revealed clocks. Reuse the live table (Reverb, the ShouldRescue events, Table\Fight's query gates) and the handout "Show the party" action. The GM chooses what is on the screen from the Run screen.
2. **Downtime tracking.** What each character did between sessions, with a cost in days from the in-game calendar and a link to the session it happened around. A player writes their own PC's downtime; a GM writes anyone's. Shape it like the decision log and the ledger: a gated table, a nested Livewire component, summed on every read, in the export and the vault.
3. **Family trees.** Relationships already exist (slice 11, EntityRelation with a label and a reverse label). Add a kinship kind on a relation so parent, child, sibling, and spouse are typed, then draw a tree on a character's page from the typed relations, gated at both ends as the relations card already is. No new gate.
4. **The full 5e character sheet, as a ruleset module.** The core is system-agnostic and a ruleset adds its module; slices 15 to 17 built the compendium and the fight rules under that seam (`Ruleset::hasCompendium`, `config/encounters.php`). Ability scores, saves, skills, HP and hit dice, spell slots, and an inventory that reads from the party ledger, on a PC's page, editable by its player, shown only when the campaign's ruleset is SRD 5.2.1. Stat blocks already model six ability scores; reuse that shape.

## The workflow, per slice

Follow the pattern in docs/plans/2026-09-11-feat-secret-blocks-reputation-plan.md.

- Write the plan under docs/plans with the same front matter and sections; branch `feat/<name>-slice-<n>`; commit the plan alone first.
- Before editing, read `.ai/rules/index.md` and every rule file whose globs match, and `grep -rin` the rules for the area.
- Tests first. Run the narrow tests, then the full suite with `php -d memory_limit=1G vendor/bin/pest --compact`, then `vendor/bin/pint --dirty --format agent` and `vendor/bin/phpstan analyse`.
- Every new campaign-scoped table joins `ExportCampaign::SECTION_TABLES`, the reader, the importer, and the vault in the same commit, or ExportCoverageTest fails.
- Seed the demo world (DemoCampaignSeeder and DemoSeederTest) so the feature is visible on first run.
- Record durable rules with the Boost `record-rule` tool. Add the slice's paragraph to the README.
- Browser pass from both seats where the tool cooperates. Read the `browser-pass-mechanics` memory first: Livewire buttons need `element.click()` through `agent-browser eval`, `php artisan serve` ignores shell env so put a key in `.env` and restore it after, and `pkill -f agent-browser` if the daemon wedges. Stop the server and close the session when done.
- Append an "Implementation Results" section to the plan: what shipped against the plan, deviations with reasons, what was not done and why, and the browser checks.
- Commit, push, open the PR with `gh pr create`, watch the checks with `gh pr checks --watch`, and merge on green with `gh pr merge --merge`. Then check out main and pull.
- No AI attribution in commits or PR bodies.

Report after each merge in a few sentences, then start the next slice. Stop only for a new Composer or npm dependency, or a decision that is the user's to make.

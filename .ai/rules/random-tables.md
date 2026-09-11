---
paths:
  - 'database/generators/**, app/Support/Generators/**, app/Actions/RandomTables/InstallGenerator.php'
---

# Random Tables

## A generator set is copied into the campaign, never read globally
The slice 3 migration recorded why shipped tables do not sit in `random_tables` with a null `campaign_id`: the campaign scope would drop them silently. So a set is a JSON file under `database/generators`, read and shape-checked by `Support\Generators\Generators` (a typo or a nested slug the set lacks throws at read time), and `InstallGenerator` copies it into a campaign in one transaction as ordinary tables stamped with `generator_key`. Nothing at runtime knows the word generator except the tables index, which offers a set while no table carries its key. A copy is the GM's: editing a shipped file does not reach a copy already made, and a better set ships as a new key. A name the campaign already uses gets " (generator)" appended, and the set nests into the renamed table by id. The content is demgem's own; no published table is transcribed, and `database/generators/README.md` says so.

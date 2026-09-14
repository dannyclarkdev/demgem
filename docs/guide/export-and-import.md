# Take your data with you

The archive, the JSON, the Obsidian vault, and the importer.

A GM downloads the whole campaign from campaign settings, two ways:

- **The archive**, a zip. Inside it is `campaign.json`, every image and attachment beside it, and a Markdown folder with one file per page, foldered by type, with front matter and the wiki links left exactly as written. Obsidian opens that folder as a vault.
- **The JSON alone**, for anything that only wants the data.

Both carry every entity with its GM notes, every session with its prep, secrets, and recaps, plus quests, encounters, tables, maps, handouts, clocks, and the dice log. They leave out email addresses, invite links, and deleted things. `ExportCoverageTest` reads the schema and fails when a new campaign table is neither exported nor documented as excluded, so the export cannot quietly fall behind.

Either file imports back into any demgem, as a new campaign, from `/campaigns/import`. The JSON also imports from a terminal:

```sh
php artisan demgem:import path/to/campaign.json --user=you@example.com
```

The importer validates the whole file before it writes a row, remaps every id, and reports what it could not carry before the GM commits. It never fetches a URL found in the file and never uses a string from the archive as a path, so an untrusted file cannot reach the network or the disk. Four things stay behind on purpose: the members, because the file carries no email addresses, so the GM invites the party again; the viewer lists on entities shown to selected players, which import as GM-only rather than guess wider; the dice log, because the file cannot say who rolled; and the answers about sessions, who said yes and who turned up, for the same reason.

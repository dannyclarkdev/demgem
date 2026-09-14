# Templates and body history

Reusable Markdown outlines per entity type, and the history of every replaced body.

Open **Entity templates** from campaign settings to create a reusable Markdown outline for a character, location, quest, or any other entity type. On a new page, choose an outline and press **Use template**. Replacing an unsaved body requires confirmation. Only the body is copied; the page keeps its own name, visibility, tags, and other fields. Editing or deleting a template never changes existing pages.

**Body history** on an entity page lets a GM inspect and restore earlier text. Every changed body saved through the form or API preserves the body it replaces, including an empty body. History starts with the first body change after this feature is installed; earlier edits cannot be recovered. Automatic wiki-link replacements following a rename do not add revisions, and old snapshots retain their original link text. Restoring an old link may therefore leave it unresolved until edited.

History is GM-only, including on a player's own character: an earlier body may hold a secret removed before the page was revealed. Revision labels say who replaced the body and when, rather than claiming who originally wrote it. A restore changes only the body. This is not an undo for GM notes, media, visibility, or other fields.

Bodies are kept without expiry or individual deletion. Deleting an entity hides its history and leaves it out of exports; permanently deleting the entity or campaign removes it. Templates and history are carried in `campaign.json` inside an archive. The Markdown vault contains current pages only. Imported history keeps the replacement time and name, without linking that name to a local account.

The importer reads documents up to **25 MiB**, in both the browser and the Artisan command. Keeping all history can eventually exceed that limit. Exports still include every revision; they never silently drop history to fit. Larger imports and configurable retention are future work.

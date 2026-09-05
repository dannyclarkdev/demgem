---
title: "feat: The archive, and the strings it will not turn into paths"
type: feat
date: 2026-09-04
brainstorm: docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md
follows: docs/plans/2026-09-04-feat-json-importer-plan.md
---

# feat: The archive, and the strings it will not turn into paths

## Overview

Slice 8 shipped an importer with four documented losses. This closes the first one and delivers the brainstorm's Markdown row on the same trip.

| Feature | What it adds |
|---|---|
| A campaign archive | One `.zip`: the same `campaign.json`, the image bytes beside it, and the whole campaign as Markdown. |
| Media that survives | The importer reads an archive and restores every image and attachment. Loss 1 is gone. |
| Markdown with front matter | One file per page, foldered by type, wiki links untouched. Obsidian opens the folder and it works. |
| An import that takes either | A `.json` or a `.zip`, told apart by looking rather than by the file name. |

When this slice is done a GM exports one file, moves it to another demgem, and gets their campaign back **with the pictures in it** — and if they would rather read it in Obsidian, the same file already holds that.

**On scope.** Phases 0 and 1 are a release: the archive round-trips with its media. Phases 2 and 3 are a second. Reading an Obsidian vault back in is **not in this slice** and is not a small addition to it; see below.

## Problem Statement

**The importer's biggest loss is the one a GM notices first.** Slice 8 refused to fetch the URLs in an export, and it was right to: a request the server makes to an address inside an uploaded file is server-side request forgery, and no scheme check makes that acceptable in an app whose network this project cannot see. But the consequence is that a restored campaign has no maps, no portraits, and no handout scans, and those are the things a GM sees the moment the page loads.

The answer was always the same one: **carry the bytes**. A JSON document cannot, so the file has to become an archive.

**And the second row is free once the first is done.** The brainstorm asks for "Markdown export with front matter, in a zip" so Obsidian can open it. Once there is a zip, that is a writer and a folder layout. The wiki-link syntax already matches: `[[The Salt Cathedral]]` means the same thing in demgem and in Obsidian, which was true by accident of taste and is now worth something.

**The hard part is not the zip. It is the reading of one.** An uploaded archive is the most dangerous input this app has ever taken: an entry named `../../../.env`, an entry that decompresses to forty gigabytes, an entry claiming to be a PNG that is a PHP file, a symlink pointing at `/etc/passwd`. Slice 8's rule was "never fetch a URL from the file". This slice's rule is its sibling and it is stated the same way.

## Proposed Solution

**No string from an archive is ever used as a path.**

That single sentence removes the entire traversal class, and it is a design decision rather than a check. The importer never calls `extractTo`, never joins an entry name onto a directory, and never writes a file whose name came from the archive. It opens the entries it already expects — their names are in `campaign.json`, which it has read and validated first — and it streams each one into a temporary file whose name **this application generated**.

An archive entry called `../../../.env` is therefore not dangerous. It is simply an entry nothing asks for.

**The three remaining risks each get a number, not a hope.**

- **Decompressed size.** `ZipArchive::statIndex()` reports the uncompressed size before a byte is read. The importer sums it across the entries it intends to read and refuses past **200MB**, and it refuses an archive with more than **2,000 entries**. A bomb is a file that lies about neither: it just says forty gigabytes, and we decline.
- **What a file actually is.** Every extracted file is re-sniffed with `finfo` and checked against the same allow-list the upload form uses. `campaign.json` says a media entry is a PNG; the archive is not asked to be honest about that, it is measured.
- **Where it lands.** Media goes in through Media Library's own `addMedia()` from our temporary path, so the disk, the naming and the conversions are the code that already handles uploads.

**`campaign.json` does not change shape, it gains a key.** Each media object grows `archive_path`, naming the entry that holds its bytes. That is additive: a version 1 reader that has never heard of it ignores it, and slice 8's importer reads a slice 9 archive's JSON perfectly well. **This fixes the format policy in writing: an added key does not bump the version, a removed or changed one does.**

**The Markdown is a second view of the same data, and it is one-way.** It exists to be read — in Obsidian, in a text editor, in a git repository — and nothing in this slice reads it back. That is not laziness: parsing an arbitrary vault means guessing which files are entities, which front matter fields are ours, and what a `[[link]]` means when the target file was renamed by hand. It is a slice with its own hard problem, and it deserves its own plan.

## Technical Approach

### No new dependency

`ZipArchive` is in PHP's core zip extension, which the Docker image already installs and the CI job already has. There is no zip library to add, no YAML library either: front matter is four to eight scalar fields and a list of strings, and hand-writing it means the quoting rules are ours to state rather than to inherit.

### The archive

```
demgem-the-drowned-duchy-2026-09-04.zip
├── campaign.json                       the slice 8 document, plus archive_path
├── README.md                           what this is, and what opens it
├── media/
│   ├── 0001-the-duchy-of-vell.png
│   └── 0002-the-dukes-letter.png
└── markdown/
    ├── characters/wren.md
    ├── locations/the-salt-cathedral.md
    ├── handouts/the-dukes-letter.md
    └── sessions/01-the-harbor-fire.md
```

- **Media names are generated, never taken.** `sprintf('media/%04d-%s.%s', $ordinal, $slug, $extension)`. The ordinal makes it unique without leaking a database id, and the slug makes the folder readable when a human opens it.
- **`README.md` is a courtesy.** A GM who unzips this in three years should not have to guess. Four sentences: what made it, what reads it back, and that the Markdown is a copy rather than the source.

### Writing it

`BuildCampaignArchive` writes to a temporary file and hands back a path; the controller sends it with `deleteFileAfterSend()`.

The JSON export streams because a campaign of any size must start downloading at once. **A zip cannot, and the plan says so rather than pretending.** `ZipArchive` needs a real file to finalise its central directory, so an archive is built and then sent. The mitigation is that it is built with `addFile()` — pointing at media already on disk rather than reading them into memory — so the peak cost is the JSON document, exactly as it is today.

### Reading it

```php
// ReadCampaignArchive: the safe half.
// 1. Open the zip. Refuse if it is not one.
// 2. Read campaign.json through getStream(), never extractTo. Hand it to
//    ReadCampaignFile, which is unchanged and still knows nothing about zips.
// 3. Sum the uncompressed sizes of the entries that document names. Refuse past
//    the cap, before reading any of them.
// 4. Stream each named entry to a temp path this app chose, sniff it, keep it or
//    drop it.
```

`ReadCampaignFile` does not learn about archives. It takes a JSON string and returns a document, and that is the whole of its job; the archive reader is a front half that produces one. That keeps every slice 8 test meaningful and every new risk in one file.

### Restoring media

The document already says which entity owns which media, because it always did — `image`, `files[]` and the campaign's `cover`. `ImportCampaign` gains an optional map of `archive_path` to a local temp file, and attaches each one through `addMedia()` after the row exists.

A missing or refused file is **not** an error. The archive is a convenience and the campaign is the point: a GM whose zip lost one PNG should get their campaign with 11 images and a line in the report, not a refusal. The report gains a fifth line for it, and loss 1's wording changes from "cannot come across" to "came across" when the file is an archive.

### The Markdown

One file per entity, per session, and one per random table.

```markdown
---
name: The Salt Cathedral
type: location
visibility: players
tags: [coastal, ruin]
parent: Harrowgate
demgem: entity
---

The tide fills the nave twice a day.

## GM notes

They are not what they seem.
```

- **Front matter is written by hand, and the rules are stated.** Every value is a string or a list of strings; anything holding a character YAML would argue about is double-quoted with `"` escaped. There are no nested maps, no dates, and no numbers that matter, so the whole surface is one function and a test that round-trips the awkward names.
- **The body is the Markdown that was already there.** `[[The Salt Cathedral]]` is left exactly as written, because that is what Obsidian wants. Nothing is rewritten on the way out.
- **GM notes go in, under a heading.** The JSON export carries `dm_notes` and this is the same file in a different shape; a Markdown export that quietly dropped half the campaign would be worse than one that includes it. The README says so.
- **Sessions are numbered in the filename**, `01-the-harbor-fire.md`, so a folder listing is in play order.

### The one place this touches old code

`Campaign::registerMediaConversions()` still declares `card` with no collection. It has one collection, so it is harmless today — and it is exactly the shape that bit `Entity` in slice 7 the moment a second collection appeared. It gets `performOnCollections('cover')` here, so the rule recorded in `.ai/rules/models.md` is true everywhere rather than in one model.

## Decisions resolved

| Question | Decision |
|---|---|
| How media crosses | In a zip, as bytes. Never fetched from a URL: that was slice 8's rule and it stands. |
| Path traversal | Impossible by construction. No string from an archive is ever used as a path; entries are read by expected name and streamed to names this app generates. |
| Zip bombs | Uncompressed size summed from the directory before reading, capped at 200MB and 2,000 entries. |
| Trusting `mime_type` | Never. Every extracted file is re-sniffed and checked against the upload allow-list. |
| `extractTo` | Never called. |
| Format version | Unchanged. `archive_path` is additive, and the policy is now written down: an added key does not bump the version; a removed or changed one does. |
| A media file that fails its checks | Dropped and counted, not fatal. The campaign is the point; the picture is not. |
| Streaming the zip | Not possible with `ZipArchive`, and the plan says so. `addFile()` keeps the media out of memory, so the peak is the JSON, as today. |
| Markdown direction | Out only. Reading a vault back is a different hard problem and a different plan. |
| A YAML library | No. Front matter here is scalars and string lists; the quoting rule is ours to state and to test. |
| GM notes in the Markdown | Included, under a heading, as the JSON already includes them. The README says so. |
| Where the archive is built | A temp file, sent with `deleteFileAfterSend()`. |
| Telling a `.json` from a `.zip` | By reading the first bytes, not the file name. |
| New tables | None. |

## Implementation Phases

Each phase ends with a green suite. Phases 0 and 1 are a release.

### Phase 0: Writing the archive

Deliverables:
- `BuildCampaignArchive`: `campaign.json` with `archive_path` on every media object, the media beside it, and `README.md`.
- `CampaignArchiveController` at `/campaigns/{campaign}/archive`, GM only, throttled as the export is.
- `ExportCampaign` gains the `archive_path` key, behind a flag so the plain JSON export is byte-for-byte what it was.

Tests: `tests/Feature/Campaigns/BuildArchiveTest.php` — the zip holds the document, every media file, and the README; `archive_path` names an entry that exists; a campaign with no media makes a valid archive; the JSON export is unchanged.

Success: a GM downloads one file that holds their whole campaign.

### Phase 1: Reading it back

Deliverables:
- `ReadCampaignArchive`: the four steps above, with the caps and the sniffing.
- `ImportCampaign` restores media from the extracted files.
- The report gains "12 files came across" and loses the "cannot" line when the source is an archive.

Tests: `tests/Feature/Campaigns/ReadArchiveTest.php` — a round trip restores every image; **an entry named `../../../.env` is ignored and nothing is written outside the temp directory**; an archive whose declared uncompressed size passes the cap is refused before reading; a PHP file renamed `.png` is dropped and counted, and the campaign still imports; an archive with no `campaign.json` is refused.

Success: a restored campaign has its maps, its portraits and its handout scans.

### Phase 2: The Markdown

Deliverables:
- `WriteCampaignMarkdown`: front matter, folders by type, sessions numbered, tables as lists.
- The front matter quoting rule and its test.
- `README.md` explains the folder.

Tests: `tests/Feature/Campaigns/MarkdownExportTest.php` — a name holding a colon, a quote and a `#` survives the front matter; wiki links are untouched; GM notes appear under their heading; a session file is named in play order.

Success: a GM opens the `markdown/` folder as an Obsidian vault and the links work.

### Phase 3: The screens

Deliverables:
- Campaign settings offers both downloads, with a sentence each saying what they are for.
- The import screen accepts `.zip` and `.json`, told apart by the first bytes.
- The report's file line reads correctly in both cases.

Tests: `tests/Feature/Campaigns/ImportArchiveScreenTest.php` — a zip upload previews and imports; a json upload still does; a renamed file is judged by content.

Success: a GM never has to know which of the two they downloaded.

### Phase 4: Polish

- The round trip runs again with media: export an archive, import it, and the copy's media count matches.
- `Campaign::registerMediaConversions()` scoped.
- Empty states and the wording pass.
- The browser pass at 1024px and 768px.
- Record the rules: no archive string becomes a path; caps before reads; sniff, never trust; the version policy.
- Pint, Larastan, the full suite, `npm run build`.

## Alternative Approaches Considered

- **Fetch the URLs after all, with an allow-list.** The direct fix, and it needs no zip. Rejected again, and harder than in slice 8: an allow-list of hosts is a configuration a self-hoster gets wrong once, and the request still originates inside their network.
- **Base64 the images into the JSON.** No new format, no zip, no traversal risk at all. Rejected: it inflates every byte by a third, turns a 40MB campaign into a 55MB string PHP must hold whole, and makes the document unreadable in the editor it is meant to be readable in.
- **`extractTo()` a temporary directory, then read.** One line instead of a stream loop. Rejected: it is the call that makes traversal possible, and PHP's own protections against it have had CVEs. Not calling it is free.
- **A YAML library for the front matter.** Correct in general. Rejected for this: the surface is scalars and string lists, a dependency is a decision the project takes seriously, and hand-writing it means the quoting rule is stated in a test rather than assumed.
- **Markdown import in the same slice.** The brainstorm pairs them. Rejected: reading a vault means guessing which files are ours, what to do with hand-renamed files, and how to reconcile a `[[link]]` whose target moved. That is a plan, not a phase.
- **Replacing the JSON export with the archive.** Fewer buttons. Rejected: a JSON file is greppable, diffable, and readable in a browser, and a GM who wants to see what leaves should not have to unzip it first.

## Acceptance Criteria

### Functional

- [x] A GM downloads one `.zip` holding the document, the media and the Markdown.
- [x] Importing that zip restores every image and attachment.
- [x] The import report says how many files came across rather than how many could not.
- [x] A `.json` still imports exactly as it did.
- [x] A file is judged by its first bytes, not its name.
- [x] The `markdown/` folder opens as an Obsidian vault with working links. *(The links are asserted untouched; opening it in Obsidian itself is not something a test can do.)*
- [x] Front matter survives a name with a colon, a quote and a `#` in it.
- [x] The plain JSON export is unchanged for anyone already using it. *(Two tests, one of them running an archive export first to prove the flag does not leak.)*

### Non-functional

- [x] **No string from an archive is ever used as a path, and a `../` entry is inert.**
- [x] **An archive is refused on declared uncompressed size before any entry is read.** *(And on entry count, which is the cheaper of the two checks.)*
- [x] **Every extracted file is sniffed, and one that is not what it claims is dropped and counted.**
- [x] A media file that fails its checks does not fail the import.
- [x] The importer still makes no outbound HTTP request.
- [x] Building an archive holds no media in memory. *(`addFile()`, so the peak is the JSON document.)*
- [x] The rows of an archive import are one transaction, as a JSON import is. **The media attaches after it commits**, which corrects the plan: files are not transactional in any database, so attaching inside would move bytes a rollback could not take back.
- [ ] Both download buttons work at 1024px and 768px, dark and light. *(Not checked: a tab created by the browser extension does not inherit the dev login, and I do not type passwords.)*

### Quality gates

- [x] Pest suite green on SQLite locally. PostgreSQL in CI is the pull request's job.
- [x] Larastan level 6 clean. Pint clean.
- [x] No new PHP or JavaScript dependency.
- [x] `Campaign::registerMediaConversions()` names its collection.
- [x] `npm run build` clean.

## Dependencies & Risks

| Risk | Mitigation |
|---|---|
| A crafted entry name escapes the temp directory | It cannot: no archive string becomes a path. The test asserts a `../` entry is inert rather than that it is caught. |
| A zip bomb exhausts the disk | Declared uncompressed size is summed before any read, and capped. |
| A file claiming to be a PNG is not | Sniffed with finfo, checked against the upload allow-list, dropped and counted. |
| The zip cannot stream | Stated rather than hidden. `addFile()` keeps media out of memory; the peak is the JSON, as today. |
| The Markdown tempts somebody to import it | The README says it is a copy, and the plan says a vault reader is its own slice. |
| Front matter breaks on a strange name | The quoting rule is one function with a test built from the awkward cases. |
| The slice runs long | Phases 0 and 1 are a release on their own, and they are the half that closes loss 1. |

## Future Considerations

- **Reading an Obsidian vault.** The brainstorm's other row, and a real slice: which files are entities, what to do with hand-renamed ones, and how to resolve a link whose target moved.
- **Kanka and World Anvil import.** Both are that reader with a different front half.
- **A scheduled archive to object storage.** MinIO is already in the dev environment, and "a backup that runs on its own" is the next thing a self-hoster asks for.
- **Streaming the zip.** Possible with a hand-rolled writer, worth it only if a campaign ever gets big enough to notice.

## References

### Internal

- Slice 8 plan: `docs/plans/2026-09-04-feat-json-importer-plan.md` — the four losses, the SSRF refusal this slice keeps, and the reader it reuses unchanged
- Slice 7 plan: `docs/plans/2026-09-03-feat-handouts-clocks-plan.md` — the `files` collection and the conversion-scoping trap this slice finishes closing
- Patterns to copy: `app/Actions/Campaigns/ExportCampaign.php`, `app/Actions/Campaigns/ReadCampaignFile.php`, `app/Http/Controllers/CampaignExportController.php`
- Project rules: `.ai/rules/campaigns.md` (an import creates and never updates), `.ai/rules/models.md` (a conversion names its collection), `.ai/rules/tests.md`

### External

- PHP `ZipArchive::getStream`: https://www.php.net/manual/en/ziparchive.getstream.php
- PHP `ZipArchive::statIndex`: https://www.php.net/manual/en/ziparchive.statindex.php
- OWASP on path traversal: https://owasp.org/www-community/attacks/Path_Traversal
- Obsidian on YAML front matter: https://help.obsidian.md/Editing+and+formatting/Properties

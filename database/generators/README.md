# Generators

Six table sets a GM adds to a campaign from the tables index. Each file is one set:
a key, a name, a description, and its tables, with nesting written as a slug within
the set. `App\Support\Generators\Generators` reads this directory and
`App\Actions\RandomTables\InstallGenerator` copies a set into a campaign as ordinary
random tables, stamped with the set's key.

The content is demgem's own, written for this app, under the repository's licence.
No published table is transcribed here. A set, once copied, belongs to the campaign:
the GM edits it, and a later change to a file here does not reach a copy already made.

A file's shape:

    {
      "key": "npc",
      "name": "Someone the party meets",
      "description": "...",
      "tables": [
        {
          "slug": "npc-who",
          "name": "Someone the party meets",
          "description": "...",
          "entries": [
            { "body": "A ferryman with one oar.", "weight": 1, "nested": "npc-wants" }
          ]
        }
      ]
    }

`weight` defaults to 1 and `nested` to none. A body is Markdown, up to 300 characters.

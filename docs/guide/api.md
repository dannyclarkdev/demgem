# The API

Every screen's data in JSON, behind the same policies as every page.

Every screen's data, in JSON, for a script or an assistant. Get a key from your profile: it reads what you can read in every campaign you belong to, and writes what you can write if you ticked **Can write** when you made it. Send it as a bearer token.

```sh
curl -H "Authorization: Bearer $DEMGEM_KEY" https://demgem.example/api/v1/me
```

| Method and path | What it does |
|---|---|
| `GET /api/v1/me` | You, and the campaigns you belong to with your role in each. |
| `GET /api/v1/campaigns` | The same campaigns, with each calendar's current date. |
| `GET /api/v1/campaigns/{id}` | One campaign. |
| `GET /api/v1/campaigns/{id}/entities` | Every entity you may see. Filter with `type=locations`, `tag=harbor`, or `q=bell`. Fifty a page. |
| `GET /api/v1/campaigns/{id}/entities/{entityId}` | One entity with its parent, children, and relationships, each through its own visibility gate. |
| `GET /api/v1/campaigns/{id}/search?q=` | Full-text search over what you may see. |
| `GET /api/v1/campaigns/{id}/sessions` | Every session you may see. A player gets the schedule and the published recap; a GM gets the prep too. |
| `GET /api/v1/campaigns/{id}/sessions/{number}` | One session, with scenes, secrets, and prepped entities for GM roles. |
| `POST /api/v1/campaigns/{id}/entities` | Create an entity. GM roles, write key. |
| `PATCH /api/v1/campaigns/{id}/entities/{entityId}` | Change one. GM roles on anything; a player on their own PC's body and record. |
| `PATCH /api/v1/campaigns/{id}/sessions/{number}` | Change a session's title, status, and notes. GM roles, write key. |
| `POST /api/v1/campaigns/{id}/sessions/{number}/publish-recap` | Publish the recap, and save a new one on the way if you send `recap`. |

Templates and history use the same campaign prefix, `/api/v1/campaigns/{id}`:

| Method and path | What it does |
|---|---|
| `GET /entity-templates` | GM-only summaries, 50 per page. Optional `type=character` filter uses the singular entity type. |
| `GET /entity-templates/{templateId}` | GM-only template with its body. |
| `POST /entity-templates` | Create with `name`, singular `type`, and optional `body`. GM role, write key. |
| `PATCH /entity-templates/{templateId}` | Change the template's name, type, or body. GM role, write key. |
| `GET /entities/{entityId}/body-revisions` | GM-only summaries, 25 per page, newest first. |
| `GET /entities/{entityId}/body-revisions/{revisionId}` | One previous body with `recorded_at` and `replaced_by_name`. GM-only. |
| `POST /entities/{entityId}/body-revisions/{revisionId}/restore` | Restore the body and return the updated entity. No payload. GM role, write key. |

`POST /entities` also accepts `template_id` for a matching entity type. Omit `body` to use the template's text; an explicitly supplied body, including null, takes precedence. A template is resolved within the campaign even when the body is overridden. `PATCH /entities/{entityId}` cannot apply a template. Markdown body whitespace is preserved.

The API creates and changes; it never deletes. A field your key may not set comes back as a 422 that names it, not a silent drop. Sixty requests a minute per key. A campaign you are not a member of is a 404, the same as on the web.

---
paths:
  - 'app/Actions/Entities/**'
---

# Actions Entities

## Body history records displaced prose at the shared write boundary
UpdateEntity reloads and locks the entity inside its transaction before comparing a supplied body. Changed bodies preserve the previous exact text, replacement time and actor; absent or unchanged bodies add nothing. RestoreEntityBody passes only a selected body through the same action. Automatic RewriteWikiLinks saves and import reconstruction intentionally do not record revisions; historical text is never renamed. Both viewHistory and restoreBody are GM-only, including on player-owned PCs.

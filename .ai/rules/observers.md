---
paths:
  - 'app/Observers/**'
---

# Observers

## Derive the mention field map from mentionableFields()
EntityObserver used to hardcode ['body', 'dm_notes'] in both the wasChanged() check and the sync map. Adding `rewards` to Entity::mentionableFields() therefore indexed nothing at all until the list stopped being written twice.

Both observers now build the field map by looping mentionableFields(). Adding a field to the model is the whole integration; do not reintroduce a literal list.

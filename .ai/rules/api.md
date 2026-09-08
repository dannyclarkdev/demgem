---
paths:
  - 'app/Http/Controllers/Api/**'
---

# Api

## The API is the screens in JSON: same scopes, same actions, keys dropped not nulled
Every API list goes through the scope the page uses (Entity::visibleTo, GameSession::visibleTo, EntityRelation::visibleTo), and every neighbour of an entity is loaded through its own scope and handed to the resource with setRelation(), so whenLoaded() puts it in. Resources under App\Http\Resources\Api\V1 read the viewer's role from CurrentCampaign (set by EnsureCampaignMember, which the api group shares with web.php) and leave a DM-only key OUT rather than nulling it, so a diff of two roles' documents shows the gate; assert leaks with assertJsonMissingPath, on the JSON.

Every write calls the action the Livewire form calls (CreateEntity, UpdateEntity, UpdateSession, PublishRecap) behind the same policy, and a field the key may not set is `prohibited` so the 422 names it. The API never deletes: deleting is a screen with a confirmation. Entities are addressed by id, never slug, because a slug changes on rename.

Write routes sit behind `abilities:write`; a key without it is 403. `$middleware->throttleApi()` in bootstrap is what puts throttle:api on the group at all, and AppServiceProvider defines the limiter.

---
paths:
  - 'tests/Feature/Api/**'
---

# Feature Api

## Use asKey() and withoutKey(): one test app keeps the last request's user and stubs
A Pest test serves every request from one app. After a bearer request, the Sanctum RequestGuard still holds that user, so a second withToken() with a different key silently acts as the first user, and a request with no header is still authenticated. The Pest.php helpers asKey($user, write: bool) and withoutKey() call app('auth')->forgetGuards() and clear CurrentCampaign before each request; use them, never a bare withToken(). After Livewire::actingAs() in the same test, forget the guards before asserting a 401.

Http::fake() merges stubs and the FIRST stub that answers wins. A catch-all Http::fake() in beforeEach swallows a per-test Http::fake(['*' => Http::response('', 503)]). Fake per test, and Sleep::fake() when a retry is in play.

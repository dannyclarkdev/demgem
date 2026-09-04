<?php

use App\Actions\Campaigns\ImportCampaign;
use App\Actions\Campaigns\ReadCampaignFile;
use App\Actions\Entities\SyncTags;
use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Enums\Visibility;
use App\Models\Campaign;
use App\Models\Clock;
use App\Models\Combatant;
use App\Models\Encounter;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\MapMarker;
use App\Models\QuestObjective;
use App\Models\RandomTable;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\DemoCampaignSeeder;
use Illuminate\Support\Facades\DB;

/**
 * The writer builds a campaign from a document the reader has already checked.
 *
 * The interesting assertions are not "the rows exist" but "every reference points
 * inside the new campaign": an import that reuses an id or drops a remap produces a
 * campaign that looks right and is wired to somebody else's world.
 */
function exportedDocument(Campaign $campaign): array
{
    $result = app(ReadCampaignFile::class)->handle(json_encode(exportedArray($campaign), JSON_THROW_ON_ERROR));

    expect($result->errors)->toBe([]);

    return $result->document;
}

function importFrom(Campaign $source, ?User $importer = null): Campaign
{
    return app(ImportCampaign::class)->handle(exportedDocument($source), $importer ?? User::factory()->create());
}

it('builds a campaign with every section the file holds', function () {
    $this->seed(DemoCampaignSeeder::class);

    $source = Campaign::query()->firstOrFail();
    $copy = importFrom($source);

    expect($copy->id)->not->toBe($source->id)
        ->and($copy->name)->toBe($source->name);

    $count = fn (string $model, string $campaignId) => $model::withoutGlobalScopes()->where('campaign_id', $campaignId)->count();

    foreach ([Entity::class, GameSession::class, Encounter::class, RandomTable::class, Clock::class, Combatant::class, MapMarker::class, QuestObjective::class] as $model) {
        expect($count($model, $copy->id))
            ->toBe($count($model, $source->id), $model.' did not come across whole');
    }
});

it('gives every row a new id and points every reference inside the new campaign', function () {
    $this->seed(DemoCampaignSeeder::class);

    $source = Campaign::query()->firstOrFail();
    $copy = importFrom($source);

    $sourceEntityIds = Entity::withoutGlobalScopes()->where('campaign_id', $source->id)->pluck('id');
    $copyEntities = Entity::withoutGlobalScopes()->where('campaign_id', $copy->id)->get();

    expect($copyEntities->pluck('id')->intersect($sourceEntityIds))->toBeEmpty();

    $copyIds = $copyEntities->pluck('id');

    // Every self-reference resolves inside the copy, and never back at the original.
    foreach ($copyEntities->whereNotNull('parent_id') as $child) {
        expect($copyIds)->toContain($child->parent_id);
    }

    $markers = MapMarker::withoutGlobalScopes()->where('campaign_id', $copy->id)->get();

    expect($markers)->not->toBeEmpty();

    foreach ($markers->whereNotNull('target_entity_id') as $marker) {
        expect($copyIds)->toContain($marker->target_entity_id);
    }

    $clocks = Clock::withoutGlobalScopes()->where('campaign_id', $copy->id)->whereNotNull('entity_id')->get();

    expect($clocks)->not->toBeEmpty();

    foreach ($clocks as $clock) {
        expect($copyIds)->toContain($clock->entity_id);
    }
});

it('carries the turn order and whose turn it is', function () {
    $this->seed(DemoCampaignSeeder::class);

    $source = Campaign::query()->firstOrFail();
    $copy = importFrom($source);

    $encounter = Encounter::withoutGlobalScopes()->where('campaign_id', $copy->id)->whereNotNull('active_combatant_id')->first();

    expect($encounter)->not->toBeNull();

    $combatantIds = Combatant::withoutGlobalScopes()->where('encounter_id', $encounter->id)->pluck('id');

    // active_combatant_id carries no foreign key, so nothing but this test says it
    // points at a row in the same fight.
    expect($combatantIds)->toContain($encounter->active_combatant_id);
});

it('makes the importer the only member, as owner', function () {
    $this->seed(DemoCampaignSeeder::class);

    $source = Campaign::query()->firstOrFail();

    expect($source->members()->count())->toBe(2);

    $importer = User::factory()->create();
    $copy = importFrom($source, $importer);

    expect($copy->members()->count())->toBe(1)
        ->and($copy->roleFor($importer))->toBe(CampaignRole::Owner);
});

it('leaves a player character unclaimed rather than guessing whose it is', function () {
    $this->seed(DemoCampaignSeeder::class);

    $source = Campaign::query()->firstOrFail();

    expect(Entity::withoutGlobalScopes()->where('campaign_id', $source->id)->whereNotNull('player_user_id')->count())
        ->toBeGreaterThan(0);

    $copy = importFrom($source);

    expect(Entity::withoutGlobalScopes()->where('campaign_id', $copy->id)->whereNotNull('player_user_id')->count())->toBe(0)
        ->and(Entity::withoutGlobalScopes()->where('campaign_id', $copy->id)->where('is_pc', true)->count())
        ->toBeGreaterThan(0);
});

it('brings a selected page in GM-only', function () {
    $campaign = Campaign::factory()->create();
    $chosen = memberOf($campaign, CampaignRole::Player);

    $secretive = Entity::factory()->for($campaign)->create([
        'name' => 'The sealed orders', 'slug' => 'sealed-orders', 'visibility' => Visibility::Selected,
    ]);
    $secretive->viewers()->sync([$chosen->id]);

    $copy = importFrom($campaign);

    $imported = Entity::withoutGlobalScopes()->where('campaign_id', $copy->id)->sole();

    expect($imported->visibility)->toBe(Visibility::Dm)
        ->and($imported->viewers()->count())->toBe(0);
});

it('leaves the dice log and the images behind', function () {
    $this->seed(DemoCampaignSeeder::class);

    $source = Campaign::query()->firstOrFail();
    $copy = importFrom($source);

    expect(DB::table('dice_rolls')->where('campaign_id', $source->id)->count())->toBeGreaterThan(0)
        ->and(DB::table('dice_rolls')->where('campaign_id', $copy->id)->count())->toBe(0);

    $withImages = Entity::withoutGlobalScopes()->where('campaign_id', $copy->id)->get()
        ->filter(fn (Entity $entity) => $entity->getMedia('image')->isNotEmpty() || $entity->getMedia('files')->isNotEmpty());

    expect($withImages)->toBeEmpty();
});

it('rebuilds the tags by name', function () {
    $campaign = Campaign::factory()->create();
    $entity = Entity::factory()->for($campaign)->create(['name' => 'The Salt Cathedral', 'slug' => 'salt-cathedral']);
    app(SyncTags::class)->handle($entity, ['coastal', 'ruin']);

    $copy = importFrom($campaign);

    $imported = Entity::withoutGlobalScopes()->where('campaign_id', $copy->id)->sole();

    expect($imported->tags->pluck('name')->sort()->values()->all())->toBe(['coastal', 'ruin'])
        // Tags are campaign-scoped rows, so the copy gets its own pair rather than
        // sharing the source's.
        ->and(Tag::withoutGlobalScopes()->where('campaign_id', $copy->id)->count())->toBe(2)
        ->and(Tag::withoutGlobalScopes()->where('campaign_id', $campaign->id)->count())->toBe(2);
});

it('resolves the wiki links after the import', function () {
    $campaign = Campaign::factory()->create();

    // The page that links is written before the page it links to, which is the order
    // that would break a naive import.
    Entity::factory()->for($campaign)->create([
        'name' => 'Harbour lore', 'slug' => 'harbour-lore', 'body' => 'See [[The Salt Cathedral]].',
    ]);
    Entity::factory()->for($campaign)->type(EntityType::Location)->create([
        'name' => 'The Salt Cathedral', 'slug' => 'salt-cathedral',
    ]);

    $copy = importFrom($campaign);

    $cathedral = Entity::withoutGlobalScopes()->where('campaign_id', $copy->id)->where('name', 'The Salt Cathedral')->sole();
    $lore = Entity::withoutGlobalScopes()->where('campaign_id', $copy->id)->where('name', 'Harbour lore')->sole();

    $mention = DB::table('mentions')
        ->where('campaign_id', $copy->id)
        ->where('source_id', $lore->id)
        ->first();

    expect($mention)->not->toBeNull()
        ->and($mention->target_entity_id)->toBe($cathedral->id);
});

it('writes nothing at all when the import fails part way', function () {
    $campaign = Campaign::factory()->create(['name' => 'The Drowned Duchy']);
    Entity::factory()->for($campaign)->count(3)->create();

    $document = exportedDocument($campaign);

    // A clock pointing at an entity the writer will never have. The reader would have
    // caught this; the test drives the writer directly to prove the transaction.
    $document['clocks'][] = [
        'id' => 'c-broken', 'entity_id' => 'e-nowhere', 'name' => 'The ritual',
        'segments' => 6, 'filled' => 0, 'player_visible' => false, 'position' => 0,
    ];

    $before = Campaign::query()->count();

    expect(fn () => app(ImportCampaign::class)->handle($document, User::factory()->create()))
        ->toThrow(RuntimeException::class);

    expect(Campaign::query()->count())->toBe($before)
        ->and(Entity::withoutGlobalScopes()->count())->toBe(3);
});

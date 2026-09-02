<?php

use App\Actions\Entities\SyncTags;
use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\Tag;

it('keeps tag namespaces separate per campaign', function () {
    $a = Entity::factory()->create();
    $b = Entity::factory()->create();

    app(SyncTags::class)->handle($a, ['Council']);
    app(SyncTags::class)->handle($b, ['Council']);

    expect(Tag::withoutGlobalScopes()->where('slug', 'council')->count())->toBe(2)
        ->and($a->tags()->first()->campaign_id)->toBe($a->campaign_id)
        ->and($b->tags()->first()->campaign_id)->toBe($b->campaign_id);
});

it('reuses an existing tag in the same campaign and removes orphans', function () {
    $campaign = Campaign::factory()->create();
    $one = Entity::factory()->for($campaign)->create();
    $two = Entity::factory()->for($campaign)->create();

    app(SyncTags::class)->handle($one, ['Ally', 'Harbor']);
    app(SyncTags::class)->handle($two, ['ally']);
    app(SyncTags::class)->handle($one, []);

    expect(Tag::withoutGlobalScopes()->where('campaign_id', $campaign->id)->pluck('slug')->all())->toBe(['ally'])
        ->and($two->tags()->count())->toBe(1);
});

it('filters the index by tag and shows counts the viewer is allowed to see', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $visible = Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Mara Voss']);
    $hidden = Entity::factory()->for($campaign)->dmOnly()->create(['name' => 'The Duke']);
    $untagged = Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Abbess Corvane']);
    app(SyncTags::class)->handle($visible, ['villain']);
    app(SyncTags::class)->handle($hidden, ['villain']);

    $this->actingAs($player)
        ->get(route('entities.index', [$campaign, 'characters', 'tag' => 'villain']))
        ->assertSee('Mara Voss')
        ->assertDontSee('The Duke')
        ->assertDontSee('Abbess Corvane')
        ->assertSeeInOrder(['villain', '1']);

    $this->actingAs(ownerOf($campaign))
        ->get(route('entities.index', [$campaign, 'characters']))
        ->assertSeeInOrder(['villain', '2']);
});

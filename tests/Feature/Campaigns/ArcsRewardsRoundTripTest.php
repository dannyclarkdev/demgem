<?php

use App\Actions\Campaigns\ImportCampaign;
use App\Actions\Campaigns\ReadCampaignFile;
use App\Enums\EntityType;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\User;

/**
 * RoundTripTest drops arc_id with the other remapped ids, so this is what holds the
 * link: after an import, the copied quest and session point at the copied arc, and
 * the reward columns come across untouched.
 */
it('remaps the arc on a quest and a session, and keeps the reward', function () {
    $campaign = Campaign::factory()->create();
    $arc = Entity::factory()->for($campaign)->arc()->forPlayers()->create(['name' => 'The Drowned Duke']);
    Entity::factory()->for($campaign)->quest()->inArc($arc)->create(['name' => 'The Ledger']);
    GameSession::factory()->for($campaign)->number(4)->played()->inArc($arc)->rewarded(450, 'Reached the Drowned Court')->create();

    $importer = User::factory()->create();
    $document = app(ReadCampaignFile::class)->handle(json_encode(exportedArray($campaign), JSON_THROW_ON_ERROR))->document;
    $copy = app(ImportCampaign::class)->handle($document, $importer);

    $newArc = Entity::withoutGlobalScopes()->where('campaign_id', $copy->id)->where('type', EntityType::Arc)->firstOrFail();
    $newQuest = Entity::withoutGlobalScopes()->where('campaign_id', $copy->id)->where('name', 'The Ledger')->firstOrFail();
    $newSession = GameSession::withoutGlobalScopes()->where('campaign_id', $copy->id)->where('number', 4)->firstOrFail();

    expect($newArc->id)->not->toBe($arc->id)
        ->and($newQuest->arc_id)->toBe($newArc->id)
        ->and($newSession->arc_id)->toBe($newArc->id)
        ->and($newSession->xp_awarded)->toBe(450)
        ->and($newSession->milestone)->toBe('Reached the Drowned Court');
});

it('refuses a file whose quest names an arc it does not carry', function () {
    $campaign = Campaign::factory()->create();
    $arc = Entity::factory()->for($campaign)->arc()->create();
    Entity::factory()->for($campaign)->quest()->inArc($arc)->create(['name' => 'The Ledger']);

    $document = exportedArray($campaign);
    $document['entities'] = array_values(array_filter($document['entities'], fn (array $row) => $row['type'] !== 'arc'));

    $result = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));

    expect($result->succeeded())->toBeFalse()
        ->and(implode(' ', $result->errors))->toContain('arc');
});

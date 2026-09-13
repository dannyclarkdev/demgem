<?php

use App\Actions\Campaigns\ImportCampaign;
use App\Actions\Campaigns\ReadCampaignFile;
use App\Actions\Campaigns\WriteCampaignMarkdown;
use App\Actions\Downtime\RecordDowntime;
use App\Enums\CampaignRole;
use App\Livewire\Downtime\Index;
use App\Livewire\Downtime\Log;
use App\Livewire\Entities\Show as EntityShow;
use App\Livewire\Sessions\Show as SessionShow;
use App\Models\Calendar;
use App\Models\Campaign;
use App\Models\DowntimeActivity;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\User;
use App\Support\Reckoning\GameDate;
use Livewire\Livewire;

/**
 * A party of two, one session the party may see and one it may not, and a GM-only
 * character. Wren is Tobin's; Halder is Mira's. The leak tests read the log from
 * Tobin's seat and expect Wren's rows, Halder's rows, and nothing of the abbess.
 */
function aPartyWithDowntime(): array
{
    $campaign = Campaign::factory()->create();
    $tobin = memberOf($campaign, CampaignRole::Player);
    $mira = memberOf($campaign, CampaignRole::Player);

    $wren = Entity::factory()->for($campaign)->pcOf($tobin)->forPlayers()->create(['name' => 'Wren Ashgrove', 'slug' => 'wren-ashgrove']);
    $halder = Entity::factory()->for($campaign)->pcOf($mira)->forPlayers()->create(['name' => 'Halder Bream', 'slug' => 'halder-bream']);
    $abbess = Entity::factory()->for($campaign)->dmOnly()->create(['name' => 'Abbess Corvane', 'slug' => 'abbess-corvane']);

    $open = GameSession::factory()->for($campaign)->number(1)->played()->create(['title' => 'The Harbor Fire']);
    $draft = GameSession::factory()->for($campaign)->number(2)->hidden()->create(['title' => 'The Secret Night']);

    $forge = DowntimeActivity::factory()->forCharacter($wren)->around($open)->days(5)->create(['activity' => 'Forged the harbor papers']);
    $carouse = DowntimeActivity::factory()->forCharacter($halder)->around($draft)->days(3)->create(['activity' => 'Drank the Tidewardens under the table']);
    $plot = DowntimeActivity::factory()->forCharacter($abbess)->days(12)->create(['activity' => 'Wrote to the Duke']);

    return compact('campaign', 'tobin', 'mira', 'wren', 'halder', 'abbess', 'open', 'draft', 'forge', 'carouse', 'plot');
}

it('shows a player the rows of the characters they may see, with a session link only when they may see the session', function () {
    ['campaign' => $campaign, 'tobin' => $tobin] = aPartyWithDowntime();

    $component = Livewire::actingAs($tobin)
        ->test(Log::class, ['campaign' => $campaign])
        ->assertSee('Forged the harbor papers')
        ->assertSee('The Harbor Fire')
        ->assertSee('Drank the Tidewardens under the table')
        ->assertDontSee('The Secret Night')
        ->assertDontSee('Wrote to the Duke');

    expect(json_encode($component->snapshot))->not->toContain('Wrote to the Duke')
        ->not->toContain('The Secret Night');
});

it('sums the days on every read, per character and for the campaign', function () {
    ['campaign' => $campaign, 'tobin' => $tobin, 'wren' => $wren] = aPartyWithDowntime();

    DowntimeActivity::factory()->forCharacter($wren)->days(2)->create(['activity' => 'Rested']);

    Livewire::actingAs($tobin)
        ->test(Log::class, ['campaign' => $campaign, 'character' => $wren])
        ->assertSee('7 days')
        ->assertDontSee('Drank the Tidewardens');

    // The abbess's twelve days are not in a player's total, because her rows never
    // reached the query.
    Livewire::actingAs($tobin)
        ->test(Index::class, ['campaign' => $campaign])
        ->assertSee('Wren Ashgrove')
        ->assertSee('Halder Bream')
        ->assertDontSee('Abbess Corvane')
        ->assertSeeInOrder(['Wren Ashgrove', '7 days'])
        ->assertSeeInOrder(['Halder Bream', '3 days']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign])
        ->assertSeeInOrder(['Abbess Corvane', '12 days']);
});

it('lets a player record, edit, and delete downtime on their own character', function () {
    ['campaign' => $campaign, 'tobin' => $tobin, 'wren' => $wren, 'open' => $open] = aPartyWithDowntime();

    $component = Livewire::actingAs($tobin)
        ->test(Log::class, ['campaign' => $campaign, 'character' => $wren])
        ->set('newActivity', 'Trained with the Tidewardens')
        ->set('newDays', '4')
        ->set('newNotes', 'Mara Voss took the coin and taught the [[Tidewardens]] salute.')
        ->set('newSessionId', $open->id)
        ->call('record')
        ->assertHasNoErrors()
        ->assertSee('Trained with the Tidewardens')
        ->assertSee('9 days');

    $row = DowntimeActivity::query()->where('activity', 'Trained with the Tidewardens')->firstOrFail();

    expect($row->entity_id)->toBe($wren->id)
        ->and($row->days)->toBe(4)
        ->and($row->game_session_id)->toBe($open->id)
        ->and($row->created_by)->toBe($tobin->id);

    $component->call('edit', $row->id)
        ->set('editingDays', '6')
        ->set('editingActivity', 'Trained with the Tidewardens, twice')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('11 days');

    expect($row->fresh()->days)->toBe(6)
        ->and($row->fresh()->activity)->toBe('Trained with the Tidewardens, twice');

    $component->call('delete', $row->id)->assertSee('5 days');

    expect(DowntimeActivity::query()->whereKey($row->id)->exists())->toBeFalse();
});

it('keeps a player off another character, and lets a GM write on anyone', function () {
    ['campaign' => $campaign, 'tobin' => $tobin, 'halder' => $halder, 'abbess' => $abbess, 'carouse' => $carouse] = aPartyWithDowntime();

    Livewire::actingAs($tobin)
        ->test(Log::class, ['campaign' => $campaign, 'character' => $halder])
        ->assertDontSee('Record it')
        ->set('newActivity', 'Stole the boat')
        ->set('newDays', '1')
        ->call('record')
        ->assertForbidden();

    Livewire::actingAs($tobin)
        ->test(Log::class, ['campaign' => $campaign])
        ->call('delete', $carouse->id)
        ->assertForbidden();

    Livewire::actingAs($tobin)
        ->test(Log::class, ['campaign' => $campaign])
        ->call('edit', $carouse->id)
        ->assertForbidden();

    // The abbess is GM-only, so from a player's seat she is a 404 rather than a 403.
    Livewire::actingAs($tobin)
        ->test(Log::class, ['campaign' => $campaign])
        ->set('newCharacterId', $abbess->id)
        ->set('newActivity', 'Stole the boat')
        ->set('newDays', '1')
        ->call('record')
        ->assertHasErrors('newCharacterId');

    Livewire::actingAs(ownerOf($campaign))
        ->test(Log::class, ['campaign' => $campaign, 'character' => $halder])
        ->set('newActivity', 'Carried the tide charts to the abbey')
        ->set('newDays', '2')
        ->call('record')
        ->assertHasNoErrors();

    expect(DowntimeActivity::query()->where('entity_id', $halder->id)->count())->toBe(2)
        ->and(DowntimeActivity::query()->where('activity', 'Stole the boat')->exists())->toBeFalse();
});

it('offers a player only their own characters in the picker, and a GM every one', function () {
    ['campaign' => $campaign, 'tobin' => $tobin] = aPartyWithDowntime();

    expect(Livewire::actingAs($tobin)->test(Log::class, ['campaign' => $campaign])->viewData('characterOptions')->pluck('name')->all())
        ->toBe(['Wren Ashgrove'])
        ->and(Livewire::actingAs(ownerOf($campaign))->test(Log::class, ['campaign' => $campaign])->viewData('characterOptions')->pluck('name')->all())
        ->toBe(['Abbess Corvane', 'Halder Bream', 'Wren Ashgrove']);
});

it('refuses an empty activity and a day count outside the bounds', function () {
    ['campaign' => $campaign, 'tobin' => $tobin, 'wren' => $wren] = aPartyWithDowntime();

    Livewire::actingAs($tobin)
        ->test(Log::class, ['campaign' => $campaign, 'character' => $wren])
        ->set('newActivity', '')
        ->set('newDays', '1000')
        ->call('record')
        ->assertHasErrors(['newActivity', 'newDays']);

    expect(DowntimeActivity::query()->where('entity_id', $wren->id)->count())->toBe(1);
});

it('takes a start date checked against the calendar, and prints the range it covers', function () {
    ['campaign' => $campaign, 'tobin' => $tobin, 'wren' => $wren] = aPartyWithDowntime();

    // Harvest has 20 days in the factory's calendar.
    Calendar::factory()->inCampaign($campaign)->create();

    $component = Livewire::actingAs($tobin)
        ->test(Log::class, ['campaign' => $campaign, 'character' => $wren])
        ->set('newActivity', 'Walked to the abbey')
        ->set('newDays', '5')
        ->set('newStartsOn', ['year' => '1042', 'month' => '2', 'day' => '18'])
        ->call('record')
        ->assertHasNoErrors()
        ->assertSee('18 Harvest 1042 AR to 2 Frost 1042 AR');

    $row = DowntimeActivity::query()->where('activity', 'Walked to the abbey')->firstOrFail();

    expect($row->starts_on)->toEqual(new GameDate(1042, 2, 18));

    $component
        ->set('newActivity', 'Fell off the calendar')
        ->set('newDays', '1')
        ->set('newStartsOn', ['year' => '1042', 'month' => '2', 'day' => '25'])
        ->call('record')
        ->assertHasErrors('newStartsOn.day');

    // A single day, or none, prints the one date.
    $component
        ->set('newActivity', 'Slept')
        ->set('newDays', '0')
        ->set('newStartsOn', ['year' => '1042', 'month' => '1', 'day' => '3'])
        ->call('record')
        ->assertHasNoErrors()
        ->assertSee('3 Thaw 1042 AR')
        ->assertDontSee('3 Thaw 1042 AR to');
});

it('offers no date without a calendar, and prints days alone', function () {
    ['campaign' => $campaign, 'tobin' => $tobin, 'wren' => $wren] = aPartyWithDowntime();

    Livewire::actingAs($tobin)
        ->test(Log::class, ['campaign' => $campaign, 'character' => $wren])
        ->assertDontSee('Starting on')
        ->assertSee('5 days');
});

it('sits on the character page for a PC, on the session page, and in the sidebar', function () {
    ['campaign' => $campaign, 'tobin' => $tobin, 'wren' => $wren, 'abbess' => $abbess, 'open' => $open] = aPartyWithDowntime();
    $gm = ownerOf($campaign);

    Livewire::actingAs($tobin)
        ->test(EntityShow::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => $wren->slug])
        ->assertSee('Downtime')
        ->assertSee('Forged the harbor papers')
        ->assertSeeLivewire(Log::class);

    // An NPC with rows carries the card for the GM; an NPC with none does not.
    Livewire::actingAs($gm)
        ->test(EntityShow::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => $abbess->slug])
        ->assertSee('Wrote to the Duke');

    $quiet = Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Mara Voss', 'slug' => 'mara-voss']);

    Livewire::actingAs($gm)
        ->test(EntityShow::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => $quiet->slug])
        ->assertDontSeeLivewire(Log::class);

    Livewire::actingAs($tobin)
        ->test(SessionShow::class, ['campaign' => $campaign, 'number' => $open->number])
        ->assertSee('Forged the harbor papers')
        ->assertDontSee('Drank the Tidewardens');

    $this->actingAs($tobin)->get(route('downtime.index', $campaign))
        ->assertOk()
        ->assertSee('Forged the harbor papers');

    $this->actingAs($tobin)->get(route('campaigns.show', $campaign))
        ->assertSee(route('downtime.index', $campaign), false);

    $this->actingAs(User::factory()->create())->get(route('downtime.index', $campaign))->assertNotFound();
});

it('travels in the export, comes back remapped, and lands in the vault', function () {
    ['campaign' => $campaign, 'wren' => $wren, 'open' => $open] = aPartyWithDowntime();

    Calendar::factory()->inCampaign($campaign)->create();

    app(RecordDowntime::class)->handle($campaign, ownerOf($campaign), $wren, [
        'activity' => 'Walked to the abbey',
        'days' => 5,
        'notes' => 'Along the sea wall.',
        'session' => $open,
        'starts_on' => new GameDate(1042, 2, 18),
    ]);

    $document = exportedArray($campaign);

    expect($document['downtime'])->toHaveCount(4);

    $walk = collect($document['downtime'])->firstWhere('activity', 'Walked to the abbey');

    expect($walk['entity_id'])->toBe($wren->id)
        ->and($walk['game_session_id'])->toBe($open->id)
        ->and($walk['days'])->toBe(5)
        ->and($walk['starts_on'])->toBe(['year' => 1042, 'month' => 2, 'day' => 18]);

    $read = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));

    expect($read->succeeded())->toBeTrue();

    $copy = app(ImportCampaign::class)->handle($read->document, User::factory()->create());

    $newWren = Entity::withoutGlobalScopes()->where('campaign_id', $copy->id)->where('slug', 'wren-ashgrove')->firstOrFail();
    $newOpen = GameSession::withoutGlobalScopes()->where('campaign_id', $copy->id)->where('number', 1)->firstOrFail();
    $copied = DowntimeActivity::withoutGlobalScopes()->where('campaign_id', $copy->id)->where('activity', 'Walked to the abbey')->firstOrFail();

    expect(DowntimeActivity::withoutGlobalScopes()->where('campaign_id', $copy->id)->count())->toBe(4)
        ->and($copied->entity_id)->toBe($newWren->id)
        ->and($copied->game_session_id)->toBe($newOpen->id)
        ->and($copied->days)->toBe(5)
        ->and($copied->notes)->toBe('Along the sea wall.')
        ->and($copied->starts_on)->toEqual(new GameDate(1042, 2, 18));

    $vault = app(WriteCampaignMarkdown::class)->handle($campaign)['markdown/characters/wren-ashgrove.md'];

    expect($vault)->toContain('## Downtime')
        ->toContain('- Forged the harbor papers, 5 days *(Session 1)*')
        ->toContain('- Walked to the abbey, 5 days, 18 Harvest 1042 AR to 2 Frost 1042 AR *(Session 1)*')
        ->toContain('  Along the sea wall.');
});

it('refuses a file whose downtime points at a character it does not carry', function () {
    ['campaign' => $campaign] = aPartyWithDowntime();

    $document = exportedArray($campaign);
    $document['downtime'][0]['entity_id'] = '01JNOTINTHISFILE0000000000';

    $read = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));

    expect($read->succeeded())->toBeFalse()
        ->and(implode(' ', $read->errors))->toContain('downtime');
});

it('reads an older file with no downtime section', function () {
    ['campaign' => $campaign] = aPartyWithDowntime();

    $document = exportedArray($campaign);
    unset($document['downtime']);

    $read = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));

    expect($read->succeeded())->toBeTrue()
        ->and($read->document['downtime'])->toBe([]);
});

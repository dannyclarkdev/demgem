<?php

use App\Actions\Campaigns\ImportCampaign;
use App\Actions\Campaigns\ReadCampaignFile;
use App\Actions\Campaigns\WriteCampaignMarkdown;
use App\Actions\Characters\AdjustHitPoints;
use App\Actions\Characters\LongRest;
use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Enums\Ruleset;
use App\Livewire\Characters\Sheet;
use App\Livewire\Entities\Show as EntityShow;
use App\Models\Campaign;
use App\Models\CharacterSheet;
use App\Models\Entity;
use App\Models\LedgerEntry;
use App\Models\User;
use Livewire\Livewire;

/**
 * A party of two on the SRD 5.2.1 rules, and one NPC. Wren is Tobin's rogue, Halder
 * is Mira's cleric. The sheet is written from Tobin's seat on Wren, refused on
 * Halder, and read by both.
 */
function aTableOnFifthEdition(): array
{
    $campaign = Campaign::factory()->create(['ruleset' => Ruleset::Srd5e2024]);
    $tobin = memberOf($campaign, CampaignRole::Player);
    $mira = memberOf($campaign, CampaignRole::Player);

    $wren = Entity::factory()->for($campaign)->pcOf($tobin)->forPlayers()->withRecord('Rogue', 5, null)
        ->create(['name' => 'Wren Ashgrove', 'slug' => 'wren-ashgrove']);
    $halder = Entity::factory()->for($campaign)->pcOf($mira)->forPlayers()->withRecord('Cleric', 5, null)
        ->create(['name' => 'Halder Bream', 'slug' => 'halder-bream']);
    $abbess = Entity::factory()->for($campaign)->type(EntityType::Character)->forPlayers()
        ->create(['name' => 'Abbess Corvane', 'slug' => 'abbess-corvane']);

    return compact('campaign', 'tobin', 'mira', 'wren', 'halder', 'abbess');
}

function sheetOf(Entity $character, User $viewer)
{
    return Livewire::actingAs($viewer)->test(Sheet::class, ['campaign' => $character->campaign, 'character' => $character]);
}

it('offers no sheet on a system-agnostic campaign', function () {
    $campaign = Campaign::factory()->create(['ruleset' => Ruleset::Generic]);
    $player = memberOf($campaign, CampaignRole::Player);
    $pc = Entity::factory()->for($campaign)->pcOf($player)->forPlayers()->withRecord('Rogue', 5, null)->create(['name' => 'Wren Ashgrove', 'slug' => 'wren-ashgrove']);

    Livewire::actingAs($player)
        ->test(EntityShow::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => $pc->slug])
        ->assertOk()
        ->assertDontSee('Character sheet')
        ->assertDontSeeLivewire(Sheet::class);

    Livewire::actingAs($player)
        ->test(Sheet::class, ['campaign' => $campaign, 'character' => $pc])
        ->assertNotFound();
});

it('lets a player write their own sheet, and reads the maths from it', function () {
    ['campaign' => $campaign, 'tobin' => $tobin, 'wren' => $wren] = aTableOnFifthEdition();

    Livewire::actingAs($tobin)
        ->test(EntityShow::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => $wren->slug])
        ->assertSee('Character sheet')
        ->assertSeeLivewire(Sheet::class);

    $component = sheetOf($wren, $tobin)
        ->assertSee('No sheet yet')
        ->call('edit')
        ->set('scores', ['str' => 10, 'dex' => 17, 'con' => 14, 'int' => 12, 'wis' => 14, 'cha' => 8])
        ->set('savingThrows', ['dex', 'int'])
        ->set('skills', ['stealth', 'perception', 'sleight_of_hand'])
        ->set('expertise', ['stealth'])
        ->set('hpMax', '38')
        ->set('hpCurrent', '38')
        ->set('hpTemp', '0')
        ->set('hitDie', '8')
        ->set('hitDiceSpent', '0')
        ->set('armorClass', '15')
        ->set('speed', '30')
        ->call('save')
        ->assertHasNoErrors()
        // Dexterity 17 is +3; Stealth with expertise at level 5 is +3 +6; Perception
        // with proficiency is +2 +3; the Dexterity save is +3 +3; passive Perception
        // is 10 and the Perception bonus; initiative is the Dexterity modifier.
        ->assertSeeInOrder(['Dexterity', '17', '+3'])
        ->assertSeeInOrder(['Stealth', '+9'])
        ->assertSeeInOrder(['Perception', '+5'])
        ->assertSee('Passive Perception')
        ->assertSee('15')
        ->assertSee('5d8')
        ->assertSee('System Reference Document');

    $sheet = CharacterSheet::query()->where('entity_id', $wren->id)->firstOrFail();

    expect($sheet->dexterity)->toBe(17)
        ->and($sheet->skills)->toBe(['stealth', 'perception', 'sleight_of_hand'])
        ->and($sheet->expertise)->toBe(['stealth'])
        ->and($sheet->saving_throws)->toBe(['dex', 'int'])
        ->and($sheet->hp_max)->toBe(38)
        ->and($sheet->hit_die)->toBe(8)
        ->and($sheet->skillBonus('stealth'))->toBe(9)
        ->and($sheet->skillBonus('athletics'))->toBe(0)
        ->and($sheet->saveBonus('dex'))->toBe(6)
        ->and($sheet->saveBonus('str'))->toBe(0)
        ->and($sheet->passivePerception())->toBe(15)
        ->and($sheet->initiative())->toBe(3)
        ->and($sheet->proficiencyBonus())->toBe(3);

    // A second save replaces the row rather than adding one.
    $component->call('edit')->set('hpMax', '40')->call('save')->assertHasNoErrors();

    expect(CharacterSheet::query()->where('entity_id', $wren->id)->count())->toBe(1)
        ->and($sheet->fresh()->hp_max)->toBe(40);
});

it('keeps a player off another character, and lets a GM write on anyone', function () {
    ['campaign' => $campaign, 'tobin' => $tobin, 'halder' => $halder, 'abbess' => $abbess] = aTableOnFifthEdition();

    sheetOf($halder, $tobin)
        ->assertDontSee('Edit sheet')
        ->call('edit')
        ->assertForbidden();

    sheetOf($halder, $tobin)->call('save')->assertForbidden();
    sheetOf($halder, $tobin)->call('damage', 5)->assertForbidden();
    sheetOf($halder, $tobin)->call('longRest')->assertForbidden();

    sheetOf($abbess, ownerOf($campaign))
        ->call('edit')
        ->set('scores', ['str' => 8, 'dex' => 12, 'con' => 12, 'int' => 16, 'wis' => 18, 'cha' => 15])
        ->set('hpMax', '27')
        ->set('hpCurrent', '27')
        ->set('hitDie', '8')
        ->call('save')
        ->assertHasNoErrors();

    expect(CharacterSheet::query()->where('entity_id', $abbess->id)->exists())->toBeTrue()
        ->and(CharacterSheet::query()->where('entity_id', $halder->id)->exists())->toBeFalse();
});

it('takes damage off temporary hit points first, and heals no higher than the maximum', function () {
    ['tobin' => $tobin, 'wren' => $wren] = aTableOnFifthEdition();

    $sheet = CharacterSheet::factory()->forCharacter($wren)->create(['hp_max' => 38, 'hp_current' => 38, 'hp_temp' => 5]);

    $component = sheetOf($wren, $tobin)->set('damageAmount', '12')->call('damage')->assertHasNoErrors();

    expect($sheet->fresh()->hp_temp)->toBe(0)
        ->and($sheet->fresh()->hp_current)->toBe(31);

    $component->set('damageAmount', '40')->call('damage');

    expect($sheet->fresh()->hp_current)->toBe(0);

    $component->set('healAmount', '10')->call('heal');

    expect($sheet->fresh()->hp_current)->toBe(10);

    $component->set('healAmount', '100')->call('heal');

    expect($sheet->fresh()->hp_current)->toBe(38);

    $component->set('damageAmount', '0')->call('damage')->assertHasErrors('damageAmount');

    app(AdjustHitPoints::class)->damage($sheet->fresh(), 3);

    expect($sheet->fresh()->hp_current)->toBe(35);
});

it('makes the sheet whole on a long rest', function () {
    ['tobin' => $tobin, 'wren' => $wren] = aTableOnFifthEdition();

    $sheet = CharacterSheet::factory()->forCharacter($wren)->create([
        'hp_max' => 38,
        'hp_current' => 10,
        'hp_temp' => 3,
        'hit_dice_spent' => 4,
        'spell_slots' => [1 => ['total' => 4, 'used' => 3], 2 => ['total' => 2, 'used' => 2]],
    ]);

    sheetOf($wren, $tobin)->call('longRest')->assertHasNoErrors();

    $rested = $sheet->fresh();

    expect($rested->hp_current)->toBe(38)
        ->and($rested->hp_temp)->toBe(0)
        // Half of five hit dice, rounded down: two come back.
        ->and($rested->hit_dice_spent)->toBe(2)
        ->and($rested->spell_slots)->toBe([1 => ['total' => 4, 'used' => 0], 2 => ['total' => 2, 'used' => 0]]);

    app(LongRest::class)->handle($rested);

    expect($rested->fresh()->hit_dice_spent)->toBe(0);
});

it('refuses a score outside the bounds, a hit die the rules do not have, and a skill it does not know', function () {
    ['tobin' => $tobin, 'wren' => $wren] = aTableOnFifthEdition();

    sheetOf($wren, $tobin)
        ->call('edit')
        ->set('scores', ['str' => 31, 'dex' => 0, 'con' => 14, 'int' => 12, 'wis' => 14, 'cha' => 8])
        ->set('hitDie', '7')
        ->set('skills', ['stealth', 'lockpicking'])
        ->set('hpMax', '38')
        ->set('hpCurrent', '50')
        ->call('save')
        ->assertHasErrors(['scores.str', 'scores.dex', 'hitDie', 'skills.1', 'hpCurrent']);

    expect(CharacterSheet::query()->where('entity_id', $wren->id)->exists())->toBeFalse();
});

it('shows the party pack on the sheet, and is read by whoever may read the character', function () {
    ['campaign' => $campaign, 'mira' => $mira, 'wren' => $wren] = aTableOnFifthEdition();

    CharacterSheet::factory()->forCharacter($wren)->create(['dexterity' => 17]);
    LedgerEntry::factory()->inCampaign($campaign)->item('Tidewarden Signet', 1)->create();

    sheetOf($wren, $mira)
        ->assertSee('Tidewarden Signet')
        ->assertSeeInOrder(['Dexterity', '17'])
        ->assertDontSee('Edit sheet')
        ->assertDontSee('Long rest');
});

it('travels nested under its character, lands in the vault, and shows in the API', function () {
    ['campaign' => $campaign, 'tobin' => $tobin, 'wren' => $wren] = aTableOnFifthEdition();

    CharacterSheet::factory()->forCharacter($wren)->create([
        'dexterity' => 17,
        'skills' => ['stealth'],
        'expertise' => ['stealth'],
        'hp_max' => 38,
        'hp_current' => 20,
        'spell_slots' => [1 => ['total' => 2, 'used' => 1]],
    ]);

    $document = exportedArray($campaign);
    $wrenRow = collect($document['entities'])->firstWhere('slug', 'wren-ashgrove');
    $halderRow = collect($document['entities'])->firstWhere('slug', 'halder-bream');

    expect($wrenRow['sheet']['dexterity'])->toBe(17)
        ->and($wrenRow['sheet']['skills'])->toBe(['stealth'])
        ->and($wrenRow['sheet']['spell_slots'])->toBe(['1' => ['total' => 2, 'used' => 1]])
        ->and($halderRow['sheet'])->toBeNull();

    $read = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));

    expect($read->succeeded())->toBeTrue();

    $copy = app(ImportCampaign::class)->handle($read->document, User::factory()->create());
    $newWren = Entity::withoutGlobalScopes()->where('campaign_id', $copy->id)->where('slug', 'wren-ashgrove')->firstOrFail();
    $copied = CharacterSheet::withoutGlobalScopes()->where('entity_id', $newWren->id)->firstOrFail();

    expect($copied->dexterity)->toBe(17)
        ->and($copied->expertise)->toBe(['stealth'])
        ->and($copied->hp_current)->toBe(20)
        ->and($copied->spell_slots)->toBe([1 => ['total' => 2, 'used' => 1]])
        ->and(CharacterSheet::withoutGlobalScopes()->where('campaign_id', $copy->id)->count())->toBe(1);

    $vault = app(WriteCampaignMarkdown::class)->handle($campaign)['markdown/characters/wren-ashgrove.md'];

    expect($vault)->toContain('## Character sheet')
        ->toContain('Dexterity 17 (+3)')
        ->toContain('Hit points: 20 of 38')
        ->toContain('Stealth +9');

    asKey($tobin)
        ->getJson("/api/v1/campaigns/{$campaign->id}/entities/{$wren->id}")
        ->assertOk()
        ->assertJsonPath('data.sheet.scores.dex', 17)
        ->assertJsonPath('data.sheet.skills.stealth', 9)
        ->assertJsonPath('data.sheet.hit_points.current', 20)
        ->assertJsonPath('data.sheet.proficiency_bonus', 3);
});

it('refuses a file whose sheet carries a score outside the bounds', function () {
    ['campaign' => $campaign, 'wren' => $wren] = aTableOnFifthEdition();

    CharacterSheet::factory()->forCharacter($wren)->create();

    $document = exportedArray($campaign);
    $document['entities'] = array_map(function (array $row): array {
        if ($row['slug'] === 'wren-ashgrove') {
            $row['sheet']['dexterity'] = 40;
        }

        return $row;
    }, $document['entities']);

    $read = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));

    expect($read->succeeded())->toBeFalse()
        ->and(implode(' ', $read->errors))->toContain('sheet');
});

it('reads an older file whose characters carry no sheet', function () {
    ['campaign' => $campaign] = aTableOnFifthEdition();

    $document = exportedArray($campaign);
    $document['entities'] = array_map(function (array $row): array {
        unset($row['sheet']);

        return $row;
    }, $document['entities']);

    $read = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));

    expect($read->succeeded())->toBeTrue()
        ->and(collect($read->document['entities'])->pluck('sheet')->unique()->all())->toBe([null]);
});

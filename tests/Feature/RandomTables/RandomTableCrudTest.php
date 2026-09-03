<?php

use App\Enums\CampaignRole;
use App\Livewire\RandomTables\Index;
use App\Livewire\RandomTables\Roller;
use App\Livewire\RandomTables\Show;
use App\Models\Campaign;
use App\Models\RandomTable;
use App\Models\RandomTableEntry;
use Livewire\Livewire;

it('creates a table and goes straight to it', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign])
        ->set('newName', 'Tavern rumours')
        ->call('create')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(RandomTable::query()->sole()->name)->toBe('Tavern rumours');
});

it('refuses two tables with the same name in one campaign', function () {
    $campaign = Campaign::factory()->create();
    RandomTable::factory()->for($campaign)->create(['name' => 'Tavern rumours']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign])
        ->set('newName', 'Tavern rumours')
        ->call('create')
        ->assertHasErrors('newName');

    expect(RandomTable::query()->count())->toBe(1);
});

it('allows the same table name in a different campaign', function () {
    $campaign = Campaign::factory()->create();
    $other = Campaign::factory()->create();
    RandomTable::factory()->for($other)->create(['name' => 'Tavern rumours']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign])
        ->set('newName', 'Tavern rumours')
        ->call('create')
        ->assertHasNoErrors();

    expect(RandomTable::query()->withoutGlobalScopes()->count())->toBe(2);
});

it('adds, edits, and removes a row', function () {
    $campaign = Campaign::factory()->create();
    $table = RandomTable::factory()->for($campaign)->create();

    $component = Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'tableId' => $table->id])
        ->set('newBody', 'A caravan is late')
        ->set('newWeight', 5)
        ->call('addEntry')
        ->assertHasNoErrors()
        ->assertSet('newBody', '')
        ->assertSet('newWeight', 1);

    $entry = $table->entries()->sole();

    expect($entry->body)->toBe('A caravan is late')
        ->and($entry->weight)->toBe(5)
        ->and($entry->position)->toBe(0);

    $component->call('edit', $entry->id)
        ->set('editingBody', 'A caravan never arrived')
        ->set('editingWeight', 2)
        ->call('saveEntry')
        ->assertSet('editingId', null);

    expect($entry->refresh()->body)->toBe('A caravan never arrived')
        ->and($entry->weight)->toBe(2);

    $component->call('removeEntry', $entry->id);

    expect($table->entries()->count())->toBe(0);
});

it('caps the weight and requires a body', function () {
    $campaign = Campaign::factory()->create();
    $table = RandomTable::factory()->for($campaign)->create();

    $component = Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'tableId' => $table->id]);

    $component->set('newBody', '')->set('newWeight', 1)->call('addEntry')->assertHasErrors('newBody');
    $component->set('newBody', 'Fine')->set('newWeight', 0)->call('addEntry')->assertHasErrors('newWeight');
    $component->set('newBody', 'Fine')->set('newWeight', RandomTableEntry::MAX_WEIGHT + 1)->call('addEntry')->assertHasErrors('newWeight');

    expect($table->entries()->count())->toBe(0);
});

it('derives the range and the die from the weights', function () {
    $campaign = Campaign::factory()->create();
    $table = RandomTable::factory()->for($campaign)->create();

    $first = RandomTableEntry::factory()->inTable($table, 0)->weighing(5)->create();
    $second = RandomTableEntry::factory()->inTable($table, 1)->weighing(15)->create();
    $third = RandomTableEntry::factory()->inTable($table, 2)->weighing(80)->create();

    $ranges = $table->ranges();

    expect($table->totalWeight())->toBe(100)
        ->and($table->dieLabel())->toBe('d100')
        ->and($ranges[$first->id])->toBe(['from' => 1, 'to' => 5])
        ->and($ranges[$second->id])->toBe(['from' => 6, 'to' => 20])
        ->and($ranges[$third->id])->toBe(['from' => 21, 'to' => 100]);
});

it('reorders rows and reshuffles the ranges with them', function () {
    $campaign = Campaign::factory()->create();
    $table = RandomTable::factory()->for($campaign)->create();

    $a = RandomTableEntry::factory()->inTable($table, 0)->weighing(5)->create(['body' => 'A']);
    $b = RandomTableEntry::factory()->inTable($table, 1)->weighing(15)->create(['body' => 'B']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'tableId' => $table->id])
        ->call('reorder', $b->id, 0);

    $ranges = $table->ranges();

    expect($table->entries()->pluck('body')->all())->toBe(['B', 'A'])
        ->and($ranges[$b->id])->toBe(['from' => 1, 'to' => 15])
        ->and($ranges[$a->id])->toBe(['from' => 16, 'to' => 20]);
});

it('refuses a table that nests itself', function () {
    $campaign = Campaign::factory()->create();
    $table = RandomTable::factory()->for($campaign)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'tableId' => $table->id])
        ->set('newBody', 'Loop')
        ->set('newNestedTableId', $table->id)
        ->call('addEntry')
        ->assertHasErrors('newNestedTableId');

    expect($table->entries()->count())->toBe(0);
});

it('refuses a nested table from another campaign', function () {
    $campaign = Campaign::factory()->create();
    $stranger = RandomTable::factory()->create();
    $table = RandomTable::factory()->for($campaign)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'tableId' => $table->id])
        ->set('newBody', 'Elsewhere')
        ->set('newNestedTableId', $stranger->id)
        ->call('addEntry')
        ->assertHasErrors('newNestedTableId');
});

it('deletes a table, its rows, and any reference to it', function () {
    $campaign = Campaign::factory()->create();
    $names = RandomTable::factory()->for($campaign)->withEntries(['Kell'])->create(['name' => 'Names']);
    $rumours = RandomTable::factory()->for($campaign)->create(['name' => 'Rumours']);
    $entry = RandomTableEntry::factory()->inTable($rumours)->nesting($names)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign])
        ->call('delete', $names->id);

    // The nesting entry degrades to plain text rather than pointing at a ghost.
    expect(RandomTable::query()->count())->toBe(1)
        ->and(RandomTableEntry::query()->count())->toBe(1)
        ->and($entry->refresh()->nested_table_id)->toBeNull();
});

it('renames a table', function () {
    $campaign = Campaign::factory()->create();
    $table = RandomTable::factory()->for($campaign)->create(['name' => 'Old']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'tableId' => $table->id])
        ->set('name', 'Tavern rumours')
        ->set('description', 'What the regulars are saying.')
        ->call('save')
        ->assertHasNoErrors();

    expect($table->refresh()->name)->toBe('Tavern rumours')
        ->and($table->description)->toBe('What the regulars are saying.');
});

it('rolls from the drawer and keeps a short history', function () {
    $campaign = Campaign::factory()->create();
    $table = RandomTable::factory()->for($campaign)->withEntries(['A caravan is late'])->create(['name' => 'Rumours']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Roller::class, ['campaign' => $campaign])
        ->assertSee('Rumours')
        ->call('roll', $table->id)
        ->assertSee('A caravan is late')
        ->call('clearHistory')
        ->assertSet('history', []);
});

it('is closed to players and spectators everywhere', function (CampaignRole $role) {
    $campaign = Campaign::factory()->create();
    $member = memberOf($campaign, $role);
    $table = RandomTable::factory()->for($campaign)->withEntries(['A caravan is late'])->create();

    Livewire::actingAs($member)->test(Index::class, ['campaign' => $campaign])->assertForbidden();
    Livewire::actingAs($member)->test(Roller::class, ['campaign' => $campaign])->assertForbidden();

    $this->actingAs($member)->get(route('tables.index', $campaign))->assertForbidden();

    $this->actingAs($member)
        ->get(route('tables.show', [$campaign, $table->id]))
        ->assertNotFound()
        ->assertDontSee('A caravan is late');
})->with([CampaignRole::Player, CampaignRole::Spectator]);

it('404s a table from another campaign', function () {
    $campaign = Campaign::factory()->create();
    $stranger = RandomTable::factory()->create();

    $this->actingAs(ownerOf($campaign))
        ->get(route('tables.show', [$campaign, $stranger->id]))
        ->assertNotFound();
});

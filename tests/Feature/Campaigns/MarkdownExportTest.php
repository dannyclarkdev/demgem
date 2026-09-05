<?php

use App\Actions\Campaigns\BuildCampaignArchive;
use App\Actions\Campaigns\WriteCampaignMarkdown;
use App\Actions\Entities\SyncTags;
use App\Enums\EntityType;
use App\Enums\QuestStatus;
use App\Enums\Visibility;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

it('writes one file per page, foldered by type', function () {
    $campaign = Campaign::factory()->create();

    Entity::factory()->for($campaign)->type(EntityType::Location)->forPlayers()
        ->create(['name' => 'The Salt Cathedral', 'slug' => 'salt-cathedral']);
    Entity::factory()->for($campaign)->type(EntityType::Character)->forPlayers()
        ->create(['name' => 'Wren', 'slug' => 'wren']);

    $files = app(WriteCampaignMarkdown::class)->handle($campaign);

    expect(array_keys($files))->toContain('markdown/locations/salt-cathedral.md')
        ->and(array_keys($files))->toContain('markdown/characters/wren.md');
});

it('quotes every front matter value, whatever is in it', function () {
    $campaign = Campaign::factory()->create();

    $awkward = 'The Duke: "Drowned" #1, or so they say';

    Entity::factory()->for($campaign)->forPlayers()
        ->create(['name' => $awkward, 'slug' => 'the-duke', 'body' => 'Prose.']);

    $file = app(WriteCampaignMarkdown::class)->handle($campaign)['markdown/characters/the-duke.md'];

    // Quoting unconditionally is a rule rather than a judgement, so a colon, a hash
    // and a quote in one name have nothing to get wrong.
    expect($file)->toContain('name: "The Duke: \"Drowned\" #1, or so they say"')
        ->and($file)->toStartWith("---\n");
});

it('leaves the wiki links exactly as they were written', function () {
    $campaign = Campaign::factory()->create();

    Entity::factory()->for($campaign)->forPlayers()->create([
        'name' => 'Harbour lore', 'slug' => 'harbour-lore',
        'body' => 'Ask at [[The Salt Cathedral]] and mention [[Wren]].',
    ]);

    $file = app(WriteCampaignMarkdown::class)->handle($campaign)['markdown/characters/harbour-lore.md'];

    // Obsidian reads the same syntax, which is the whole reason this folder opens as
    // a vault and the links work.
    expect($file)->toContain('[[The Salt Cathedral]]')
        ->and($file)->toContain('[[Wren]]');
});

it('puts the GM notes under their own heading', function () {
    $campaign = Campaign::factory()->create();

    Entity::factory()->for($campaign)->withDmNotes('They are not what they seem.')
        ->create(['name' => 'The Duke', 'slug' => 'the-duke', 'body' => 'A tall man.']);

    $file = app(WriteCampaignMarkdown::class)->handle($campaign)['markdown/characters/the-duke.md'];

    expect($file)->toContain('A tall man.')
        ->and($file)->toContain("## GM notes\n\nThey are not what they seem.");
});

it('writes a quest with its objectives as a checklist', function () {
    $campaign = Campaign::factory()->create();

    Entity::factory()->for($campaign)->quest(QuestStatus::Active)->forPlayers()->withObjectives(3, 1)
        ->create(['name' => 'The Toll Bridge', 'slug' => 'toll-bridge']);

    $file = app(WriteCampaignMarkdown::class)->handle($campaign)['markdown/quests/toll-bridge.md'];

    expect($file)->toContain('status: "active"')
        ->and($file)->toContain('## Objectives')
        ->and(substr_count($file, '- [ ]'))->toBe(2)
        ->and(substr_count($file, '- [x]'))->toBe(1);
});

it('numbers the session files so a folder listing is in play order', function () {
    $campaign = Campaign::factory()->create();

    GameSession::factory()->for($campaign)->number(1)->played()->create(['title' => 'The Harbor Fire']);
    GameSession::factory()->for($campaign)->number(2)->planned()->create(['title' => 'The Cellar']);

    $files = array_keys(app(WriteCampaignMarkdown::class)->handle($campaign));

    expect($files)->toContain('markdown/sessions/01-the-harbor-fire.md')
        ->and($files)->toContain('markdown/sessions/02-the-cellar.md');
});

it('carries the tags into the front matter as a list', function () {
    $campaign = Campaign::factory()->create();

    $entity = Entity::factory()->for($campaign)->forPlayers()
        ->create(['name' => 'The Salt Cathedral', 'slug' => 'salt-cathedral']);

    app(SyncTags::class)->handle($entity, ['coastal', 'ruin']);

    $file = app(WriteCampaignMarkdown::class)->handle($campaign->fresh())['markdown/characters/salt-cathedral.md'];

    expect($file)->toContain('tags: ["coastal", "ruin"]');
});

it('lands in the archive beside the document and the media', function () {
    $campaign = aCampaignWithPictures();

    $entries = array_keys(archiveEntries(app(BuildCampaignArchive::class)->handle($campaign)));

    expect($entries)->toContain('campaign.json')
        ->and($entries)->toContain('media/0001-the-duchy-of-vell.png')
        ->and(collect($entries)->filter(fn (string $n) => str_starts_with($n, 'markdown/')))->not->toBeEmpty();
});

it('says in the readme that the markdown is a copy', function () {
    $campaign = Campaign::factory()->create();

    $readme = archiveEntries(app(BuildCampaignArchive::class)->handle($campaign))['README.md'];

    expect($readme)->toContain('a copy, not the source')
        ->and($readme)->toContain('Obsidian');
});

it('marks a GM-only page as such in its front matter', function () {
    $campaign = Campaign::factory()->create();

    Entity::factory()->for($campaign)->dmOnly()->create(['name' => 'The Informant', 'slug' => 'informant']);

    $file = app(WriteCampaignMarkdown::class)->handle($campaign)['markdown/characters/informant.md'];

    // The Markdown is the GM's own export, and the JSON already carries this. A copy
    // that quietly dropped half the campaign would be worse than one that says which
    // half is which.
    expect($file)->toContain('visibility: "'.Visibility::Dm->value.'"');
});

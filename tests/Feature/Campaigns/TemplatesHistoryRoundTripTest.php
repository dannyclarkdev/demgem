<?php

use App\Actions\Campaigns\BuildCampaignArchive;
use App\Actions\Campaigns\ImportCampaign;
use App\Actions\Campaigns\ReadCampaignArchive;
use App\Actions\Campaigns\ReadCampaignFile;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\EntityBodyRevision;
use App\Models\EntityTemplate;
use App\Models\User;

it('carries templates and exact historical bodies through two JSON round trips', function () {
    $campaign = Campaign::factory()->create();
    $entity = Entity::factory()->for($campaign)->create(['body' => "    Current code\n"]);
    $template = EntityTemplate::factory()->for($campaign)->create(['body' => "## Outline\n\n"]);
    EntityBodyRevision::factory()->for($entity)->create([
        'body' => "    Old code\n\n", 'recorded_at' => '2026-09-01 12:00:00', 'replaced_by_name' => 'Former GM',
    ]);
    EntityBodyRevision::factory()->for($entity)->create([
        'body' => null, 'recorded_at' => '2026-09-02 12:00:00', 'replaced_by_name' => null,
    ]);
    $before = exportedArray($campaign);
    $importer = User::factory()->create();
    $current = $campaign;
    for ($trip = 0; $trip < 2; $trip++) {
        $read = app(ReadCampaignFile::class)->handle(json_encode(exportedArray($current), JSON_THROW_ON_ERROR));
        expect($read->errors)->toBe([]);
        expect($read->report->counts['entity_body_revisions'])->toBe(2);
        $current = app(ImportCampaign::class)->handle($read->document, $importer);
        $after = exportedArray($current);
        expect($after['entity_templates'][0]['body'])->toBe("## Outline\n\n");
        expect($after['entities'][0]['body'])->toBe("    Current code\n");
        expect($after['entity_templates'][0]['id'])->not->toBe($template->id);
        expect($after['entity_body_revisions'])->toHaveCount(2);
        foreach ($after['entity_body_revisions'] as $index => $row) {
            expect($row['body'])->toBe($before['entity_body_revisions'][$index]['body']);
            expect($row['recorded_at'])->toBe($before['entity_body_revisions'][$index]['recorded_at']);
            expect($row['replaced_by_name'])->toBe($before['entity_body_revisions'][$index]['replaced_by_name']);
            expect($row['id'])->not->toBe($before['entity_body_revisions'][$index]['id']);
            expect($row['entity_id'])->toBe($after['entities'][0]['id']);
        }
        expect(EntityBodyRevision::withoutGlobalScopes()->where('campaign_id', $current->id)->whereNotNull('replaced_by')->count())->toBe(0);
    }
});

it('carries templates and history in archive JSON while Markdown describes the current world', function () {
    $campaign = Campaign::factory()->create();
    $entity = Entity::factory()->for($campaign)->create(['body' => 'Current world prose']);
    EntityTemplate::factory()->for($campaign)->create(['body' => 'Template-only prompt']);
    EntityBodyRevision::factory()->for($entity)->create(['body' => 'History-only secret']);
    $archive = app(BuildCampaignArchive::class)->handle($campaign);
    $entries = archiveEntries($archive);
    $markdown = implode("\n", array_filter($entries, fn (string $key) => str_ends_with($key, '.md'), ARRAY_FILTER_USE_KEY));
    expect($markdown)->toContain('Current world prose')->not->toContain('History-only secret')->not->toContain('Template-only prompt');
    $read = app(ReadCampaignArchive::class)->handle($archive);
    expect($read->succeeded())->toBeTrue();
    $copy = app(ImportCampaign::class)->handle($read->read->document, User::factory()->create(), $read->restored);
    $document = exportedArray($copy);
    expect($document['entity_templates'][0]['body'])->toBe('Template-only prompt');
    expect($document['entity_body_revisions'][0]['body'])->toBe('History-only secret');
});

it('imports old documents without manufacturing history and omits deleted entity history', function () {
    $campaign = Campaign::factory()->create();
    $entity = Entity::factory()->for($campaign)->create();
    EntityBodyRevision::factory()->for($entity)->create(['body' => 'Deleted secret']);
    $before = exportedArray($campaign);
    unset($before['entity_templates'], $before['entity_body_revisions']);
    $read = app(ReadCampaignFile::class)->handle(json_encode($before, JSON_THROW_ON_ERROR));
    expect($read->errors)->toBe([]);
    $copy = app(ImportCampaign::class)->handle($read->document, User::factory()->create());
    expect(exportedArray($copy)['entity_body_revisions'])->toBe([]);
    $entity->delete();
    expect(exportedArray($campaign)['entity_body_revisions'])->toBe([]);
});

it('refuses invalid history and templates before writing any campaign', function (string $section, string $field, mixed $value) {
    $campaign = Campaign::factory()->create();
    $entity = Entity::factory()->for($campaign)->create();
    EntityTemplate::factory()->for($campaign)->create();
    EntityBodyRevision::factory()->for($entity)->create();
    $document = exportedArray($campaign);
    $document[$section][0][$field] = $value;
    $read = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));
    expect($read->succeeded())->toBeFalse();
    expect($read->document)->toBe([]);
    expect(Campaign::query()->count())->toBe(1);
})->with([
    ['entity_templates', 'type', 'dragon'],
    ['entity_templates', 'body', str_repeat('t', 100001)],
    ['entity_body_revisions', 'body', str_repeat('h', 100001)],
    ['entity_body_revisions', 'recorded_at', 'not a date'],
    ['entity_body_revisions', 'entity_id', 'missing-entity'],
]);

it('refuses oversized history with an honest message about both import entry points', function () {
    $read = app(ReadCampaignFile::class)->handle(str_repeat(' ', ReadCampaignFile::MAX_BYTES + 1));
    expect($read->succeeded())->toBeFalse();
    expect($read->errors[0])->toContain('Both the browser and artisan importer have this limit.');
});

<?php

use App\Actions\Campaigns\ExportCampaign;
use Illuminate\Support\Facades\Schema;

/**
 * The failure mode of an export is silence: somebody adds a table, nobody adds it to
 * the export, and a year later a GM's data leaves without it. These two tests read
 * the schema instead of trusting anybody to remember.
 */
function documentedTables(): array
{
    return array_merge(
        array_keys(ExportCampaign::SECTION_TABLES),
        array_keys(ExportCampaign::NESTED_TABLES),
        array_keys(ExportCampaign::EXCLUDED_TABLES),
    );
}

it('exports, nests, or documents every campaign-scoped table', function () {
    $documented = documentedTables();

    $campaignScoped = collect(Schema::getTableListing())
        ->map(fn (string $table) => str_contains($table, '.') ? (string) last(explode('.', $table)) : $table)
        ->filter(fn (string $table) => Schema::hasColumn($table, 'campaign_id'))
        ->values();

    expect($campaignScoped)->not->toBeEmpty();

    // A new table here means a decision to make in ExportCampaign: give it a section,
    // nest it inside one, or write down why it stays behind.
    expect($campaignScoped->reject(fn (string $table) => in_array($table, $documented, true))->all())->toBe([]);
});

it('documents no table that has left the schema', function () {
    $tables = collect(Schema::getTableListing())
        ->map(fn (string $table) => str_contains($table, '.') ? (string) last(explode('.', $table)) : $table)
        ->all();

    expect(collect(documentedTables())->reject(fn (string $table) => in_array($table, $tables, true))->all())->toBe([]);
});

it('gives every excluded table a reason', function () {
    foreach (ExportCampaign::EXCLUDED_TABLES as $table => $reason) {
        expect($reason)->toBeString()->not->toBe('');
    }

    expect(ExportCampaign::EXCLUDED_TABLES)->not->toBeEmpty();
});

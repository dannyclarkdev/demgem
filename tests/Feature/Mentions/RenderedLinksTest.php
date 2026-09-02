<?php

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Markdown\MarkdownRenderer;
use App\Markdown\WikiLink\WikiLinkRenderer;
use App\Models\Campaign;
use App\Models\Entity;

it('renders a link for a visible target and uses the label', function () {
    $campaign = Campaign::factory()->create();
    $target = Entity::factory()->for($campaign)->type(EntityType::Location)->forPlayers()->create(['name' => 'Harrowgate', 'slug' => 'harrowgate']);
    $player = memberOf($campaign, CampaignRole::Player);

    $html = app(MarkdownRenderer::class)->render('Go to [[Harrowgate|the capital]].', WikiLinkRenderer::for($campaign, $player, CampaignRole::Player));

    expect($html)->toContain('href="'.$target->url().'"')
        ->toContain('>the capital</a>')
        ->toContain('class="wiki-link"');
});

it('renders plain text for a hidden target and for a missing target when the viewer is a player', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->dmOnly()->create(['name' => 'The Duke']);
    $player = memberOf($campaign, CampaignRole::Player);

    $html = app(MarkdownRenderer::class)->render('[[The Duke]] and [[Nobody Yet]].', WikiLinkRenderer::for($campaign, $player, CampaignRole::Player));

    expect($html)->not->toContain('href=')
        ->toContain('<span class="wiki-link wiki-link--plain">The Duke</span>')
        ->toContain('<span class="wiki-link wiki-link--plain">Nobody Yet</span>');
});

it('renders a create prompt for a missing target when the viewer is a DM', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);
    $renderer = WikiLinkRenderer::for($campaign, $owner, CampaignRole::Owner);

    $bare = app(MarkdownRenderer::class)->render('[[Nobody Yet]]', $renderer);
    $typed = app(MarkdownRenderer::class)->render('[[location:Nowhere]]', $renderer);

    expect($bare)->toContain('wiki-link--missing')
        ->toContain(route('entities.create', [$campaign, 'characters', 'name' => 'Nobody Yet']))
        ->toContain(route('entities.create', [$campaign, 'notes', 'name' => 'Nobody Yet']))
        ->and($typed)->toContain('href="'.route('entities.create', [$campaign, 'locations', 'name' => 'Nowhere']).'"')
        ->toContain('Create this Location');
});

it('escapes link text and never emits raw html from a label', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Mara Voss']);

    $html = app(MarkdownRenderer::class)->render('[[Mara Voss|<script>alert(1)</script>]] [[<b>x</b>]]', WikiLinkRenderer::for($campaign, ownerOf($campaign), CampaignRole::Owner));

    expect($html)->not->toContain('<script>')->not->toContain('<b>');
});

it('picks the highest priority type for an ambiguous bare name and honors a prefix', function () {
    $campaign = Campaign::factory()->create();
    $item = Entity::factory()->for($campaign)->type(EntityType::Item)->forPlayers()->create(['name' => 'Raven', 'slug' => 'raven-item']);
    $character = Entity::factory()->for($campaign)->type(EntityType::Character)->forPlayers()->create(['name' => 'Raven', 'slug' => 'raven']);
    $renderer = WikiLinkRenderer::for($campaign, ownerOf($campaign), CampaignRole::Owner);

    $html = app(MarkdownRenderer::class)->render('[[Raven]] [[item:Raven]] [[items:Raven]]', $renderer);

    expect(substr_count($html, 'href="'.$character->url().'"'))->toBe(1)
        ->and(substr_count($html, 'href="'.$item->url().'"'))->toBe(2);
});

it('leaves links untouched when no wiki link renderer is given', function () {
    expect(app(MarkdownRenderer::class)->render('[[Raven]]'))->toContain('[[Raven]]');
});

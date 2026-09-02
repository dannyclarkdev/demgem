<?php

use App\Enums\EntityType;
use App\Markdown\WikiLinkScanner;

it('parses every link form', function () {
    $tokens = (new WikiLinkScanner)->scan('See [[Mara Voss]], [[Vell|the duchy]], [[item:Ember Throne]], and [[Locations:Harrowgate|the capital]].');

    expect($tokens)->toHaveCount(4)
        ->and($tokens[0]->name)->toBe('Mara Voss')->and($tokens[0]->type)->toBeNull()->and($tokens[0]->label)->toBeNull()
        ->and($tokens[1]->name)->toBe('Vell')->and($tokens[1]->label)->toBe('the duchy')
        ->and($tokens[2]->name)->toBe('Ember Throne')->and($tokens[2]->type)->toBe(EntityType::Item)
        ->and($tokens[3]->name)->toBe('Harrowgate')->and($tokens[3]->type)->toBe(EntityType::Location)->and($tokens[3]->label)->toBe('the capital');
});

it('keeps an unknown prefix as part of the name', function () {
    $tokens = (new WikiLinkScanner)->scan('[[Chapter: The Fall]] and [[re:Vell]]');

    expect($tokens[0]->name)->toBe('Chapter: The Fall')->and($tokens[0]->type)->toBeNull()
        ->and($tokens[1]->name)->toBe('re:Vell')->and($tokens[1]->type)->toBeNull();
});

it('ignores malformed and multi-line brackets', function () {
    $tokens = (new WikiLinkScanner)->scan("[[]] [[ ]] [[a\nb]] [not a link] [[ok]]");

    expect(array_map(fn ($t) => $t->name, $tokens))->toBe(['ok']);
});

it('returns nothing for empty input', function () {
    expect((new WikiLinkScanner)->scan(null))->toBe([])
        ->and((new WikiLinkScanner)->scan(''))->toBe([]);
});

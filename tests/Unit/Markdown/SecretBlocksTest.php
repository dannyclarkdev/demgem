<?php

use App\Markdown\Secrets\SecretBlocks;

it('strips a fence and its contents and leaves the rest', function () {
    $text = "Calm. Never blinks.\n\n:::secret\nShe serves [[The Drowned Duke]].\n:::\n\nShe preaches at dawn.";

    expect(SecretBlocks::strip($text))->toBe("Calm. Never blinks.\n\nShe preaches at dawn.")
        ->and(SecretBlocks::contains($text))->toBeTrue();
});

it('strips an unclosed fence to the end of the text', function () {
    $text = "Calm.\n\n:::secret\nShe serves the Duke.\nAnd more.";

    expect(SecretBlocks::strip($text))->toBe('Calm.');
});

it('strips more than one fence', function () {
    $text = ":::secret\nOne.\n:::\nOpen.\n:::secret\nTwo.\n:::\nAlso open.";

    expect(SecretBlocks::strip($text))->toBe("Open.\n\nAlso open.");
});

it('leaves text with no fence exactly as it was, and null as null', function () {
    $text = "A ::: on its own line is not a fence.\n\n:::\n\nStill not.";

    expect(SecretBlocks::strip($text))->toBe($text)
        ->and(SecretBlocks::strip(null))->toBeNull()
        ->and(SecretBlocks::contains($text))->toBeFalse();
});

it('does not treat an indented or inline marker as a fence', function () {
    $text = "Say :::secret in prose.\n\n    :::secret\n    code\n    :::";

    expect(SecretBlocks::contains($text))->toBeFalse();
});

it('returns only the fenced contents for the mention scanner', function () {
    $text = "Open [[Vell]].\n\n:::secret\nHidden [[The Duke]].\n:::\n\n:::secret\nAlso [[Wren]].\n:::";

    expect(SecretBlocks::only($text))->toBe("Hidden [[The Duke]].\n\nAlso [[Wren]].")
        ->and(SecretBlocks::only('No fence.'))->toBeNull();
});

it('puts the stored fences back after an edit that never saw them', function () {
    $stored = "Calm.\n\n:::secret\nShe serves the Duke.\n:::\n\nShe preaches.";
    $edited = "Calm, and tall.\n\nShe preaches at dawn.";

    $merged = SecretBlocks::merge($edited, $stored);

    expect($merged)->toBe("Calm, and tall.\n\nShe preaches at dawn.\n\n:::secret\nShe serves the Duke.\n:::")
        ->and(SecretBlocks::strip($merged))->toBe($edited)
        ->and(SecretBlocks::merge('New text.', 'Old text, no fence.'))->toBe('New text.')
        ->and(SecretBlocks::merge('', $stored))->toBe(":::secret\nShe serves the Duke.\n:::");
});

it('closes an unclosed stored fence when it puts it back', function () {
    $stored = "Calm.\n\n:::secret\nShe serves the Duke.";

    expect(SecretBlocks::merge('Calm, and tall.', $stored))->toBe("Calm, and tall.\n\n:::secret\nShe serves the Duke.\n:::");
});

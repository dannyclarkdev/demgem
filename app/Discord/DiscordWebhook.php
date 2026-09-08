<?php

namespace App\Discord;

/**
 * The one place "the server only ever talks to Discord" is spelled.
 *
 * A URL is accepted when it is https, on discord.com or discordapp.com, and under
 * /api/webhooks/{id}/{token}, with nothing else in it. Anything else is refused
 * before it is stored, so the job never has to decide, and the metadata address a
 * cloud host answers on is a string that never becomes a request. This is how the
 * slice keeps the SSRF refusal the importer made in slice 8 without a blocklist.
 */
final class DiscordWebhook
{
    /** @var list<string> */
    public const HOSTS = ['discord.com', 'discordapp.com'];

    private function __construct(public readonly string $url) {}

    public static function tryFrom(string $url): ?self
    {
        $url = trim($url);
        $parts = parse_url($url);

        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https') {
            return null;
        }

        if (! in_array(strtolower($parts['host'] ?? ''), self::HOSTS, true)) {
            return null;
        }

        // discord.com@evil.example parses with discord.com as the user. Nothing Discord
        // hands out carries a user, a port, a query, or a fragment, so none is allowed.
        foreach (['user', 'pass', 'port', 'query', 'fragment'] as $part) {
            if (isset($parts[$part])) {
                return null;
            }
        }

        if (preg_match('#^/api/webhooks/\d+/[A-Za-z0-9_-]+$#', $parts['path'] ?? '') !== 1) {
            return null;
        }

        return new self($url);
    }

    public static function isValid(string $url): bool
    {
        return self::tryFrom($url) !== null;
    }
}

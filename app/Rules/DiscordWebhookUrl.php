<?php

namespace App\Rules;

use App\Discord\DiscordWebhook;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * DiscordWebhook's rule, for the settings form.
 */
class DiscordWebhookUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! DiscordWebhook::isValid($value)) {
            $fail('Paste the webhook URL Discord gives you. It starts with https://discord.com/api/webhooks/.');
        }
    }
}

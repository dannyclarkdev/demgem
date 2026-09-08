---
paths:
  - 'app/Discord/**'
---

# Discord

## The server posts to Discord and to nothing else; DiscordWebhook spells the host rule once
DiscordWebhook::tryFrom() accepts https, discord.com or discordapp.com, and /api/webhooks/{id}/{token} with no user, port, query, or fragment. Nothing else becomes a request. The rule (App\Rules\DiscordWebhookUrl) and the job (App\Jobs\PostToDiscord) both go through it, and the job checks again at send time because a row edited by hand is a row. This is how slice 13 kept slice 8's SSRF refusal without a blocklist, and why there are no generic webhooks: a second destination is a change to this class with its own allow-list, never a field that takes any URL.

campaigns.discord_webhook_url is an `encrypted` cast and is never exported; it is a credential like an invite token.

Every post is names and links, never prose: the channel's members are not the campaign's. PublishRecap posts only when recap_published_at was null before the write; SendSessionReminders posts inside the same reminder_sent_at stamp as the mail. The job retries an outage (connection error or 5xx) three times and a refusal (4xx) once, then logs and finishes; nothing about Discord reaches a GM's screen.

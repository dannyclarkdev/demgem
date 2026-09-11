---
paths:
  - 'app/Actions/Auth/**, app/Http/Controllers/Auth/**'
---

# Auth

## The Discord callback has one order of outcomes, and an unverified email never links
`SignInWithDiscord::handle()` decides in this order and no other: a `social_accounts` row for the Discord id signs that user in; a signed-in user (linking from the profile) gets the row; a user whose email matches is linked and signed in only when Discord's raw payload says `verified: true`, else refused with the notice to log in with the password and link from the profile; otherwise a new user with a random 40-character password. An unverified email linking to an existing account is the account-takeover path, so the check is defensive: no raw payload reads as unverified. Only `identify` and `email` scopes; never guilds. The avatar is kept only from `https://cdn.discordapp.com/`. Both routes 404 while `services.discord.client_id` or `client_secret` is empty, and `DiscordAuthController::isConfigured()` is the one place that asks. `social_accounts` is a person table like `users`: never exported. Tests swap `Socialite::driver('discord')` for a mock whose `user()` returns a `Laravel\Socialite\Two\User` with `setRaw([... 'verified' => ...])`; re-mocking the facade in one test does not work, so one outcome per test.

---
title: "feat: Continue with Discord, and the account it links to"
type: feat
date: 2026-09-11
status: implemented
brainstorm: docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md
follows: docs/plans/2026-09-11-feat-built-in-generators-plan.md
---

# feat: Continue with Discord, and the account it links to

## Overview

The brainstorm's auth row names Fortify, Socialite for Discord and Google, and 2FA. Slice 1 built Fortify. This slice adds the first Socialite provider, Discord, because a table lives on Discord and a player who is handed an invite link should not have to invent a password to read the recap.

| Feature | What it adds |
|---|---|
| Continue with Discord | A button on the login and register pages, shown when the install has a Discord app configured. It signs a returning user in, links a verified email to an existing account, or makes a new account. |
| Connected accounts | A card on the profile: link Discord to an account made with a password, or unlink it. |
| A linked-account table | `social_accounts`: one row per provider per user, with the provider's id and the name and avatar it gave. |
| An invite that survives the round trip | A player who opens an invite link, is sent to log in, and continues with Discord lands on the invite. |

When this slice is done a GM pastes an invite link into the party's Discord channel. A player taps it on their phone, sees "Continue with Discord", taps that, approves demgem in Discord, and is on the invite page with their Discord name already filled in.

**On scope.** Phase 0 is the dependency, the table, and the callback. Phase 1 is the buttons and the profile card. Phase 2 is the rules, the README, and the pass.

## Problem Statement

**The first thing a player does in demgem is invent a password.** The invite link takes them to a registration form. For a player who was handed the link in Discord, the app is asking for a name and a password the day before they have any reason to want an account. Half of them use the same password as everything else; the other half forget it.

**The table already has an identity.** A campaign's Discord server is where the players are. Their name there is the name the GM knows them by. The webhook, slice 13, posts into that server; the login should come from it.

**Two accounts for one person is the failure to avoid.** A GM who registered with a password in slice 1 and continues with Discord in slice 22 must land in the same account, or their campaigns split. Discord says whether an email is verified, and only a verified one is trusted to link. An unverified one is refused with a sentence that says what to do instead.

## Proposed Solution

**One callback, three outcomes, in that order.** The Discord callback receives an id, an email, a verified flag, a name, and an avatar. First: a `social_accounts` row for that Discord id logs its user in. Second: a signed-in user, who came from the profile card, gets the row linked to their account. Third: a user whose email matches gets the row linked and is logged in, when Discord says the email is verified; when it does not, the callback refuses and sends them to the login page with a notice. Last: nobody matches, and a new user is made with the Discord name, the email, a random password they never see, and the row.

**The password stays required and stays random.** `users.password` is not nullable. A user made through Discord gets a random 40-character password, which they can replace through the reset link if they ever want to log in without Discord. Nothing about Fortify changes.

**A button is shown only when the install can honour it.** `services.discord.client_id` empty means no button, no route error, and no "Continue with Discord" that goes nowhere. The redirect and callback routes 404 in that state.

**The invite survives.** The auth middleware stores the intended URL before sending a guest to the login page. The callback ends in `redirect()->intended()`, so the invite page is where a new player lands.

## Technical Approach

### The dependency

`laravel/socialite` and `socialiteproviders/discord`, approved for this slice. Discord is not a first-party Socialite provider; the second package adds it through the `SocialiteWasCalled` event, registered in `AppServiceProvider`.

### What slices 1 to 21 give us for free

| Piece | Reuse |
|---|---|
| Fortify's login and register views | Where the button lands. |
| `CreateNewUser` | The shape of a user made outside the form; this slice writes its own action beside it. |
| `Profile\Edit` and its API keys card | The profile card pattern. |
| `InviteController` and the auth middleware's intended URL | The invite round trip, unchanged. |
| `.ai/rules/discord.md` | The host rule for the webhook. Socialite's Discord provider talks to `discord.com` only, and nothing here takes a URL from a user. |

### The data

```
social_accounts
  id            ulid
  user_id       foreignId -> users, cascade
  provider      string(20)     'discord'
  provider_id   string(64)     Discord's snowflake
  name          string(120) nullable    the name Discord gave
  avatar_url    string(500) nullable
  created_at, updated_at
  unique (provider, provider_id)
  unique (user_id, provider)
```

### Scope and decisions

| Question | Decision |
|---|---|
| Which providers | Discord only. The code keys on a provider string so a second one is a config entry, a button, and a test, not a rewrite. |
| Scopes | `identify` and `email`. Nothing about guilds; demgem never reads a server's member list. |
| A Discord email that matches an existing user | Linked and logged in when Discord says verified. Refused otherwise, with a notice: sign in with your password and link Discord from your profile. |
| A Discord account with no email | Refused. demgem needs an email for the invite, the reminder, and the reset link. |
| A new user's password | Random, never shown. The reset link is the way to a password. |
| A new user's email verification | Fortify's verification is off on this install, so nothing to set. `email_verified_at` is stamped when Discord says verified, for the day verification is turned on. |
| Unlinking | Allowed always. A user with no way in but Discord who unlinks it still has the reset link, which the card says. |
| The name | A new user takes Discord's display name, or its username when there is none. Linking never renames an existing user. |
| The avatar | Stored on the row and shown on the profile card only. Nothing else in the app shows avatars from outside. |
| Rate limits | The redirect route is throttled like the invite routes. |
| CSRF | Socialite's state parameter; the callback refuses a mismatch. |
| The API | No change. |
| The export | `social_accounts` has no `campaign_id` and is a person table, like `users`. Never exported. |

### Actions

| Action | Does |
|---|---|
| `Auth\SignInWithDiscord` | The callback's three outcomes and the refusals, in one place, returning the user to log in or throwing a refusal with the notice to show. |
| `Auth\UnlinkSocialAccount` | Deletes the row. |

### Screens

- **Login and register** gain "Continue with Discord" above the form when the install is configured, and a divider.
- **The profile** gains a "Connected accounts" card: Discord linked as name and avatar with an Unlink button, or a Link button.
- **The login page** shows the refusal notice when the callback sends a user back.

### Configuration

`DISCORD_CLIENT_ID`, `DISCORD_CLIENT_SECRET`, and `DISCORD_REDIRECT_URI` in both env examples, with the README saying where to make the app in Discord's developer portal and what redirect URL to paste.

## Verification

    php artisan test --compact --filter=Discord
    php artisan test --compact tests/Feature/Auth tests/Feature/Invites tests/Feature/ProfileTest.php
    vendor/bin/pint --dirty --format agent
    vendor/bin/phpstan analyse

Then the full suite with `memory_limit=1G`. The browser pass needs a Discord app, which this machine does not have; the tests drive the callback with a faked provider instead, and the results section says so.

## Open Questions

1. **Should a Discord user's display name update on every login?** Recommendation: no. The name in demgem is the user's to edit, and a rename in Discord should not overwrite it.
2. **Should a GM be able to see who at the table signed in with Discord?** Recommendation: no. It is the user's account, not the campaign's.
3. **Google next?** Recommendation: when somebody asks. The code is ready for a second key.

## References

- `.ai/rules/discord.md` — the server posts to Discord and nothing else; this slice reads from it through Socialite, and takes no URL from a user.
- `.ai/rules/calendar.md` — the feed's token is a credential; a Discord id is an identity, and the row that holds it cascades with the user.
- `docs/plans/2026-09-08-feat-api-tokens-discord-plan.md` — the webhook, and the reason there is no bot.

## Implementation Results — 2026-09-11

Implemented in full. 10 new tests; the suite is 1388 tests, 1387 passing, 1 skipped, with Larastan clean and Pint clean.

### What shipped, against the plan

| Planned | Shipped |
|---|---|
| `laravel/socialite` and `socialiteproviders/discord` | Installed, with the Discord provider registered through `SocialiteWasCalled` in `AppServiceProvider`. |
| `social_accounts` | As planned. A person table, never exported. |
| One callback, three outcomes | `SignInWithDiscord`, in the plan's order, with `DiscordSignInRefused` carrying the notice for each refusal. A Discord account already linked to another user is a fourth refusal the plan did not list. |
| The verified check | Read from Socialite's raw payload, and defensive: no payload reads as unverified. |
| The button | One Blade component on the login and register pages, rendered only when both keys are set. The routes 404 otherwise. |
| The profile card | Link, or the name and avatar with Unlink. |
| The invite round trip | A test opens an invite as a guest, continues with Discord, and lands on the invite. |

### Deviations

None from the plan.

### Browser checks

The pass could not be driven. The browser tool failed on every read partway through this slice, after four slices of use today, and the screenshot of the login page with the button on it was never captured. Two things stand in for it:

- With the two keys in `.env`, a request for the login page carries "Continue with Discord"; without them it does not. Checked with curl against the served app.
- The tests drive every outcome of the callback with a faked provider, and the profile card's link and unlink through Livewire.

A real Discord app is needed to press the button end to end, and this machine has none. That is recorded here rather than pretended.

### For the next session

`php artisan serve` does not pass shell environment variables to the server it spawns, so a key set on the command line is not seen by the served app. Put it in `.env` for a local check.

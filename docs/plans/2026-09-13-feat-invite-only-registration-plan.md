---
title: "feat: Invite-only registration"
type: feat
date: 2026-09-13
status: implemented
brainstorm: docs/brainstorms/2026-09-02-demgem-campaign-manager-brainstorm.md
follows: docs/plans/2026-09-13-feat-character-sheet-plan.md
---

# feat: Invite-only registration

## Overview

The first production install went up today, and its owner's first question was why a stranger with the URL can make an account. Every player joins a campaign through a GM's invite link, so the register page has no honest reason to be public. This slice closes it to everyone but the person holding an invite, with one exception for the first account on a fresh install.

| Feature | What it adds |
|---|---|
| `DEMGEM_REGISTRATION` | `invite`, the default, or `open`. One env key, one config value, read in one class. |
| The gate | `Support\Auth\RegistrationGate` answers one question: may this request make an account? Yes when the mode is open, when the session holds a valid invite, or when the install has no users yet. |
| The invite page for a guest | `/invites/{token}` no longer sends a guest to log in first. It shows the campaign and the role, offers "Create an account" and "Log in", and puts the token in the session. Both doors lead back to the invite. |
| The closed register page | A 403 with a sentence: demgem is invite only, ask your GM for a link. The login page drops its "Create an account" link at the same time. |
| Discord | "Continue with Discord" for someone nobody has seen is a registration too, and it goes through the same gate. A refused sign-in says why, and nothing is written. |

When this slice is done, the owner registers the first account on an empty install, makes a campaign, and hands out invite links. A player taps one, sees "You are invited to join The Ashgrove Chronicle as a Player", presses "Create an account", registers, and lands back on the invite to press Join. Anyone who types `/register` gets a closed door.

## Problem Statement

**Registration is Fortify's stock feature, and it is public.** Anyone who finds the URL can make an account. They cannot see a campaign without an invite, so the exposure is bounded, but a public register page on a private table is still a page that takes accounts from strangers.

**The invite link already is the credential.** The routes file says so about calendar feeds and invites both: the token in the URL is the proof. Registration should trust the same proof and nothing else.

**A fresh install has nobody to send an invite.** The first account has to come from somewhere, and a bootstrap command that prints a password is worse than a register page that is open exactly once.

## Proposed Solution

**One class decides.** `RegistrationGate::allows(Request)` is the only place that reads the mode, the session, and the users table. Fortify's register view callback, `CreateNewUser`, and the Discord sign-in all ask it. Nothing else knows the rule.

**The invite page is the front door.** Today a guest who opens an invite is bounced to the login page by the auth middleware, and the invite survives only as `url.intended`. Now the show route sits outside the auth group, and for a guest it remembers the token in the session under its own key and sets `url.intended` to itself. The accept route stays behind auth. After register, login, or Discord, every path already redirects to `intended`, so the guest lands back on the invite with a Join button.

**The session holds a token, and the gate looks it up every time.** Storing the token rather than a flag means an invite revoked between the tap and the form closes the door with it. `CampaignInvite::findByToken()` and `isValid()` already exist.

**The first user is the exception, and it is a fact about the table, not a flag.** `User::query()->exists()` is false exactly once. No setup command, no env flip, nothing to forget.

## Technical Approach

### No new dependency

Fortify's view callback receives the request. `CreateNewUser` is resolved from the container, so it takes the gate and the request in its constructor. `SignInWithDiscord::handle()` gains a third parameter, `bool $mayRegister`, that the controller fills from the gate; the action throws `DiscordSignInRefused` at step 4 when it is false, before the insert.

### The data

None. One config file, `config/registration.php`, with `mode`. One session key, `registration.invite`, written by the invite page for guests and cleared on accept.

### Scope and decisions

- The default is `invite`. An install that upgrades becomes invite only, which is the safe direction and the one this slice exists for. `open` restores the old page for a public instance.
- A value that is neither `open` nor `invite` reads as `invite`.
- The closed page is a 403 with a view, not a redirect. A script that posts to `/register` without a pending invite gets a 403 and no row.
- The register page shows which campaign the invite is for, so the person knows the link did its job before they type a password.
- No CAPTCHA, no admin approval, no allow-list of email domains. The invite is the allow-list.

### Screens

- `auth/register`: an alert naming the campaign and role when an invite is pending.
- `auth/register-closed`: the 403 page.
- `auth/login`: the footer link to register shows only when the gate allows.
- `invites/show`: a guest branch with "Create an account" and "Log in".

## Verification

Feature tests in `tests/Feature/Auth/InviteOnlyRegistrationTest.php`:

- The register page is 403 when users exist and no invite is in the session, and the POST writes nothing.
- The first account on an empty install registers.
- A guest who opens an invite sees the campaign, can register, and lands on the invite.
- An expired or revoked invite in the session does not open the page.
- `open` mode keeps the page public.
- The login page hides the register link when closed.
- A Discord sign-in for a new user is refused when closed and allowed with a pending invite.

Existing tests in `AcceptInviteTest` and `DiscordLoginTest` change where a guest used to be bounced to login.

## References

- `docs/plans/2026-09-11-feat-discord-login-plan.md`, the Discord front door this slice gates.
- `.ai/rules/auth.md`, the order of outcomes in `SignInWithDiscord`.

## Implementation Results — 2026-09-13

### What shipped, against the plan

Everything in the table. The gate is 60 lines; the rest is wiring.

### Deviations

None.

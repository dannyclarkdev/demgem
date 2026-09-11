<?php

namespace App\Actions\Auth;

use RuntimeException;

/**
 * The callback would not sign this person in, and the message says what to do
 * instead. Shown on the login page, never logged as an error: it is a decision.
 */
final class DiscordSignInRefused extends RuntimeException {}

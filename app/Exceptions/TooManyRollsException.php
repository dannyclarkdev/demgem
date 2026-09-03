<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The roll limit, hit. The message is shown to whoever is holding the dice, so write
 * it for them and not for a rate limiter.
 */
class TooManyRollsException extends RuntimeException {}

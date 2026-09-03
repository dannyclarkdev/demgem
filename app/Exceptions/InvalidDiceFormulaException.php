<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The message is shown to the GM, so write it for someone holding dice, not for a
 * parser author.
 */
class InvalidDiceFormulaException extends RuntimeException {}

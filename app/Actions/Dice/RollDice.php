<?php

namespace App\Actions\Dice;

use App\Events\DiceRolled;
use App\Exceptions\InvalidDiceFormulaException;
use App\Exceptions\TooManyRollsException;
use App\Models\Campaign;
use App\Models\DiceRoll;
use App\Models\GameSession;
use App\Models\User;
use App\Support\Dice\DiceFormula;
use App\Support\Dice\DiceRoll as Result;
use App\Support\Dice\DiceRoller;
use Illuminate\Support\Facades\RateLimiter;

class RollDice
{
    /**
     * Rolls a user may log in a minute. The log is shared from this slice on, so one
     * person with a stuck key would fill the whole table's screen.
     *
     * It is per user rather than per campaign: a table of five rolling hard is a good
     * night, and one of them holding a key down is not.
     */
    public const PER_MINUTE = 30;

    public function __construct(private readonly DiceRoller $roller) {}

    /**
     * Parses, rolls, logs, and tells every screen in the campaign. The formula is
     * stored normalised, so "4 D 6 KH 3" and "4d6kh3" read the same a week later.
     *
     * The limit is checked before the parse, so a refused roll writes nothing and
     * costs nothing. The dice limits themselves stay in DiceFormula::parse().
     *
     * $private is a GM's request, never a promise: only a GM role may set it, and the
     * check lives here so every surface gets the same answer. A player's roll is never
     * private, because a roll nobody else sees is a roll they did not make.
     *
     * @throws InvalidDiceFormulaException
     * @throws TooManyRollsException
     */
    public function handle(
        Campaign $campaign,
        User $actor,
        string $formula,
        ?string $label = null,
        ?GameSession $session = null,
        bool $private = false,
    ): DiceRoll {
        $this->checkTheLimit($actor);

        $result = $this->roller->roll(DiceFormula::parse($formula));

        $roll = DiceRoll::create([
            'campaign_id' => $campaign->id,
            'game_session_id' => $session?->id,
            'user_id' => $actor->id,
            'formula' => $result->formula,
            'label' => $label !== null && trim($label) !== '' ? trim($label) : null,
            'total' => $result->total,
            'detail' => $result->toArray(),
            'private' => $private && DiceRoll::mayRollPrivately($campaign->roleFor($actor)),
        ]);

        // Even a private roll broadcasts. The event carries no result and no roller, so
        // every other screen re-renders, reads the log under its own viewer, and finds
        // nothing new. One event, one channel, no branch.
        DiceRolled::dispatch($campaign->id);

        return $roll;
    }

    /**
     * Rolls without logging. Used when one button rolls for a dozen combatants and
     * twelve log lines would be noise rather than history. Unthrottled: it is the GM's
     * one click, not a person tapping d20.
     */
    public function roll(string $formula): Result
    {
        return $this->roller->roll(DiceFormula::parse($formula));
    }

    /**
     * @throws TooManyRollsException
     */
    private function checkTheLimit(User $actor): void
    {
        $key = 'dice-roll:'.$actor->id;

        if (RateLimiter::tooManyAttempts($key, self::PER_MINUTE)) {
            $seconds = RateLimiter::availableIn($key);

            throw new TooManyRollsException(
                "That is a lot of dice. Try again in {$seconds} seconds."
            );
        }

        RateLimiter::hit($key);
    }
}

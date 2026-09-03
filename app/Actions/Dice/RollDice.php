<?php

namespace App\Actions\Dice;

use App\Exceptions\InvalidDiceFormulaException;
use App\Models\Campaign;
use App\Models\DiceRoll;
use App\Models\GameSession;
use App\Models\User;
use App\Support\Dice\DiceFormula;
use App\Support\Dice\DiceRoll as Result;
use App\Support\Dice\DiceRoller;

class RollDice
{
    public function __construct(private readonly DiceRoller $roller) {}

    /**
     * Parses, rolls, and logs. The formula is stored normalised, so "4 D 6 KH 3" and
     * "4d6kh3" read the same in the log a week later.
     *
     * @throws InvalidDiceFormulaException
     */
    public function handle(
        Campaign $campaign,
        User $actor,
        string $formula,
        ?string $label = null,
        ?GameSession $session = null,
    ): DiceRoll {
        $result = $this->roller->roll(DiceFormula::parse($formula));

        return DiceRoll::create([
            'campaign_id' => $campaign->id,
            'game_session_id' => $session?->id,
            'user_id' => $actor->id,
            'formula' => $result->formula,
            'label' => $label !== null && trim($label) !== '' ? trim($label) : null,
            'total' => $result->total,
            'detail' => $result->toArray(),
        ]);
    }

    /**
     * Rolls without logging. Used when one button rolls for a dozen combatants and
     * twelve log lines would be noise rather than history.
     */
    public function roll(string $formula): Result
    {
        return $this->roller->roll(DiceFormula::parse($formula));
    }
}

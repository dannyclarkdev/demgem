<?php

namespace App\Livewire\Dice;

use App\Actions\Dice\RollDice;
use App\Exceptions\InvalidDiceFormulaException;
use App\Exceptions\TooManyRollsException;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\DiceRoll;
use App\Models\GameSession;
use App\Models\User;
use App\Support\Dice\DiceFormula;
use App\Support\Dice\KeepMode;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The dice tray: the controls, and nothing else. Dice\Log holds the results, because
 * from slice 5 the log is the campaign's and not this component's.
 *
 * Owner, co-GM, and player. Not a spectator, who is read-only. The tray is on the Run
 * screen drawer and on /table, and a roll from either reaches every open screen.
 *
 * Nested, so a roll re-renders the tray and not the screen behind it, and it calls
 * enterCampaign() in its own mount because the hydrate hook runs per component.
 */
class Tray extends Component
{
    use InteractsWithCampaign;

    public ?GameSession $session = null;

    public string $formula = '1d20';

    public string $label = '';

    /** '', 'kh' for advantage, 'kl' for disadvantage. Applies to the leading die. */
    public string $advantage = '';

    /** The GM's screen. Ignored for anyone else, in the action, on every surface. */
    public bool $private = false;

    public const QUICK_DICE = [20, 12, 10, 8, 6, 4, 100];

    public function mount(Campaign $campaign, ?GameSession $session = null): void
    {
        $this->enterCampaign($campaign);
        $this->authorize('rollDice', $campaign);

        $this->session = $session;
    }

    public function rollQuick(int $sides, RollDice $rollDice): void
    {
        $this->formula = 'd'.$sides;

        if ($sides !== 20) {
            $this->advantage = '';
        }

        $this->roll($rollDice);
    }

    public function roll(RollDice $rollDice): void
    {
        $this->authorize('rollDice', $this->campaign);

        $this->validate([
            'formula' => ['required', 'string', 'max:60'],
            'label' => ['nullable', 'string', 'max:60'],
        ]);

        $mode = KeepMode::tryFrom($this->advantage);

        try {
            $rollDice->handle(
                $this->campaign,
                $this->user(),
                DiceFormula::withAdvantage($this->formula, $mode),
                $this->label,
                $this->session,
                $this->private,
            );
        } catch (InvalidDiceFormulaException|TooManyRollsException $exception) {
            $this->addError('formula', $exception->getMessage());

            return;
        }

        $this->label = '';
    }

    public function render(): View
    {
        return view('livewire.dice.tray', [
            'quickDice' => self::QUICK_DICE,
            'mayRollPrivately' => DiceRoll::mayRollPrivately($this->role()),
        ]);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}

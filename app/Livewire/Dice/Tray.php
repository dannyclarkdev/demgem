<?php

namespace App\Livewire\Dice;

use App\Actions\Dice\RollDice;
use App\Exceptions\InvalidDiceFormulaException;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\DiceRoll;
use App\Models\GameSession;
use App\Models\User;
use App\Support\Dice\DiceFormula;
use App\Support\Dice\KeepMode;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * The dice tray. Nested, so a roll re-renders the tray and not the screen behind it,
 * and it calls enterCampaign() in its own mount because the hydrate hook runs per
 * component. GM roles only: a player rolling in the app is worth nothing until other
 * people see it, which is the shared log in P2.
 */
class Tray extends Component
{
    use InteractsWithCampaign;

    public ?GameSession $session = null;

    public string $formula = '1d20';

    public string $label = '';

    /** '', 'kh' for advantage, 'kl' for disadvantage. Applies to the leading die. */
    public string $advantage = '';

    public const QUICK_DICE = [20, 12, 10, 8, 6, 4, 100];

    public const LOG_LIMIT = 25;

    public function mount(Campaign $campaign, ?GameSession $session = null): void
    {
        $this->enterCampaign($campaign);
        $this->authorize('useGmTools', $campaign);

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
        $this->authorize('useGmTools', $this->campaign);

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
            );
        } catch (InvalidDiceFormulaException $exception) {
            $this->addError('formula', $exception->getMessage());

            return;
        }

        $this->label = '';
    }

    public function clearLog(): void
    {
        $this->authorize('useGmTools', $this->campaign);

        DiceRoll::query()->where('user_id', $this->user()->id)->delete();
    }

    public function render(): View
    {
        return view('livewire.dice.tray', [
            'quickDice' => self::QUICK_DICE,
            'rolls' => $this->recentRolls(),
        ]);
    }

    /**
     * A GM sees the whole campaign's log, which in this slice is every GM's rolls.
     *
     * @return Collection<int, DiceRoll>
     */
    private function recentRolls(): Collection
    {
        return DiceRoll::query()
            ->with('user')
            ->latest()
            ->limit(self::LOG_LIMIT)
            ->get();
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}

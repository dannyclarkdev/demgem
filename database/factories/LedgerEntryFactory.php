<?php

namespace Database\Factories;

use App\Enums\LedgerKind;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedgerEntry>
 */
class LedgerEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'game_session_id' => null,
            'kind' => LedgerKind::Coin,
            'amount' => 10,
            'item_name' => null,
            'quantity' => null,
            'entity_id' => null,
            'note' => null,
            'created_by' => null,
        ];
    }

    public function inCampaign(Campaign $campaign): static
    {
        return $this->state(['campaign_id' => $campaign->id]);
    }

    public function coin(float $amount, ?string $note = null): static
    {
        return $this->state(['kind' => LedgerKind::Coin, 'amount' => $amount, 'item_name' => null, 'quantity' => null, 'note' => $note]);
    }

    public function item(string $name, int $quantity = 1, ?Entity $page = null): static
    {
        return $this->state([
            'kind' => LedgerKind::Item,
            'amount' => null,
            'item_name' => $name,
            'quantity' => $quantity,
            'entity_id' => $page?->id,
        ]);
    }

    public function madeIn(GameSession $session): static
    {
        return $this->state(['campaign_id' => $session->campaign_id, 'game_session_id' => $session->id]);
    }

    public function by(User $author): static
    {
        return $this->state(['created_by' => $author->id]);
    }
}

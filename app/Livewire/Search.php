<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Search')]
class Search extends Component
{
    use InteractsWithCampaign;

    #[Url(as: 'q')]
    public string $query = '';

    public function mount(Campaign $campaign): void
    {
        $this->enterCampaign($campaign);
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $role = $this->role();
        $term = trim($this->query);

        /** @var Collection<int, Entity> $results */
        $results = $term === ''
            ? new Collection
            : Entity::search($term)
                ->where('campaign_id', $this->campaign->id)
                ->query(function (Builder $q) use ($user, $role): void {
                    /** @var Builder<Entity> $q */
                    $q->visibleTo($user, $role)->with('tags')->orderBy('name');
                })
                ->take(50)
                ->get();

        return view('livewire.search', [
            'term' => $term,
            'groups' => $results->groupBy(fn (Entity $entity) => $entity->type->value)->sortKeys(),
            'total' => $results->count(),
        ]);
    }
}

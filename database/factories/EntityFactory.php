<?php

namespace Database\Factories;

use App\Enums\EntityType;
use App\Enums\Visibility;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Entity>
 */
class EntityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));

        return [
            'campaign_id' => Campaign::factory(),
            'type' => EntityType::Character,
            'name' => $name,
            'slug' => fn (array $attributes) => Str::slug($attributes['name']),
            'body' => fake()->paragraph(),
            'dm_notes' => null,
            'visibility' => Visibility::Dm,
            'parent_id' => null,
            'is_pc' => false,
            'player_user_id' => null,
        ];
    }

    public function type(EntityType $type): static
    {
        return $this->state(['type' => $type]);
    }

    public function visibility(Visibility $visibility): static
    {
        return $this->state(['visibility' => $visibility]);
    }

    public function forPlayers(): static
    {
        return $this->visibility(Visibility::Players);
    }

    public function dmOnly(): static
    {
        return $this->visibility(Visibility::Dm);
    }

    public function selectedFor(User ...$users): static
    {
        return $this->visibility(Visibility::Selected)
            ->afterCreating(fn (Entity $entity) => $entity->viewers()->sync(array_map(fn (User $u) => $u->id, $users)));
    }

    public function pcOf(User $user): static
    {
        return $this->state(['type' => EntityType::Character, 'is_pc' => true, 'player_user_id' => $user->id]);
    }

    public function childOf(Entity $parent): static
    {
        return $this->state(['type' => $parent->type, 'parent_id' => $parent->id, 'campaign_id' => $parent->campaign_id]);
    }

    public function withDmNotes(string $notes): static
    {
        return $this->state(['dm_notes' => $notes]);
    }
}

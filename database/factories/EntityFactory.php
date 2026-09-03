<?php

namespace Database\Factories;

use App\Enums\EntityType;
use App\Enums\QuestStatus;
use App\Enums\Visibility;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\QuestObjective;
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

    /**
     * A character record: class, level, and a sheet link.
     */
    public function withRecord(string $class = 'Bard', int $level = 5, ?string $sheetUrl = 'https://example.test/sheet'): static
    {
        return $this->state([
            'type' => EntityType::Character,
            'character_class' => $class,
            'level' => $level,
            'sheet_url' => $sheetUrl,
        ]);
    }

    public function childOf(Entity $parent): static
    {
        return $this->state(['type' => $parent->type, 'parent_id' => $parent->id, 'campaign_id' => $parent->campaign_id]);
    }

    public function withDmNotes(string $notes): static
    {
        return $this->state(['dm_notes' => $notes]);
    }

    /**
     * A quest, available unless another status is asked for. Wraps type() rather than
     * replacing it, because quest_status is meaningless on the other five types.
     */
    public function quest(?QuestStatus $status = null): static
    {
        return $this->state([
            'type' => EntityType::Quest,
            'quest_status' => $status ?? QuestStatus::Available,
        ]);
    }

    public function givenBy(Entity $giver): static
    {
        return $this->state(['giver_entity_id' => $giver->id, 'campaign_id' => $giver->campaign_id]);
    }

    public function withRewards(string $rewards): static
    {
        return $this->state(['rewards' => $rewards]);
    }

    /**
     * Objectives in order, the first $completed of them already ticked.
     */
    public function withObjectives(int $count, int $completed = 0): static
    {
        return $this->afterCreating(function (Entity $entity) use ($count, $completed): void {
            for ($position = 0; $position < $count; $position++) {
                QuestObjective::factory()->for($entity, 'quest')->create([
                    'campaign_id' => $entity->campaign_id,
                    'position' => $position,
                    'completed_at' => $position < $completed ? now() : null,
                ]);
            }
        });
    }
}

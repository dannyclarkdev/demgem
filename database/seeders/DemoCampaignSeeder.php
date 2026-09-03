<?php

namespace Database\Seeders;

use App\Actions\Campaigns\CreateCampaign;
use App\Actions\Entities\CreateEntity;
use App\Actions\Sessions\CreateSession;
use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Enums\PrepRole;
use App\Enums\SessionStatus;
use App\Enums\Visibility;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A small linked world for development. Run: php artisan db:seed --class=DemoCampaignSeeder
 */
class DemoCampaignSeeder extends Seeder
{
    public function run(): void
    {
        $dm = User::firstOrCreate(
            ['email' => 'dev@demgem.test'],
            ['name' => 'Danny (dev)', 'password' => 'password'],
        );
        $player = User::firstOrCreate(
            ['email' => 'tobin@demgem.test'],
            ['name' => 'Tobin Ashgrove', 'password' => 'password'],
        );

        $existing = Campaign::query()->where('name', 'The Drowned Duchy')->first();

        if ($existing !== null) {
            $this->command->warn('The Drowned Duchy already exists. Nothing seeded.');

            return;
        }

        $campaign = app(CreateCampaign::class)->handle($dm, [
            'name' => 'The Drowned Duchy',
            'description' => 'Salt, secrets, and a throne that should have stayed underwater.',
            'ruleset' => 'srd-5e-2024',
        ]);
        $campaign->members()->firstOrCreate(['user_id' => $player->id], ['role' => CampaignRole::Player]);

        $create = app(CreateEntity::class);
        $make = fn (EntityType $type, string $name, array $extra = []): Entity => $create->handle($campaign, $dm, [
            'type' => $type,
            'name' => $name,
            'visibility' => Visibility::Players,
            ...$extra,
        ]);

        $vell = $make(EntityType::Location, 'Vell', [
            'body' => "The drowned duchy. Half its streets flood at high tide. The court sits in [[Harrowgate]] and pretends otherwise.\n\nThe [[Tidewardens]] keep the sea walls. Everyone else keeps their head down.",
            'tags' => ['region', 'coast'],
        ]);
        $harrowgate = $make(EntityType::Location, 'Harrowgate', [
            'parent_id' => $vell->id,
            'body' => 'Capital of [[Vell]]. Built on pilings over the old city. The [[Salt Cathedral]] is its only dry building.',
            'tags' => ['city'],
        ]);
        $make(EntityType::Location, 'Salt Cathedral', [
            'parent_id' => $harrowgate->id,
            'body' => 'Where [[Abbess Corvane]] preaches that the flood was a mercy.',
            'dm_notes' => 'The crypt connects to the [[Ember Throne]] chamber. Nobody alive knows.',
            'tags' => ['landmark', 'religion'],
        ]);
        $make(EntityType::Faction, 'Tidewardens', [
            'body' => 'Sea-wall engineers turned militia. Led by [[Mara Voss]]. They answer to no duke.',
            'tags' => ['militia'],
        ]);
        $make(EntityType::Character, 'Mara Voss', [
            'body' => 'Commander of the [[Tidewardens]]. Blunt, tired, honest. Owes the party a favor after the harbor fire.',
            'dm_notes' => 'Secretly negotiating with the Drowned Court. Will betray the party if the duchy is at stake.',
            'tags' => ['ally', 'npc'],
        ]);
        $make(EntityType::Character, 'Abbess Corvane', [
            'body' => 'Voice of the [[Salt Cathedral]]. Calm. Never blinks.',
            'tags' => ['npc', 'religion'],
        ]);
        $make(EntityType::Character, 'The Drowned Duke', [
            'visibility' => Visibility::Dm,
            'body' => 'Still on the [[Ember Throne]] beneath [[Harrowgate]]. Still ruling. Nobody has told the living.',
            'tags' => ['villain'],
        ]);
        $make(EntityType::Character, 'Wren Ashgrove', [
            'is_pc' => true,
            'player_user_id' => $player->id,
            'body' => 'Rogue. Grew up on the pilings of [[Harrowgate]]. Looking for the sister the tide took.',
            'tags' => ['pc'],
        ]);
        $make(EntityType::Item, 'Ember Throne', [
            'visibility' => Visibility::Dm,
            'body' => 'A basalt seat that stays warm underwater. Whoever sits on it does not drown, and does not leave.',
            'tags' => ['artifact'],
        ]);
        $make(EntityType::Item, 'Tidewarden Signet', [
            'body' => 'Opens the sea-wall gates. [[Mara Voss]] lent it to the party. She wants it back.',
        ]);
        $make(EntityType::Quest, 'Seal the Undercity', [
            'body' => "[[Mara Voss]] asks the party to collapse the tunnels under [[Harrowgate]] before the spring tide.\n\nReward: the [[Tidewarden Signet]], permanently.",
            'tags' => ['active'],
        ]);
        $make(EntityType::Note, 'Session zero agreements', [
            'body' => "- Horror, not gore.\n- Lines: harm to children.\n- Veils: drowning described, not narrated.\n- We start at 5th level.",
            'tags' => ['table'],
        ]);
        $make(EntityType::Note, 'What the players do not know', [
            'visibility' => Visibility::Dm,
            'body' => "- [[The Drowned Duke]] is awake.\n- [[Mara Voss]] is compromised.\n- [[Wren Ashgrove]]'s sister sits at the Duke's right hand.",
        ]);

        $this->seedSessions($campaign, $dm);

        $this->command->info("Seeded The Drowned Duchy for {$dm->email} (password: password).");
    }

    /**
     * Three sessions across the loop: one recapped, one waiting on words, one prepped
     * and ready to run, with two secrets carried over from the first night.
     */
    private function seedSessions(Campaign $campaign, User $dm): void
    {
        $create = app(CreateSession::class);

        $first = $create->handle($campaign, $dm, [
            'number' => 1,
            'title' => 'The Harbor Fire',
            'scheduled_at' => now()->subWeeks(3),
            'status' => SessionStatus::Played,
        ]);
        $first->update([
            'recap' => "The warehouse went up at dusk and took half the north quay with it. [[Mara Voss]] pulled two of you out of the water and asked no questions, which was itself a question.\n\nBy morning the party held the [[Tidewarden Signet]] and a debt nobody has named a price for yet.",
            'recap_published_at' => now()->subWeeks(3)->addDay(),
        ]);

        $second = $create->handle($campaign, $dm, [
            'number' => 2,
            'title' => 'Under the Pilings',
            'scheduled_at' => now()->subWeek(),
            'status' => SessionStatus::Played,
        ]);
        $second->update([
            'live_notes' => "Party went down at low tide. Found the old customs house intact.\nWren recognised the door knocker. Did not say why.\nSpent 40g bribing the gate sergeant.\nEnded mid-corridor, water rising.",
        ]);

        $third = $create->handle($campaign, $dm, [
            'number' => 3,
            'title' => 'The Spring Tide',
            'scheduled_at' => now()->addDays(4)->setTime(19, 0),
            'status' => SessionStatus::Planned,
        ]);
        $third->update([
            'strong_start' => 'The water in the corridor stops rising. Then it starts moving the wrong way, back down the stairs, as if something below is drinking.',
            'dm_notes' => 'Keep [[The Drowned Duke]] off screen. He is a rumour tonight, nothing more.',
        ]);

        $scenes = [
            ['The corridor empties', 'The water drains toward the crypt under the [[Salt Cathedral]]. Following it is the obvious move. It is also the wrong one, and that is fine.'],
            ['The gate sergeant returns', 'He wants the 40 gold back, with interest, and he has six friends.'],
            ['Mara at the sea wall', '[[Mara Voss]] asks for the [[Tidewarden Signet]] back. She will not say why. She is lying about where she was last night.'],
        ];

        foreach ($scenes as $position => [$title, $notes]) {
            $third->scenes()->create([
                'campaign_id' => $campaign->id,
                'position' => $position,
                'title' => $title,
                'notes' => $notes,
            ]);
        }

        $secrets = [
            [$first, 'The harbor fire was set from inside the warehouse.', true],
            [$first, 'The gate sergeant takes bribes from the Drowned Court.', false],
            [$first, "[[Wren Ashgrove]]'s sister was seen alive, underwater, three months ago.", false],
            [$third, '[[Mara Voss]] has met someone beneath the sea wall twice this month.', false],
            [$third, 'The [[Salt Cathedral]] crypt has a door that opens from the other side.', false],
            [$third, 'The [[Ember Throne]] keeps whoever sits on it breathing, and keeps them there.', false],
        ];

        foreach ($secrets as $position => [$session, $body, $revealed]) {
            $secret = $campaign->secrets()->create([
                'game_session_id' => $session->id,
                'body' => $body,
                'position' => $position,
                'created_by' => $dm->id,
            ]);

            if ($revealed) {
                $secret->update(['revealed_at' => now()->subWeeks(3), 'revealed_in_session_id' => $session->id]);
            }
        }

        $prep = [
            [PrepRole::Npc, ['Mara Voss', 'Abbess Corvane']],
            [PrepRole::Location, ['Salt Cathedral', 'Harrowgate']],
            [PrepRole::Monster, ['The Drowned Duke']],
            [PrepRole::Treasure, ['Tidewarden Signet']],
        ];

        foreach ($prep as [$role, $names]) {
            foreach ($names as $position => $name) {
                $entity = $campaign->entities()->where('name', $name)->first();

                if ($entity !== null) {
                    $third->entities()->attach($entity->id, ['role' => $role->value, 'position' => $position]);
                }
            }
        }
    }
}

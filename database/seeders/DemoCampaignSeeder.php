<?php

namespace Database\Seeders;

use App\Actions\Campaigns\CreateCampaign;
use App\Actions\Dice\RollDice;
use App\Actions\Encounters\AddCombatants;
use App\Actions\Encounters\ApplyDamage;
use App\Actions\Encounters\CreateEncounter;
use App\Actions\Encounters\NextTurn;
use App\Actions\Encounters\RollInitiative;
use App\Actions\Encounters\SetConditions;
use App\Actions\Encounters\SetPlayerVisibility;
use App\Actions\Encounters\SortByInitiative;
use App\Actions\Entities\CreateEntity;
use App\Actions\Maps\PlaceMarker;
use App\Actions\Maps\SetMarkerVisibility;
use App\Actions\RandomTables\CreateRandomTable;
use App\Actions\Sessions\CreateSession;
use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Enums\PrepRole;
use App\Enums\QuestStatus;
use App\Enums\SessionStatus;
use App\Enums\Visibility;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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
            'custom_fields' => [
                ['key' => 'Rank', 'value' => 'Commander'],
                ['key' => 'Owes the party', 'value' => 'One favour, unnamed'],
            ],
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
            'character_class' => 'Rogue',
            'level' => 5,
            'sheet_url' => 'https://www.dndbeyond.com/characters/example-wren',
            'custom_fields' => [
                ['key' => 'Race', 'value' => 'Human'],
                ['key' => 'Background', 'value' => 'Urchin'],
            ],
            'body' => 'Rogue. Grew up on the pilings of [[Harrowgate]]. Looking for the sister the tide took.',
            'tags' => ['pc'],
        ]);
        $make(EntityType::Character, 'Halder Bream', [
            'is_pc' => true,
            'character_class' => 'Cleric of the Tide',
            'level' => 5,
            'body' => 'Cleric. Came down from the dry country to see what a drowned god sounds like.',
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
            'quest_status' => QuestStatus::Active,
            'body' => '[[Mara Voss]] asks the party to collapse the tunnels under [[Harrowgate]] before the spring tide.',
            'rewards' => 'The [[Tidewarden Signet]], permanently, and a berth in the harbour for as long as the duchy stands.',
            'tags' => ['active'],
        ]);
        $make(EntityType::Quest, 'Find Wren\'s sister', [
            'quest_status' => QuestStatus::Available,
            'body' => 'Nobody has offered this one. [[Wren Ashgrove]] is going to ask, and the answer will not be kind.',
        ]);
        $make(EntityType::Note, 'Session zero agreements', [
            'body' => "- Horror, not gore.\n- Lines: harm to children.\n- Veils: drowning described, not narrated.\n- We start at 5th level.",
            'tags' => ['table'],
        ]);
        $make(EntityType::Note, 'What the players do not know', [
            'visibility' => Visibility::Dm,
            'body' => "- [[The Drowned Duke]] is awake.\n- [[Mara Voss]] is compromised.\n- [[Wren Ashgrove]]'s sister sits at the Duke's right hand.",
        ]);

        // Sessions first: the ticked objectives record the night they were finished.
        $this->seedSessions($campaign, $dm);
        $this->seedQuestDetails($campaign);
        $this->seedEncounter($campaign, $dm);
        $this->seedTables($campaign, $dm);
        $this->seedDiceLog($campaign, $dm, $player);
        $this->seedMaps($campaign, $dm);

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
            'scheduled_at' => now()->subWeeks(3)->setTime(19, 0),
            'status' => SessionStatus::Played,
        ]);
        $first->update([
            'recap' => "The warehouse went up at dusk and took half the north quay with it. [[Mara Voss]] pulled two of you out of the water and asked no questions, which was itself a question.\n\nBy morning the party held the [[Tidewarden Signet]] and a debt nobody has named a price for yet.",
            'recap_published_at' => now()->subWeeks(3)->addDay(),
        ]);

        $second = $create->handle($campaign, $dm, [
            'number' => 2,
            'title' => 'Under the Pilings',
            'scheduled_at' => now()->subWeek()->setTime(19, 0),
            'status' => SessionStatus::Played,
        ]);
        $second->update([
            'recap' => 'They went down at low tide and found the customs house dry inside, which nobody has explained yet.',
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

    /**
     * The active quest gets a giver and five objectives, two of them already ticked in
     * the first session, so the quest log and the Run screen both have something in them.
     */
    private function seedQuestDetails(Campaign $campaign): void
    {
        $quest = $campaign->entities()->where('name', 'Seal the Undercity')->first();
        $mara = $campaign->entities()->where('name', 'Mara Voss')->first();

        if ($quest === null) {
            return;
        }

        if ($mara !== null) {
            $quest->update(['giver_entity_id' => $mara->id]);
        }

        $objectives = [
            'Get the tide charts from the Tidewardens',
            'Find a way into the Undercity that does not flood',
            'Map the three tunnels under the Salt Cathedral',
            'Collapse them before the spring tide',
            'Give the signet back, or do not',
        ];

        foreach ($objectives as $position => $body) {
            $quest->objectives()->create([
                'campaign_id' => $campaign->id,
                'position' => $position,
                'body' => $body,
                'completed_at' => $position < 2 ? now()->subWeeks(3) : null,
            ]);
        }

        $first = $campaign->gameSessions()->where('number', 1)->first();

        if ($first !== null) {
            $quest->objectives()->whereNotNull('completed_at')->update(['completed_in_session_id' => $first->id]);
        }
    }

    /**
     * The fight the third session is prepped for: the party, plus four of the Duke's
     * drowned, ready to roll initiative.
     */
    /**
     * A fight already in progress, so /table shows a table on the first run rather
     * than an empty state.
     *
     * Half revealed and half not, because that is the feature: the party sees itself
     * and the two thralls it has already met, and the Duke waits behind the GM's eye
     * toggle until the fiction catches up.
     */
    private function seedEncounter(Campaign $campaign, User $dm): void
    {
        $third = $campaign->gameSessions()->where('number', 3)->first();
        $encounter = app(CreateEncounter::class)->handle($campaign, $dm, 'The stair under the Cathedral', $third);
        $add = app(AddCombatants::class);

        $add->fromEntities($encounter, $campaign->entities()->where('is_pc', true)->orderBy('name')->get());
        $add->handle($encounter, 'Drowned thrall', 4, null, 22, 12, 1);

        $duke = $campaign->entities()->where('name', 'The Drowned Duke')->first();

        if ($duke !== null) {
            $add->handle($encounter, $duke->name, 1, $duke, 187, 18, 5);
        }

        app(RollInitiative::class)->handle($encounter);
        app(SortByInitiative::class)->handle($encounter);
        app(NextTurn::class)->handle($encounter);

        $reveal = app(SetPlayerVisibility::class);
        $thralls = $encounter->combatants()->where('name', 'like', 'Drowned thrall%')->orderBy('position')->get();

        foreach ($thralls->take(2) as $thrall) {
            $reveal->handle($thrall, true);
        }

        if ($thralls->isNotEmpty()) {
            app(ApplyDamage::class)->handle($thralls->first(), 18);
            app(SetConditions::class)->handle($thralls->first(), ['Prone']);
        }
    }

    /**
     * Two maps, one nested inside the other, with half the pins revealed.
     *
     * The pictures are drawn here rather than shipped as files: a placeholder that
     * says "placeholder" is more honest than a stock coastline, and the repository
     * stays free of binaries.
     */
    private function seedMaps(Campaign $campaign, User $dm): void
    {
        $create = app(CreateEntity::class);
        $place = app(PlaceMarker::class);
        $reveal = app(SetMarkerVisibility::class);

        $duchy = $create->handle($campaign, $dm, [
            'type' => EntityType::Map,
            'name' => 'The Duchy of Vell',
            'visibility' => Visibility::Players,
            'body' => 'Salt, sea walls, and the drowned court beneath [[Harrowgate]].',
            'tags' => ['map'],
        ]);

        $harrowgate = $create->handle($campaign, $dm, [
            'type' => EntityType::Map,
            'name' => 'Harrowgate streets',
            'visibility' => Visibility::Players,
            'body' => 'The town at high tide. Half of it floods; the court pretends otherwise.',
            'tags' => ['map'],
        ]);

        $this->attachPlaceholderMap($duchy, 'The Duchy of Vell', 2000, 1400);
        $this->attachPlaceholderMap($harrowgate, 'Harrowgate', 1600, 1200);

        $named = fn (string $name): ?Entity => $campaign->entities()->where('name', $name)->first();

        // A pin whose target is a map drills into it. That is the whole of nesting.
        $pins = [
            [$duchy, 26.5, 41.0, null, $named('Harrowgate'), true],
            [$duchy, 24.0, 39.0, null, $harrowgate, true],
            [$duchy, 58.5, 30.0, 'The Salt Cathedral', $named('Salt Cathedral'), true],
            [$duchy, 78.0, 63.5, 'The smugglers stair', null, false],
            [$duchy, 88.0, 21.0, 'Here be dragons', null, true],
            [$duchy, 44.0, 74.0, 'The drowned court', $named('The Drowned Duke'), false],
            [$harrowgate, 33.0, 52.0, 'The Tidewarden barracks', $named('Tidewardens'), true],
            [$harrowgate, 67.5, 38.0, 'Where the sister was seen', null, false],
        ];

        foreach ($pins as [$map, $x, $y, $label, $target, $shown]) {
            $pin = $place->handle($map, $x, $y, $label, $target);

            if ($shown) {
                $reveal->handle($pin, true);
            }
        }
    }

    /**
     * A drawn stand-in: a coast, a grid, and its own name across the top. It exists so
     * the viewer has something to zoom on the first run, and it does not pretend to be
     * anybody's art.
     */
    private function attachPlaceholderMap(Entity $map, string $title, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);

        $sea = imagecolorallocate($image, 34, 62, 84);
        $land = imagecolorallocate($image, 118, 132, 84);
        $coast = imagecolorallocate($image, 196, 184, 140);
        $grid = imagecolorallocatealpha($image, 255, 255, 255, 108);
        $ink = imagecolorallocate($image, 250, 246, 236);

        imagefilledrectangle($image, 0, 0, $width, $height, $sea);

        for ($blob = 0; $blob < 22; $blob++) {
            $cx = (int) ($width * 0.2 + ($blob * $width * 0.03) % ($width * 0.62));
            $cy = (int) ($height * 0.5 + sin($blob) * $height * 0.2);

            imagefilledellipse($image, $cx, $cy, (int) ($width * 0.22), (int) ($height * 0.26), $land);
            imageellipse($image, $cx, $cy, (int) ($width * 0.22), (int) ($height * 0.26), $coast);
        }

        for ($x = 0; $x < $width; $x += (int) ($width / 16)) {
            imageline($image, $x, 0, $x, $height, $grid);
        }

        for ($y = 0; $y < $height; $y += (int) ($height / 12)) {
            imageline($image, 0, $y, $width, $y, $grid);
        }

        imagestring($image, 5, 40, 30, strtoupper($title).' (placeholder)', $ink);

        $path = tempnam(sys_get_temp_dir(), 'demgem-map').'.png';

        imagepng($image, $path);
        imagedestroy($image);

        $map->addMedia($path)->usingFileName(Str::slug($title).'.png')->toMediaCollection('image');
    }

    /**
     * A handful of rolls in the shared log, from both sides of the screen, so the
     * feature explains itself the moment somebody opens /table.
     */
    private function seedDiceLog(Campaign $campaign, User $dm, User $player): void
    {
        $roll = app(RollDice::class);

        $roll->handle($campaign, $dm, '1d20+3', 'Drowned thrall, claw');
        $roll->handle($campaign, $player, '2d20kh1', 'Wren, stealth');
        $roll->handle($campaign, $dm, '2d6+2', 'Thrall damage');
        $roll->handle($campaign, $player, '1d20+5', 'Halder, tide domain save');

        // Behind the screen: the party sees no trace of this one, which is the point.
        $roll->handle($campaign, $dm, '1d20', 'Does the Duke notice them', null, true);
    }

    /**
     * Two tables, one nesting the other, so the nesting is visible on the first roll.
     */
    private function seedTables(Campaign $campaign, User $dm): void
    {
        $create = app(CreateRandomTable::class);

        $names = $create->handle($campaign, $dm, 'Harrowgate names', 'Somebody the party has never met.');

        foreach (['Sella Roke', 'Dann Pell', 'Old Ivry', 'The Cormorant', 'Tass Vane'] as $position => $body) {
            $names->entries()->create([
                'campaign_id' => $campaign->id,
                'position' => $position,
                'weight' => 1,
                'body' => $body,
            ]);
        }

        $rumours = $create->handle($campaign, $dm, 'Tavern rumours', 'What the regulars are saying at the Drowned Cat.');

        $rows = [
            ['A caravan out of [[Harrowgate]] is three days late and nobody will say why.', 30, null],
            ['The sea walls sang last night. Ask about it and people change the subject.', 25, null],
            ['Somebody is asking after the party by name:', 20, $names->id],
            ['A [[Tidewardens]] officer was seen leaving the [[Salt Cathedral]] before dawn.', 15, null],
            ['The tide came in wrong. It went out and did not come back for an hour.', 10, null],
        ];

        foreach ($rows as $position => [$body, $weight, $nested]) {
            $rumours->entries()->create([
                'campaign_id' => $campaign->id,
                'position' => $position,
                'weight' => $weight,
                'body' => $body,
                'nested_table_id' => $nested,
            ]);
        }
    }
}

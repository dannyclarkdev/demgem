<?php

namespace App\Actions\Table;

use App\Actions\Handouts\RevealHandout;
use App\Enums\ScreenFocus;
use App\Enums\Visibility;
use App\Events\ScreenChanged;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\User;
use InvalidArgumentException;

/**
 * Writes what the television at the end of the table shows.
 *
 * Two columns on the campaign and one event. A hidden handout is revealed on the way
 * through RevealHandout, so it lands on every player's own device the same moment it
 * lands on the wall: "on the screen" means the party can see it, and there is no
 * second switch that could say otherwise. A map is not revealed on the way, because a
 * map's visibility is a form decision, so a map the party may not see is refused.
 */
class SetScreen
{
    public function __construct(private readonly RevealHandout $revealHandout) {}

    /**
     * A handout or a map, on the screen.
     *
     * @throws InvalidArgumentException when the page is not a handout or a map, is
     *                                  not this campaign's, or is a map the party may
     *                                  not see.
     */
    public function show(Campaign $campaign, Entity $page, User $actor): void
    {
        $focus = ScreenFocus::forPage($page->type);

        if ($focus === null) {
            throw new InvalidArgumentException('Only a handout or a map goes on the screen.');
        }

        if ($page->campaign_id !== $campaign->id) {
            throw new InvalidArgumentException('That page belongs to another campaign.');
        }

        if ($page->isHandout()) {
            if ($page->visibility === Visibility::Dm) {
                $page = $this->revealHandout->show($page, $actor);
            }
        } elseif ($page->visibility !== Visibility::Players) {
            throw new InvalidArgumentException('The party cannot see that map.');
        }

        $this->write($campaign, $focus, $page->id);
    }

    /**
     * The fight, the clocks, or nothing. Any page on the screen comes down.
     *
     * @throws InvalidArgumentException for a focus that needs a page; that is show().
     */
    public function focus(Campaign $campaign, ?ScreenFocus $focus): void
    {
        if ($focus?->needsPage()) {
            throw new InvalidArgumentException('A handout or a map goes on the screen through show().');
        }

        $this->write($campaign, $focus, null);
    }

    private function write(Campaign $campaign, ?ScreenFocus $focus, ?string $entityId): void
    {
        $campaign->forceFill([
            'screen_focus' => $focus,
            'screen_entity_id' => $entityId,
        ])->save();

        ScreenChanged::dispatch($campaign->id);
    }
}

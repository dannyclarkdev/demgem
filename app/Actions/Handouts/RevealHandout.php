<?php

namespace App\Actions\Handouts;

use App\Actions\Entities\UpdateEntity;
use App\Enums\Visibility;
use App\Events\HandoutRevealed;
use App\Models\Entity;
use App\Models\User;

class RevealHandout
{
    public function __construct(private readonly UpdateEntity $updateEntity) {}

    /**
     * Puts a handout in front of the party, or takes it back.
     *
     * It writes visibility, the column the form writes, and it does it through
     * UpdateEntity so the observers, the mention sync and the audit trail all run
     * exactly as they do when a GM edits the form. This action is a shortcut for the
     * GM at the table; it is deliberately not a second way to decide who may see an
     * entity, because a second way is how the two answers come to disagree.
     *
     * Selected stays a form decision. "Show the party" means the party.
     */
    public function handle(Entity $handout, User $actor, Visibility $visibility): Entity
    {
        $handout = $this->updateEntity->handle($handout, $actor, ['visibility' => $visibility]);

        HandoutRevealed::dispatch($handout->campaign_id, $handout->id);

        return $handout;
    }

    public function show(Entity $handout, User $actor): Entity
    {
        return $this->handle($handout, $actor, Visibility::Players);
    }

    public function takeBack(Entity $handout, User $actor): Entity
    {
        return $this->handle($handout, $actor, Visibility::Dm);
    }
}

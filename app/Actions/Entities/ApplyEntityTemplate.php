<?php

namespace App\Actions\Entities;

use App\Enums\EntityType;
use App\Models\Campaign;
use App\Models\EntityTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ApplyEntityTemplate
{
    public function handle(Campaign $campaign, User $actor, EntityType $type, string $templateId): ?string
    {
        $template = EntityTemplate::query()->where('campaign_id', $campaign->id)->findOrFail($templateId);
        Gate::forUser($actor)->authorize('view', $template);

        if ($template->type !== $type) {
            throw ValidationException::withMessages(['template_id' => 'Choose a template for this entity type.']);
        }

        return $template->body;
    }
}

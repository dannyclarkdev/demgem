<?php

namespace App\Rules;

use App\Enums\EntityType;
use App\Models\Entity;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Names are unique per campaign and type, case-insensitive. Trashed entities do not count.
 */
class UniqueEntityName implements ValidationRule
{
    public function __construct(
        private readonly string $campaignId,
        private readonly EntityType $type,
        private readonly ?string $ignoreId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = Entity::withoutGlobalScopes()
            ->where('campaign_id', $this->campaignId)
            ->where('type', $this->type->value)
            ->whereNull('deleted_at')
            ->whereRaw('lower(name) = ?', [mb_strtolower(trim((string) $value))])
            ->when($this->ignoreId !== null, fn ($query) => $query->whereKeyNot($this->ignoreId))
            ->exists();

        if ($exists) {
            $fail("A {$this->type->label()} with this name already exists in this campaign.");
        }
    }
}

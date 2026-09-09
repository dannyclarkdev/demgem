<?php

namespace App\Http\Resources\Api\V1;

use App\Models\EntityBodyRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EntityBodyRevision */
class EntityBodyRevisionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->whenHas('body'),
            'recorded_at' => $this->recorded_at->toIso8601String(),
            'replaced_by_name' => $this->replaced_by_name,
        ];
    }
}

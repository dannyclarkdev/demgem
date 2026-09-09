<?php

namespace App\Actions\Entities;

use App\Models\EntityTemplate;

class UpdateEntityTemplate
{
    /** @param array{type?: string, name?: string, body?: string|null} $data */
    public function handle(EntityTemplate $template, array $data): EntityTemplate
    {
        if (array_key_exists('name', $data)) {
            $data['name'] = trim($data['name']);
        }

        if (array_key_exists('body', $data) && $data['body'] === '') {
            $data['body'] = null;
        }

        $template->fill($data)->save();

        return $template;
    }
}

<?php

namespace App\Actions\Entities;

use App\Models\EntityTemplate;

class DeleteEntityTemplate
{
    public function handle(EntityTemplate $template): void
    {
        $template->delete();
    }
}

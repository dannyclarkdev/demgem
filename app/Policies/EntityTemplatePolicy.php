<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\EntityTemplate;
use App\Models\User;

class EntityTemplatePolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $campaign->roleFor($user)?->isDm() ?? false;
    }

    public function view(User $user, EntityTemplate $template): bool
    {
        return $this->viewAny($user, $template->campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $this->viewAny($user, $campaign);
    }

    public function update(User $user, EntityTemplate $template): bool
    {
        return $this->view($user, $template);
    }

    public function delete(User $user, EntityTemplate $template): bool
    {
        return $this->view($user, $template);
    }
}

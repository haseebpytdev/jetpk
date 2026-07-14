<?php

namespace App\Policies;

use App\Models\MarkupRule;
use App\Models\User;

class MarkupRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function view(User $user, MarkupRule $markupRule): bool
    {
        return $user->isPlatformAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function update(User $user, MarkupRule $markupRule): bool
    {
        return $user->isPlatformAdmin();
    }

    public function delete(User $user, MarkupRule $markupRule): bool
    {
        return $this->update($user, $markupRule);
    }
}

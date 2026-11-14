<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SkillSuggestion;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SkillSuggestionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SkillSuggestion');
    }

    public function view(AuthUser $authUser, SkillSuggestion $skillSuggestion): bool
    {
        return $authUser->can('View:SkillSuggestion');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SkillSuggestion');
    }

    public function update(AuthUser $authUser, SkillSuggestion $skillSuggestion): bool
    {
        return $authUser->can('Update:SkillSuggestion');
    }

    public function delete(AuthUser $authUser, SkillSuggestion $skillSuggestion): bool
    {
        return $authUser->can('Delete:SkillSuggestion');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SkillSuggestion');
    }

    public function restore(AuthUser $authUser, SkillSuggestion $skillSuggestion): bool
    {
        return $authUser->can('Restore:SkillSuggestion');
    }

    public function forceDelete(AuthUser $authUser, SkillSuggestion $skillSuggestion): bool
    {
        return $authUser->can('ForceDelete:SkillSuggestion');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SkillSuggestion');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SkillSuggestion');
    }

    public function replicate(AuthUser $authUser, SkillSuggestion $skillSuggestion): bool
    {
        return $authUser->can('Replicate:SkillSuggestion');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SkillSuggestion');
    }
}

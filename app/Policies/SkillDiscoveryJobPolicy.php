<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SkillDiscoveryJob;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SkillDiscoveryJobPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SkillDiscoveryJob');
    }

    public function view(AuthUser $authUser, SkillDiscoveryJob $skillDiscoveryJob): bool
    {
        return $authUser->can('View:SkillDiscoveryJob');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SkillDiscoveryJob');
    }

    public function update(AuthUser $authUser, SkillDiscoveryJob $skillDiscoveryJob): bool
    {
        return $authUser->can('Update:SkillDiscoveryJob');
    }

    public function delete(AuthUser $authUser, SkillDiscoveryJob $skillDiscoveryJob): bool
    {
        return $authUser->can('Delete:SkillDiscoveryJob');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SkillDiscoveryJob');
    }

    public function restore(AuthUser $authUser, SkillDiscoveryJob $skillDiscoveryJob): bool
    {
        return $authUser->can('Restore:SkillDiscoveryJob');
    }

    public function forceDelete(AuthUser $authUser, SkillDiscoveryJob $skillDiscoveryJob): bool
    {
        return $authUser->can('ForceDelete:SkillDiscoveryJob');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SkillDiscoveryJob');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SkillDiscoveryJob');
    }

    public function replicate(AuthUser $authUser, SkillDiscoveryJob $skillDiscoveryJob): bool
    {
        return $authUser->can('Replicate:SkillDiscoveryJob');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SkillDiscoveryJob');
    }
}

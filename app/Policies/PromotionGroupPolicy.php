<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PromotionGroup;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PromotionGroupPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PromotionGroup');
    }

    public function view(AuthUser $authUser, PromotionGroup $promotionGroup): bool
    {
        return $authUser->can('View:PromotionGroup');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PromotionGroup');
    }

    public function update(AuthUser $authUser, PromotionGroup $promotionGroup): bool
    {
        return $authUser->can('Update:PromotionGroup');
    }

    public function delete(AuthUser $authUser, PromotionGroup $promotionGroup): bool
    {
        return $authUser->can('Delete:PromotionGroup');
    }

    public function restore(AuthUser $authUser, PromotionGroup $promotionGroup): bool
    {
        return $authUser->can('Restore:PromotionGroup');
    }

    public function forceDelete(AuthUser $authUser, PromotionGroup $promotionGroup): bool
    {
        return $authUser->can('ForceDelete:PromotionGroup');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PromotionGroup');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PromotionGroup');
    }

    public function replicate(AuthUser $authUser, PromotionGroup $promotionGroup): bool
    {
        return $authUser->can('Replicate:PromotionGroup');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PromotionGroup');
    }
}


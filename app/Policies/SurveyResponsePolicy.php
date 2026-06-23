<?php

namespace App\Policies;

use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SurveyResponsePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('responses.view');
    }

    public function view(User $user, SurveyResponse $response): bool
    {
        if (! $user->can('responses.view')) {
            return false;
        }

        if ($user->hasRole('head_of_department') && $user->department) {
            return $user->department === $response->department;
        }

        // Staff can only view responses they collected or recent ones based on their department
        if ($user->hasRole('staff')) {
            return $user->department === $response->department;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('responses.create');
    }

    public function delete(User $user, SurveyResponse $response): bool
    {
        return $user->can('responses.delete');
    }
}

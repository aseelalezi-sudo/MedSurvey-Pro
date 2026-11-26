<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return $user->id === $id;
});

Broadcast::channel('surveys.system', function ($user) {
    return $user->isSystemAdmin();
});

Broadcast::channel('surveys.tenant.{tenantId}', function ($user, string $tenantId) {
    return $user->tenantId === $tenantId
        && $user->can('responses.view')
        && ! in_array($user->role, ['head_of_department', 'staff'], true);
});

Broadcast::channel('surveys.tenant.{tenantId}.department.{department}', function ($user, string $tenantId, string $department) {
    return $user->tenantId === $tenantId
        && $user->can('responses.view')
        && filled($user->department)
        && hash('sha256', $user->department) === $department;
});

<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TicketPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('tickets.view');
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if (! $user->can('tickets.view')) {
            return false;
        }

        if ($user->hasRole('head_of_department') && $user->department) {
            return $user->department === $ticket->department;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('tickets.create');
    }

    public function update(User $user, Ticket $ticket): bool
    {
        if (! $user->can('tickets.update')) {
            return false;
        }

        if ($user->hasRole('head_of_department') && $user->department) {
            return $user->department === $ticket->department;
        }

        return true;
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.delete');
    }
}

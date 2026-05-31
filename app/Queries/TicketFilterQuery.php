<?php

namespace App\Queries;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class TicketFilterQuery
{
    public function __construct(
        private readonly Request $request,
        private readonly ?User $user,
    ) {}

    public static function make(Request $request, ?User $user): self
    {
        return new self($request, $user);
    }

    public function builder(): Builder
    {
        $request = $this->request;
        $user = $this->user;

        $query = Ticket::query()
            ->with('response')
            ->when($user?->tenantId, fn ($query) => $query->whereHas('response', fn ($nested) => $nested->where('tenantId', $user->tenantId)))
            ->when($user?->role === 'head_of_department' && $user?->department, fn ($query) => $query->where('department', $user->department))
            ->when($request->query('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->query('priority'), fn ($query) => $query->where('priority', $request->query('priority')))
            ->when($request->query('department') && $user?->role !== 'head_of_department', fn ($query) => $query->where('department', $request->query('department')))
            ->when($request->query('q'), function ($query, string $search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('patientName', 'like', "%{$search}%")
                        ->orWhere('patientPhone', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%")
                        ->orWhere('id', 'like', '%'.ltrim($search, '#').'%');
                });
            })
            ->when($request->query('dateFilter') && $request->query('dateFilter') !== 'all', function ($query) use ($request): void {
                if ($request->query('dateFilter') === 'today') {
                    $query->where('createdAt', '>=', now()->startOfDay());
                } elseif ($request->query('dateFilter') === 'week') {
                    $query->where('createdAt', '>=', now()->subDays(7));
                } elseif ($request->query('dateFilter') === 'month') {
                    $query->where('createdAt', '>=', now()->subDays(30));
                } elseif ($request->query('dateFilter') === 'custom') {
                    if ($request->query('startDate')) {
                        $query->where('createdAt', '>=', $request->query('startDate'));
                    }
                    if ($request->query('endDate')) {
                        $query->where('createdAt', '<=', Carbon::parse($request->query('endDate'))->endOfDay());
                    }
                }
            })
            ->when(! $request->query('dateFilter') || $request->query('dateFilter') === 'all', function ($query) use ($request): void {
                if ($request->query('startDate')) {
                    $query->where('createdAt', '>=', $request->query('startDate'));
                }
                if ($request->query('endDate')) {
                    $query->where('createdAt', '<=', Carbon::parse($request->query('endDate'))->endOfDay());
                }
            });

        return $query;
    }

    public function applyOrdering(Builder $query): Builder
    {
        return $query
            ->orderByRaw("case status when 'open' then 0 when 'in_progress' then 1 else 2 end")
            ->orderByDesc('createdAt');
    }
}

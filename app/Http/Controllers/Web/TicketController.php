<?php

namespace App\Http\Controllers\Web;

use App\Models\Ticket;
use App\Models\User;
use App\Queries\TicketFilterQuery;
use App\Services\TicketService;
use App\Support\DashboardBadgeCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class TicketController
{
    public function __construct(
        private readonly TicketService $ticketService,
    ) {}

    public function tickets(Request $request): View
    {
        $user = $request->user();
        $perPage = $this->perPage($request);

        $filter = TicketFilterQuery::make($request, $user);
        $tickets = $filter->applyOrdering($filter->builder())
            ->paginate($perPage)
            ->withQueryString();

        $statsQuery = $this->scopedTicketsQuery($user);

        $ticketStats = [
            'open' => (clone $statsQuery)->where('status', 'open')->count(),
            'in_progress' => (clone $statsQuery)->where('status', 'in_progress')->count(),
            'resolved' => (clone $statsQuery)->where('status', 'resolved')->count(),
        ];

        $departments = $this->scopedTicketsQuery($user)
            ->select('department')
            ->whereNotNull('department')
            ->distinct()
            ->orderBy('department')
            ->pluck('department');

        return view('dashboard.tickets', compact('tickets', 'ticketStats', 'departments'));
    }

    public function filterTickets(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = $this->perPage($request);

        $filter = TicketFilterQuery::make($request, $user);
        $tickets = $filter->applyOrdering($filter->builder())
            ->paginate($perPage)
            ->withQueryString();

        $isAr = app()->getLocale() === 'ar';
        $isRtl = $isAr;
        $statusLabels = [
            'open' => __('ticket_status_open') ?: ($isAr ? 'مفتوحة' : 'Open'),
            'in_progress' => __('ticket_status_in_progress') ?: ($isAr ? 'قيد المعالجة' : 'In Progress'),
            'resolved' => __('ticket_status_resolved') ?: ($isAr ? 'تم الحل' : 'Resolved'),
        ];

        $html = view('dashboard.partials._ticket-cards', compact('tickets', 'isAr', 'isRtl', 'statusLabels'))->render();
        $pagination = view('dashboard.partials._tickets-pagination', compact('tickets', 'isAr'))->render();

        return response()->json(['html' => $html, 'pagination' => $pagination]);
    }

    public function updateTicket(string $id, Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'status' => ['sometimes', 'in:open,in_progress,resolved'],
            'resolutionNotes' => ['nullable', 'string', 'max:2000'],
            'assignedTo' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $this->ticketService->update($id, $payload, $request->user());

            DashboardBadgeCache::forgetOpenTickets($request->user());

            return redirect()->back()->with('success', 'تم تحديث التذكرة بنجاح');
        } catch (RuntimeException $e) {
            return redirect()->back()->with(
                'error',
                $e->getMessage() === 'Forbidden'
                    ? 'ليس لديك صلاحية لتعديل هذه التذكرة'
                    : 'التذكرة غير موجودة'
            );
        }
    }

    public function destroyTicket(string $id, Request $request): RedirectResponse
    {
        try {
            $this->ticketService->destroy($id, $request->user());

            DashboardBadgeCache::forgetOpenTickets($request->user());

            return redirect()->back()->with('success', 'تم حذف التذكرة بنجاح');
        } catch (RuntimeException) {
            return redirect()->back()->with('error', 'تعذر حذف التذكرة');
        }
    }

    private function scopedTicketsQuery(?User $user): Builder
    {
        return Ticket::query()
            ->forTenant($user?->tenantId)
            ->when($user?->role === 'head_of_department' && $user?->department, fn ($query) => $query->where('department', $user->department));
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->integer('per_page', 20);

        return in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 20;
    }
}

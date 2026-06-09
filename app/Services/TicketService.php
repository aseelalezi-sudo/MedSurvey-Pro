<?php

namespace App\Services;

use App\Models\Ticket;
use App\Traits\ResolvesAuditTarget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class TicketService
{
    use ResolvesAuditTarget;

    public function index(Request $request): Collection
    {
        $user = auth('api')->user();

        return Ticket::query()
            ->with('response')
            ->when($user?->tenantId, fn ($query) => $query->whereHas('response', fn ($nested) => $nested->where('tenantId', $user->tenantId)))
            ->when($user?->role === 'head_of_department' && $user?->department, fn ($query) => $query->where('department', $user->department))
            ->when($request->query('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->query('department') && $user?->role !== 'head_of_department', fn ($query) => $query->where('department', $request->query('department')))
            ->orderByDesc('createdAt')
            ->take(200)
            ->get();
    }

    public function update(string $id, array $payload, $user): Ticket
    {
        $ticket = $this->resolveAuditTarget(request(), 'audit_pre_target_ticket', fn () => Ticket::query()->with('response')->find($id));

        if (! $ticket || ($user?->tenantId && $ticket->response?->tenantId !== $user->tenantId)) {
            throw new \RuntimeException('Ticket not found');
        }

        if ($user?->role === 'unit_manager') {
            throw new \RuntimeException('Forbidden');
        }

        if ($user?->role === 'head_of_department' && $user?->department && $ticket->department !== $user->department) {
            throw new \RuntimeException('Forbidden');
        }

        $update = [];
        foreach (['status', 'resolutionNotes', 'assignedTo'] as $field) {
            if (array_key_exists($field, $payload)) {
                $update[$field] = $payload[$field];
            }
        }

        if (($payload['status'] ?? null) === 'resolved') {
            $update['resolvedAt'] = now();
        }

        $ticket->update($update);

        return $ticket->fresh();
    }

    public function destroy(string $id, $user): void
    {
        $ticket = $this->resolveAuditTarget(request(), 'audit_pre_target_ticket', fn () => Ticket::query()->with('response')->find($id));

        if (! $ticket || ($user?->tenantId && $ticket->response?->tenantId !== $user->tenantId)) {
            throw new \RuntimeException('Ticket not found');
        }

        $ticket->delete();
    }

    public function transformTicket(Ticket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'responseId' => $ticket->responseId,
            'department' => $ticket->department,
            'patientName' => $ticket->patientName,
            'patientPhone' => $ticket->patientPhone,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'description' => $ticket->description,
            'createdAt' => optional($ticket->createdAt)->toISOString(),
            'resolvedAt' => optional($ticket->resolvedAt)->toISOString(),
            'resolutionNotes' => $ticket->resolutionNotes,
            'assignedTo' => $ticket->assignedTo,
        ];
    }
}

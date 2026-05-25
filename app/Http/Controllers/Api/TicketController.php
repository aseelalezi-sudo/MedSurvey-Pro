<?php

namespace App\Http\Controllers\Api;

use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController
{
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        $tickets = Ticket::query()
            ->with('response')
            ->when($user?->tenantId, fn ($query) => $query->whereHas('response', fn ($nested) => $nested->where('tenantId', $user->tenantId)))
            ->when($user?->role === 'head_of_department' && $user?->department, fn ($query) => $query->where('department', $user->department))
            ->when($request->query('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->query('department') && $user?->role !== 'head_of_department', fn ($query) => $query->where('department', $request->query('department')))
            ->orderByDesc('createdAt')
            ->take(200)
            ->get()
            ->map(fn (Ticket $ticket) => $this->transformTicket($ticket));

        return response()->json($tickets);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $payload = $request->validate([
            'status' => ['sometimes', 'in:open,in_progress,resolved'],
            'resolutionNotes' => ['nullable', 'string'],
            'assignedTo' => ['nullable', 'string'],
        ]);

        $user = auth('api')->user();
        $ticket = Ticket::query()->with('response')->find($id);

        if (! $ticket || ($user?->tenantId && $ticket->response?->tenantId !== $user->tenantId)) {
            return response()->json(['error' => 'Ticket not found'], 404);
        }

        if ($user?->role === 'head_of_department' && $user?->department && $ticket->department !== $user->department) {
            return response()->json(['error' => 'Forbidden'], 403);
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

        return response()->json($this->transformTicket($ticket->fresh()));
    }

    private function transformTicket(Ticket $ticket): array
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


<?php

namespace App\Http\Controllers\Api;

use App\Services\TicketService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController
{
    public function __construct(
        private readonly TicketService $ticketService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tickets = $this->ticketService->index($request);

        return response()->json(
            $tickets->map(fn ($ticket) => $this->ticketService->transformTicket($ticket))
        );
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $payload = $request->validate([
            'status' => ['sometimes', 'in:open,in_progress,resolved'],
            'resolutionNotes' => ['nullable', 'string'],
            'assignedTo' => ['nullable', 'string'],
        ]);

        $user = auth('api')->user();

        try {
            $ticket = $this->ticketService->update($id, $payload, $user);

            return response()->json($this->ticketService->transformTicket($ticket));
        } catch (\RuntimeException $e) {
            $code = $e->getMessage() === 'Forbidden' ? 403 : 404;

            return ApiResponse::error($e->getMessage(), $code);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        $user = auth('api')->user();

        try {
            $this->ticketService->destroy($id, $user);

            return ApiResponse::deleted('Ticket deleted successfully');
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }
    }
}

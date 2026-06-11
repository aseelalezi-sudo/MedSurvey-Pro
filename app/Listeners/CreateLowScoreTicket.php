<?php

namespace App\Listeners;

use App\Events\SurveySubmitted;
use App\Models\Ticket;

class CreateLowScoreTicket
{
    public function handle(SurveySubmitted $event): void
    {
        $response = $event->response;

        if ($response->overallScore >= 50) {
            return;
        }

        // Check if ticket already exists for this response
        $exists = Ticket::query()->where('responseId', $response->id)->exists();
        if ($exists) {
            return;
        }

        Ticket::query()->create([
            'responseId' => $response->id,
            'department' => $response->department,
            'patientName' => $response->patientName ?? 'زائر',
            'patientPhone' => $response->patientPhone ?? null,
            'tenantId' => $response->tenantId,
            'priority' => $response->overallScore < 30 ? 'high' : 'medium',
            'status' => 'open',
            'description' => "تنبيه آلي: تقييم منخفض جداً ({$response->overallScore}%). المراجع أبدى عدم رضاه عن الخدمة في قسم {$response->department}. يرجى المتابعة الفورية.",
        ]);
    }
}

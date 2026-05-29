<?php

namespace App\Events;

use App\Models\SurveyResponse;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SurveySubmitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $response;

    /**
     * Create a new event instance.
     */
    public function __construct(SurveyResponse $response)
    {
        $this->response = $response;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        // Broadcast on a private channel for security
        return [
            new PrivateChannel('surveys'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->response->id,
            'department' => $this->response->department,
            'overallScore' => $this->response->overallScore,
            'patientName' => $this->response->patientName ?? 'مريض',
        ];
    }
}

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
        $channels = [new PrivateChannel('surveys.system')];
        if ($this->response->tenantId !== null) {
            $tenantChannel = 'surveys.tenant.'.$this->response->tenantId;
            $channels[] = new PrivateChannel($tenantChannel);
            $channels[] = new PrivateChannel($tenantChannel.'.department.'.hash('sha256', (string) $this->response->department));
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->response->id];
    }
}

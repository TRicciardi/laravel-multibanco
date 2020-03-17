<?php

namespace tricciardi\LaravelMultibanco\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
Use tricciardi\LaravelMultibanco\Reference;

class PaymentReceived
{
    use InteractsWithSockets, SerializesModels;

    public $reference;
    /**
     * Create a new event instance.
     *
     * @return void
     */
     public function __construct($reference)
     {
         $this->reference = $reference;
     }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}

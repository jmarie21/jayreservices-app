<?php

namespace App\Events;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Services\SupportChatPayload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SupportConversation $conversation,
        public SupportMessage $message,
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("support.conversation.{$this->conversation->id}"),
            new PrivateChannel('support.admin.inbox'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'support.message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'conversation' => SupportChatPayload::conversationSummary($this->conversation),
            'message' => SupportChatPayload::message($this->message),
        ];
    }
}

<?php

namespace App\Services;

use App\Events\SupportMessageSent;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

class SupportChatBroadcaster
{
    public function __construct(
        protected Dispatcher $events,
    ) {
    }

    public function dispatch(SupportConversation $conversation, SupportMessage $message): void
    {
        try {
            $this->events->dispatch(new SupportMessageSent($conversation, $message));
        } catch (\Throwable $exception) {
            Log::warning('Support chat broadcast failed.', [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}

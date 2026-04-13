<?php

namespace App\Services;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Support\Str;

class SupportChatPayload
{
    /**
     * @return array{conversation_id:int|null,unread_count:int}
     */
    public static function bootstrapForClient(User $user): array
    {
        $conversation = SupportConversation::query()
            ->where('client_id', $user->id)
            ->withClientUnreadCount()
            ->first();

        return [
            'conversation_id' => $conversation?->id,
            'unread_count' => $conversation
                ? self::attributeOrFallback($conversation, 'client_unread_count', fn () => self::calculateUnreadCount($conversation, 'client'))
                : 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function conversationSummary(SupportConversation $conversation): array
    {
        $conversation->loadMissing([
            'client:id,name,email',
            'latestMessage.sender:id,name,role',
        ]);

        $latestMessage = $conversation->latestMessage;
        $lastMessageAt = $conversation->last_message_at ?? $latestMessage?->created_at;

        return [
            'id' => $conversation->id,
            'client' => [
                'id' => $conversation->client?->id,
                'name' => $conversation->client?->name ?? 'Unknown client',
                'email' => $conversation->client?->email,
            ],
            'last_message_preview' => self::messagePreview($latestMessage?->body),
            'last_message_at' => $lastMessageAt?->toISOString(),
            'last_message_sender_id' => $conversation->last_message_sender_id,
            'last_message_sender_role' => $latestMessage?->sender?->role,
            'admin_unread_count' => self::attributeOrFallback(
                $conversation,
                'admin_unread_count',
                fn () => self::calculateUnreadCount($conversation, 'admin')
            ),
            'client_unread_count' => self::attributeOrFallback(
                $conversation,
                'client_unread_count',
                fn () => self::calculateUnreadCount($conversation, 'client')
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function conversationDetail(SupportConversation $conversation): array
    {
        $conversation->loadMissing([
            'messages.sender:id,name,role',
        ]);

        $messages = $conversation->messages
            ->sortBy('id')
            ->values()
            ->map(fn (SupportMessage $message) => self::message($message))
            ->all();

        return [
            ...self::conversationSummary($conversation),
            'messages' => $messages,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function message(SupportMessage $message): array
    {
        $message->loadMissing(['sender:id,name,role', 'attachments']);

        return [
            'id' => $message->id,
            'body' => $message->body,
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender?->name ?? 'Deleted user',
            'sender_role' => $message->sender?->role ?? 'unknown',
            'created_at' => $message->created_at?->toISOString(),
            'attachments' => $message->attachments
                ->values()
                ->map(fn ($attachment) => $attachment->toArray())
                ->all(),
        ];
    }

    protected static function messagePreview(?string $body): string
    {
        $text = (string) Str::of((string) $body)->squish()->limit(80);

        return $text !== '' ? $text : '[Media attached]';
    }

    protected static function calculateUnreadCount(SupportConversation $conversation, string $viewerRole): int
    {
        $senderRole = $viewerRole === 'admin' ? 'client' : 'admin';
        $readColumn = $viewerRole === 'admin' ? 'admin_last_read_at' : 'client_last_read_at';
        $readAt = $conversation->{$readColumn};

        return $conversation->messages()
            ->whereHas('sender', fn ($query) => $query->where('role', $senderRole))
            ->when($readAt, fn ($query) => $query->where('created_at', '>', $readAt))
            ->count();
    }

    protected static function attributeOrFallback(SupportConversation $conversation, string $attribute, callable $fallback): int
    {
        if (array_key_exists($attribute, $conversation->getAttributes())) {
            return (int) $conversation->getAttribute($attribute);
        }

        return (int) $fallback();
    }
}

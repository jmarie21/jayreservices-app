<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupportConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'last_message_at',
        'last_message_sender_id',
        'client_last_read_at',
        'admin_last_read_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'client_last_read_at' => 'datetime',
            'admin_last_read_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function lastMessageSender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_message_sender_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(SupportMessage::class)->latestOfMany();
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');
    }

    public function scopeWithAdminUnreadCount(Builder $query): Builder
    {
        return $query->withCount([
            'messages as admin_unread_count' => function (Builder $messageQuery) {
                $messageQuery
                    ->whereHas('sender', fn (Builder $senderQuery) => $senderQuery->where('role', 'client'))
                    ->where(function (Builder $unreadQuery) {
                        $unreadQuery
                            ->whereNull('support_conversations.admin_last_read_at')
                            ->orWhereColumn('support_messages.created_at', '>', 'support_conversations.admin_last_read_at');
                    });
            },
        ]);
    }

    public function scopeWithClientUnreadCount(Builder $query): Builder
    {
        return $query->withCount([
            'messages as client_unread_count' => function (Builder $messageQuery) {
                $messageQuery
                    ->whereHas('sender', fn (Builder $senderQuery) => $senderQuery->where('role', 'admin'))
                    ->where(function (Builder $unreadQuery) {
                        $unreadQuery
                            ->whereNull('support_conversations.client_last_read_at')
                            ->orWhereColumn('support_messages.created_at', '>', 'support_conversations.client_last_read_at');
                    });
            },
        ]);
    }

    public function scopeWithSupportSummaryData(Builder $query): Builder
    {
        return $query
            ->with([
                'client:id,name,email',
                'latestMessage.sender:id,name,role',
            ])
            ->withAdminUnreadCount()
            ->withClientUnreadCount();
    }

    public function markReadForRole(string $role): void
    {
        $column = $role === 'admin' ? 'admin_last_read_at' : 'client_last_read_at';

        $this->forceFill([
            $column => now(),
        ])->save();
    }
}

<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\StoreSupportMessageRequest;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Services\SupportChatBroadcaster;
use App\Services\SupportChatPayload;
use App\Services\SupportMessageAttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupportChatController extends Controller
{
    public function __construct(
        protected SupportChatBroadcaster $broadcaster,
        protected SupportMessageAttachmentService $attachmentService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $conversation = SupportConversation::query()
            ->where('client_id', $request->user()->id)
            ->first();

        if (! $conversation) {
            return response()->json([
                'conversation' => null,
                'bootstrap' => SupportChatPayload::bootstrapForClient($request->user()),
            ]);
        }

        $conversation->markReadForRole('client');
        $conversation = $this->loadConversation($conversation->id);

        return response()->json([
            'conversation' => SupportChatPayload::conversationDetail($conversation),
            'bootstrap' => SupportChatPayload::bootstrapForClient($request->user()),
        ]);
    }

    public function storeMessage(StoreSupportMessageRequest $request): JsonResponse
    {
        [$conversation, $message] = DB::transaction(function () use ($request) {
            $conversation = SupportConversation::query()->firstOrCreate([
                'client_id' => $request->user()->id,
            ]);

            $message = $conversation->messages()->create([
                'sender_id' => $request->user()->id,
                'body' => $request->validated('body'),
            ]);

            $conversation->forceFill([
                'last_message_at' => $message->created_at,
                'last_message_sender_id' => $request->user()->id,
                'client_last_read_at' => $message->created_at,
            ])->save();

            return [$conversation, $message];
        });

        $this->attachmentService->storeAttachments($request, $message);

        $conversation = $this->loadConversation($conversation->id);
        $message = $this->loadMessage($message->id);

        $this->broadcaster->dispatch($conversation, $message);

        return response()->json([
            'conversation' => SupportChatPayload::conversationDetail($conversation),
            'message' => SupportChatPayload::message($message),
            'bootstrap' => SupportChatPayload::bootstrapForClient($request->user()),
        ], 201);
    }

    public function markRead(Request $request): JsonResponse
    {
        $conversation = SupportConversation::query()
            ->where('client_id', $request->user()->id)
            ->first();

        if (! $conversation) {
            return response()->json([
                'conversation_id' => null,
                'unread_count' => 0,
            ]);
        }

        $conversation->markReadForRole('client');

        return response()->json([
            'conversation_id' => $conversation->id,
            'unread_count' => 0,
        ]);
    }

    protected function loadConversation(int $conversationId): SupportConversation
    {
        return SupportConversation::query()
            ->withSupportSummaryData()
            ->with([
                'messages.sender:id,name,role',
                'messages.attachments',
                'messages.project:id,project_name',
            ])
            ->findOrFail($conversationId);
    }

    protected function loadMessage(int $messageId): SupportMessage
    {
        return SupportMessage::query()
            ->with(['sender:id,name,role', 'attachments', 'project:id,project_name'])
            ->findOrFail($messageId);
    }
}

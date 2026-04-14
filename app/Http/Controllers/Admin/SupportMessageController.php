<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\StoreSupportMessageRequest;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Services\SupportChatBroadcaster;
use App\Services\SupportChatPayload;
use App\Services\SupportMessageAttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SupportMessageController extends Controller
{
    public function __construct(
        protected SupportChatBroadcaster $broadcaster,
        protected SupportMessageAttachmentService $attachmentService,
    ) {}

    public function store(StoreSupportMessageRequest $request, SupportConversation $conversation): JsonResponse
    {
        [$conversation, $message] = DB::transaction(function () use ($request, $conversation) {
            $message = $conversation->messages()->create([
                'sender_id' => $request->user()->id,
                'body' => $request->validated('body'),
            ]);

            $conversation->forceFill([
                'last_message_at' => $message->created_at,
                'last_message_sender_id' => $request->user()->id,
                'admin_last_read_at' => $message->created_at,
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
        ], 201);
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

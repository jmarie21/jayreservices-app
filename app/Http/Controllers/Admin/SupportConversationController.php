<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Services\SupportChatPayload;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class SupportConversationController extends Controller
{
    public function index(): Response
    {
        $conversations = SupportConversation::query()
            ->ordered()
            ->withSupportSummaryData()
            ->get()
            ->map(fn (SupportConversation $conversation) => SupportChatPayload::conversationSummary($conversation))
            ->values();

        return Inertia::render('admin/Messages', [
            'conversations' => $conversations,
            'initialConversationId' => $conversations->first()['id'] ?? null,
        ]);
    }

    public function show(SupportConversation $conversation): JsonResponse
    {
        $conversation->markReadForRole('admin');
        $conversation = $this->loadConversation($conversation->id);

        return response()->json([
            'conversation' => SupportChatPayload::conversationDetail($conversation),
        ]);
    }

    public function markRead(SupportConversation $conversation): JsonResponse
    {
        $conversation->markReadForRole('admin');
        $conversation = SupportConversation::query()
            ->withSupportSummaryData()
            ->findOrFail($conversation->id);

        return response()->json([
            'conversation' => SupportChatPayload::conversationSummary($conversation),
        ]);
    }

    protected function loadConversation(int $conversationId): SupportConversation
    {
        return SupportConversation::query()
            ->withSupportSummaryData()
            ->with([
                'messages.sender:id,name,role',
            ])
            ->findOrFail($conversationId);
    }
}

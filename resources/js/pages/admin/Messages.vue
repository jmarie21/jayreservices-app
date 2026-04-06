<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Toaster } from '@/components/ui/sonner';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AppPageProps, BreadcrumbItem, SupportConversationDetail, SupportConversationSummary, SupportMessage } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { LoaderCircle, MessageSquareText, SendHorizontal } from 'lucide-vue-next';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Messages', href: '/messages' }];

const page = usePage<AppPageProps<{ conversations: SupportConversationSummary[]; initialConversationId: number | null }>>();
const conversations = ref<SupportConversationSummary[]>([...(page.props.conversations ?? [])]);
const selectedConversationId = ref<number | null>(page.props.initialConversationId ?? conversations.value[0]?.id ?? null);
const selectedConversation = ref<SupportConversationDetail | null>(null);
const isLoadingConversation = ref(false);
const isSending = ref(false);
const draftBody = ref('');
const threadViewport = ref<HTMLElement | null>(null);
const activeConversationChannelId = ref<number | null>(null);

const sortConversations = () => {
    conversations.value = [...conversations.value].sort((left, right) => {
        const leftTime = left.last_message_at ? new Date(left.last_message_at).getTime() : 0;
        const rightTime = right.last_message_at ? new Date(right.last_message_at).getTime() : 0;

        return rightTime - leftTime;
    });
};

const scrollThreadToBottom = async () => {
    await nextTick();

    if (!threadViewport.value) {
        return;
    }

    threadViewport.value.scrollTop = threadViewport.value.scrollHeight;
};

const upsertConversationSummary = (summary: SupportConversationSummary) => {
    const existingIndex = conversations.value.findIndex((conversation) => conversation.id === summary.id);

    if (existingIndex >= 0) {
        conversations.value.splice(existingIndex, 1, summary);
    } else {
        conversations.value.push(summary);
    }

    sortConversations();
};

const mergeSelectedConversation = (summary: SupportConversationSummary) => {
    if (!selectedConversation.value || selectedConversation.value.id !== summary.id) {
        return;
    }

    selectedConversation.value = {
        ...selectedConversation.value,
        ...summary,
    };
};

const subscribeToActiveConversation = (conversationId: number) => {
    if (!window.Echo || activeConversationChannelId.value === conversationId) {
        return;
    }

    if (activeConversationChannelId.value) {
        window.Echo.leave(`private-support.conversation.${activeConversationChannelId.value}`);
    }

    window.Echo.private(`support.conversation.${conversationId}`).listen('.support.message.sent', handleConversationEvent);
    activeConversationChannelId.value = conversationId;
};

const markConversationRead = async (conversationId: number) => {
    try {
        const { data } = await axios.post<{ conversation: SupportConversationSummary }>(route('admin.messages.read', conversationId));
        upsertConversationSummary(data.conversation);
        mergeSelectedConversation(data.conversation);
    } catch (error) {
        console.error('Failed to mark admin conversation as read.', error);
    }
};

const loadConversation = async (conversationId: number) => {
    isLoadingConversation.value = true;
    subscribeToActiveConversation(conversationId);

    try {
        const { data } = await axios.get<{ conversation: SupportConversationDetail }>(route('admin.messages.show', conversationId));
        selectedConversation.value = data.conversation;
        selectedConversationId.value = data.conversation.id;
        upsertConversationSummary(data.conversation);
        await scrollThreadToBottom();
    } catch (error) {
        toast.error('Unable to load that conversation right now.');
        console.error('Failed to load support conversation.', error);
    } finally {
        isLoadingConversation.value = false;
    }
};

const handleInboxEvent = (payload: { conversation_id: number; conversation: SupportConversationSummary; message: SupportMessage }) => {
    upsertConversationSummary(payload.conversation);

    if (!selectedConversationId.value) {
        selectedConversationId.value = payload.conversation.id;
        void loadConversation(payload.conversation.id);
        return;
    }

    if (selectedConversationId.value === payload.conversation.id) {
        mergeSelectedConversation(payload.conversation);
    }
};

const handleConversationEvent = async (payload: { conversation_id: number; conversation: SupportConversationSummary; message: SupportMessage }) => {
    upsertConversationSummary(payload.conversation);

    if (!selectedConversation.value || selectedConversation.value.id !== payload.conversation_id) {
        return;
    }

    mergeSelectedConversation(payload.conversation);

    const existingIndex = selectedConversation.value.messages.findIndex((message) => message.id === payload.message.id);

    if (existingIndex >= 0) {
        selectedConversation.value.messages.splice(existingIndex, 1, payload.message);
    } else {
        selectedConversation.value.messages = [...selectedConversation.value.messages, payload.message];
    }

    if (payload.message.sender_id !== page.props.auth.user.id) {
        await markConversationRead(payload.conversation_id);
    }

    await scrollThreadToBottom();
};

const sendMessage = async () => {
    if (!selectedConversationId.value || isSending.value || draftBody.value.trim().length === 0) {
        return;
    }

    isSending.value = true;

    try {
        const { data } = await axios.post<{ conversation: SupportConversationDetail; message: SupportMessage }>(
            route('admin.messages.messages.store', selectedConversationId.value),
            {
                body: draftBody.value,
            },
        );

        selectedConversation.value = data.conversation;
        selectedConversationId.value = data.conversation.id;
        draftBody.value = '';
        upsertConversationSummary(data.conversation);
        await scrollThreadToBottom();
    } catch (error: any) {
        const message = error?.response?.data?.message ?? 'Unable to send your reply right now.';
        toast.error(message);
        console.error('Failed to send admin support chat message.', error);
    } finally {
        isSending.value = false;
    }
};

const selectConversation = async (conversationSummary: SupportConversationSummary) => {
    if (selectedConversationId.value === conversationSummary.id && selectedConversation.value) {
        return;
    }

    selectedConversationId.value = conversationSummary.id;
    await loadConversation(conversationSummary.id);
};

const formatListTimestamp = (value: string | null) => {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    const now = new Date();
    const sameDay = date.toDateString() === now.toDateString();

    return sameDay
        ? date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
        : date.toLocaleDateString([], { month: 'short', day: 'numeric' });
};

const formatMessageTimestamp = (value: string | null) => {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleString([], {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

onMounted(() => {
    if (window.Echo) {
        window.Echo.private('support.admin.inbox').listen('.support.message.sent', handleInboxEvent);
    }

    if (selectedConversationId.value) {
        void loadConversation(selectedConversationId.value);
    }
});

onBeforeUnmount(() => {
    if (!window.Echo) {
        return;
    }

    window.Echo.leave('private-support.admin.inbox');

    if (activeConversationChannelId.value) {
        window.Echo.leave(`private-support.conversation.${activeConversationChannelId.value}`);
    }
});
</script>

<template>
    <Head title="Messages" />
    <Toaster />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Messages</h1>
                    <p class="text-sm text-muted-foreground">Shared support inbox for client conversations.</p>
                </div>
            </div>

            <div v-if="conversations.length === 0" class="flex flex-1 items-center justify-center rounded-2xl border border-dashed bg-white p-8 text-center dark:bg-gray-900">
                <div>
                    <MessageSquareText class="mx-auto mb-4 h-10 w-10 text-muted-foreground" />
                    <h2 class="text-lg font-semibold">No conversations yet</h2>
                    <p class="mt-2 text-sm text-muted-foreground">New client messages will appear here automatically.</p>
                </div>
            </div>

            <div
                v-else
                class="grid flex-1 overflow-hidden rounded-2xl border bg-white shadow-sm dark:bg-gray-900 lg:grid-cols-5"
            >
                <aside class="flex min-h-0 flex-col border-b lg:col-span-1 lg:border-r lg:border-b-0">
                    <div class="border-b px-4 py-3">
                        <h2 class="font-semibold">Client conversations</h2>
                        <p class="text-xs text-muted-foreground">{{ conversations.length }} total</p>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto">
                        <button
                            v-for="conversationSummary in conversations"
                            :key="conversationSummary.id"
                            type="button"
                            class="flex w-full items-start gap-3 border-b px-4 py-3 text-left transition hover:bg-slate-50 dark:hover:bg-slate-800/60"
                            :class="selectedConversationId === conversationSummary.id ? 'bg-slate-100 dark:bg-slate-800' : ''"
                            @click="selectConversation(conversationSummary)"
                        >
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-200 text-sm font-semibold text-slate-700">
                                {{ conversationSummary.client.name.charAt(0).toUpperCase() }}
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">
                                        {{ conversationSummary.client.name }}
                                    </p>
                                    <span class="shrink-0 text-[11px] text-slate-400">
                                        {{ formatListTimestamp(conversationSummary.last_message_at) }}
                                    </span>
                                </div>
                                <p class="truncate text-sm text-slate-500">
                                    {{ conversationSummary.last_message_preview || 'No messages yet' }}
                                </p>
                                <div class="mt-2 flex items-center justify-between gap-2">
                                    <span class="truncate text-[11px] text-slate-400">
                                        {{ conversationSummary.client.email }}
                                    </span>
                                    <span
                                        v-if="conversationSummary.admin_unread_count > 0"
                                        class="inline-flex min-w-6 items-center justify-center rounded-full bg-rose-500 px-1.5 py-0.5 text-[11px] font-semibold text-white"
                                    >
                                        {{ conversationSummary.admin_unread_count }}
                                    </span>
                                </div>
                            </div>
                        </button>
                    </div>
                </aside>

                <section class="flex min-h-0 flex-col lg:col-span-4">
                    <div v-if="selectedConversation" class="border-b px-5 py-4">
                        <h2 class="text-lg font-semibold">{{ selectedConversation.client.name }}</h2>
                        <p class="text-sm text-muted-foreground">{{ selectedConversation.client.email }}</p>
                    </div>

                    <div
                        v-if="isLoadingConversation"
                        class="flex flex-1 items-center justify-center text-sm text-muted-foreground"
                    >
                        <LoaderCircle class="mr-2 h-4 w-4 animate-spin" />
                        Loading conversation...
                    </div>

                    <div v-else-if="selectedConversation" class="flex min-h-0 flex-1 flex-col">
                        <div ref="threadViewport" class="min-h-0 flex-1 space-y-4 overflow-y-auto bg-slate-50 px-5 py-5 dark:bg-slate-950/30">
                            <div
                                v-for="message in selectedConversation.messages"
                                :key="message.id"
                                :class="[
                                    'flex',
                                    message.sender_role === 'admin' ? 'justify-end' : 'justify-start',
                                ]"
                            >
                                <div
                                    :class="[
                                        'max-w-[80%] rounded-2xl px-4 py-3 shadow-sm',
                                        message.sender_role === 'admin'
                                            ? 'rounded-br-md bg-slate-900 text-white'
                                            : 'rounded-bl-md border border-slate-200 bg-white text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100',
                                    ]"
                                >
                                    <div class="mb-1 flex items-center gap-2">
                                        <span class="text-xs font-semibold">{{ message.sender_name }}</span>
                                        <span
                                            :class="[
                                                'text-[11px]',
                                                message.sender_role === 'admin' ? 'text-slate-300' : 'text-slate-400',
                                            ]"
                                        >
                                            {{ formatMessageTimestamp(message.created_at) }}
                                        </span>
                                    </div>
                                    <p class="text-sm whitespace-pre-wrap">{{ message.body }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="border-t bg-white p-4 dark:bg-gray-900">
                            <div class="flex items-end gap-3">
                                <Textarea
                                    v-model="draftBody"
                                    rows="3"
                                    class="min-h-[52px] resize-none"
                                    placeholder="Reply to this client..."
                                    @keydown.enter.exact.prevent="sendMessage"
                                />
                                <Button type="button" class="h-11 shrink-0" :disabled="isSending || draftBody.trim().length === 0" @click="sendMessage">
                                    <LoaderCircle v-if="isSending" class="mr-2 h-4 w-4 animate-spin" />
                                    <SendHorizontal v-else class="mr-2 h-4 w-4" />
                                    Send
                                </Button>
                            </div>
                            <p class="mt-2 text-[11px] text-slate-400">Press Enter to send. Use Shift + Enter for a new line.</p>
                        </div>
                    </div>

                    <div v-else class="flex flex-1 items-center justify-center text-sm text-muted-foreground">
                        Select a conversation to start replying.
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>

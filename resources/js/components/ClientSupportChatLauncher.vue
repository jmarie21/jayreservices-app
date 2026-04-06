<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import type { AppPageProps, SupportChatBootstrap, SupportConversationDetail, SupportConversationSummary, SupportMessage } from '@/types';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { LoaderCircle, MessageCircleMore, SendHorizontal, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const page = usePage<AppPageProps>();
const user = computed(() => page.props.auth?.user ?? null);
const bootstrap = computed<SupportChatBootstrap | null>(() => page.props.supportChatBootstrap ?? null);
const isClient = computed(() => user.value?.role === 'client');
const messagesContainer = ref<HTMLElement | null>(null);
const isOpen = ref(false);
const isLoading = ref(false);
const isSending = ref(false);
const body = ref('');
const errorMessage = ref('');
const conversation = ref<SupportConversationDetail | null>(null);
const unreadCount = ref(bootstrap.value?.unread_count ?? 0);
const conversationId = ref<number | null>(bootstrap.value?.conversation_id ?? null);
const subscribedConversationId = ref<number | null>(null);

const messages = computed(() => conversation.value?.messages ?? []);

const syncBootstrap = (nextBootstrap: SupportChatBootstrap | null | undefined) => {
    unreadCount.value = nextBootstrap?.unread_count ?? 0;

    if (nextBootstrap?.conversation_id) {
        conversationId.value = nextBootstrap.conversation_id;
        subscribeToConversation(nextBootstrap.conversation_id);
    }
};

watch(
    bootstrap,
    (nextBootstrap) => {
        syncBootstrap(nextBootstrap);
    },
    { immediate: true },
);

watch(
    () => messages.value.length,
    async () => {
        if (!isOpen.value) {
            return;
        }

        await nextTick();
        scrollMessagesToBottom();
    },
);

const scrollMessagesToBottom = () => {
    if (!messagesContainer.value) {
        return;
    }

    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
};

const replaceConversation = (nextConversation: SupportConversationDetail) => {
    conversation.value = nextConversation;
    conversationId.value = nextConversation.id;
    unreadCount.value = nextConversation.client_unread_count;
    subscribeToConversation(nextConversation.id);
};

const upsertMessage = (message: SupportMessage) => {
    if (!conversation.value) {
        return;
    }

    const existingIndex = conversation.value.messages.findIndex((currentMessage) => currentMessage.id === message.id);

    if (existingIndex >= 0) {
        conversation.value.messages.splice(existingIndex, 1, message);
        return;
    }

    conversation.value.messages = [...conversation.value.messages, message];
};

const mergeConversationSummary = (summary: SupportConversationSummary) => {
    if (!conversation.value || conversation.value.id !== summary.id) {
        return;
    }

    conversation.value = {
        ...conversation.value,
        ...summary,
    };
};

const markConversationRead = async () => {
    if (!conversationId.value) {
        unreadCount.value = 0;
        return;
    }

    try {
        await axios.post(route('support-chat.read'));
        unreadCount.value = 0;

        if (conversation.value) {
            conversation.value.client_unread_count = 0;
        }
    } catch (error) {
        console.error('Failed to mark support chat as read.', error);
    }
};

async function handleConversationEvent(payload: { conversation_id: number; conversation: SupportConversationSummary; message: SupportMessage }) {
    if (!conversationId.value || payload.conversation_id !== conversationId.value) {
        return;
    }

    unreadCount.value = payload.conversation.client_unread_count;
    mergeConversationSummary(payload.conversation);

    if (conversation.value) {
        upsertMessage(payload.message);
    }

    if (isOpen.value && payload.message.sender_id !== user.value?.id) {
        await markConversationRead();
    }
}

function subscribeToConversation(id: number) {
    if (!window.Echo || subscribedConversationId.value === id) {
        return;
    }

    if (subscribedConversationId.value) {
        window.Echo.leave(`private-support.conversation.${subscribedConversationId.value}`);
    }

    window.Echo.private(`support.conversation.${id}`).listen('.support.message.sent', handleConversationEvent);
    subscribedConversationId.value = id;
}

const loadConversation = async () => {
    if (!isClient.value) {
        return;
    }

    isLoading.value = true;
    errorMessage.value = '';

    try {
        const { data } = await axios.get<{ conversation: SupportConversationDetail | null; bootstrap: SupportChatBootstrap }>(route('support-chat.show'));

        if (data.conversation) {
            replaceConversation(data.conversation);
        }

        syncBootstrap(data.bootstrap);
        unreadCount.value = 0;
    } catch (error) {
        errorMessage.value = 'Unable to load support chat right now.';
        console.error('Failed to load support chat conversation.', error);
    } finally {
        isLoading.value = false;
    }
};

const toggleOpen = async () => {
    isOpen.value = !isOpen.value;

    if (!isOpen.value) {
        return;
    }

    await loadConversation();
    await nextTick();
    scrollMessagesToBottom();
};

const sendMessage = async () => {
    if (isSending.value || body.value.trim().length === 0) {
        return;
    }

    isSending.value = true;
    errorMessage.value = '';

    try {
        const { data } = await axios.post<{
            conversation: SupportConversationDetail;
            message: SupportMessage;
            bootstrap: SupportChatBootstrap;
        }>(route('support-chat.messages.store'), {
            body: body.value,
        });

        replaceConversation(data.conversation);
        syncBootstrap(data.bootstrap);
        unreadCount.value = 0;
        body.value = '';
    } catch (error: any) {
        errorMessage.value = error?.response?.data?.message ?? 'Unable to send your message right now.';
        console.error('Failed to send support chat message.', error);
    } finally {
        isSending.value = false;
    }
};

const displaySenderName = (message: SupportMessage) => {
    return message.sender_role === 'admin' ? 'Admin' : message.sender_name;
};

const formatTimestamp = (value: string | null) => {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    const now = new Date();
    const sameDay = now.toDateString() === date.toDateString();

    return sameDay
        ? date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
        : date.toLocaleDateString([], { month: 'short', day: 'numeric' });
};

onBeforeUnmount(() => {
    if (subscribedConversationId.value && window.Echo) {
        window.Echo.leave(`private-support.conversation.${subscribedConversationId.value}`);
    }
});
</script>

<template>
    <div v-if="isClient" class="pointer-events-none fixed right-4 bottom-4 z-50 flex max-w-[calc(100vw-2rem)] flex-col items-end gap-3">
        <transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-2 opacity-0"
        >
            <div
                v-if="isOpen"
                class="pointer-events-auto flex h-[min(32rem,calc(100vh-7rem))] w-[min(24rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
            >
                <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Support chat</p>
                        <p class="text-xs text-slate-500">Chat directly with an admin</p>
                    </div>
                    <Button type="button" size="icon" variant="ghost" class="h-8 w-8" @click="isOpen = false">
                        <X class="h-4 w-4" />
                    </Button>
                </div>

                <div ref="messagesContainer" class="flex-1 space-y-4 overflow-y-auto bg-slate-50 px-4 py-4">
                    <div v-if="isLoading" class="flex h-full items-center justify-center text-sm text-slate-500">
                        <LoaderCircle class="mr-2 h-4 w-4 animate-spin" />
                        Loading chat...
                    </div>

                    <div v-else-if="messages.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-6 text-center text-sm text-slate-500">
                        Ask a question any time and an admin can reply here.
                    </div>

                    <div
                        v-for="message in messages"
                        :key="message.id"
                        :class="[
                            'flex',
                            message.sender_role === 'client' ? 'justify-end' : 'justify-start',
                        ]"
                    >
                        <div
                            :class="[
                                'max-w-[85%] rounded-2xl px-3 py-2 shadow-sm',
                                message.sender_role === 'client'
                                    ? 'rounded-br-md bg-slate-900 text-white'
                                    : 'rounded-bl-md border border-slate-200 bg-white text-slate-900',
                            ]"
                        >
                            <div class="mb-1 flex items-center gap-2">
                                <span class="text-xs font-semibold">
                                    {{ displaySenderName(message) }}
                                </span>
                                <span
                                    :class="[
                                        'text-[11px]',
                                        message.sender_role === 'client' ? 'text-slate-300' : 'text-slate-400',
                                    ]"
                                >
                                    {{ formatTimestamp(message.created_at) }}
                                </span>
                            </div>
                            <p class="text-sm whitespace-pre-wrap">{{ message.body }}</p>
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-200 bg-white p-3">
                    <p v-if="errorMessage" class="mb-2 text-xs text-rose-600">
                        {{ errorMessage }}
                    </p>
                    <div class="flex items-end gap-2">
                        <Textarea
                            v-model="body"
                            rows="2"
                            class="min-h-[44px] resize-none"
                            placeholder="Type your message..."
                            @keydown.enter.exact.prevent="sendMessage"
                        />
                        <Button type="button" size="icon" class="h-11 w-11 shrink-0" :disabled="isSending || body.trim().length === 0" @click="sendMessage">
                            <LoaderCircle v-if="isSending" class="h-4 w-4 animate-spin" />
                            <SendHorizontal v-else class="h-4 w-4" />
                        </Button>
                    </div>
                    <p class="mt-2 text-[11px] text-slate-400">Press Enter to send. Use Shift + Enter for a new line.</p>
                </div>
            </div>
        </transition>

        <Button
            type="button"
            class="pointer-events-auto relative h-14 w-14 rounded-full shadow-lg"
            @click="toggleOpen"
        >
            <MessageCircleMore class="h-6 w-6" />
            <span
                v-if="unreadCount > 0"
                class="absolute -top-1 -right-1 inline-flex min-w-6 items-center justify-center rounded-full bg-rose-500 px-1.5 py-1 text-[11px] font-semibold text-white"
            >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </Button>
    </div>
</template>

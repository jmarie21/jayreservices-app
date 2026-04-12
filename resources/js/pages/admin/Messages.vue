<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Toaster } from '@/components/ui/sonner';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AppPageProps, BreadcrumbItem, SupportConversationDetail, SupportConversationSummary, SupportMessage } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import imageCompression from 'browser-image-compression';
import { ImagePlus, LoaderCircle, MessageSquareText, SendHorizontal, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
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

type PendingChatAttachment = { key: string; file: File; previewUrl: string; kind: 'image' | 'video' };
const maxChatAttachments = 3;
const maxChatImageBytes = 5 * 1024 * 1024;
const maxChatVideoBytes = 25 * 1024 * 1024;
const allowedImageTypes = new Set(['image/jpeg', 'image/png', 'image/webp']);
const allowedVideoTypes = new Set(['video/mp4', 'video/quicktime', 'video/webm']);
const pendingAttachments = ref<PendingChatAttachment[]>([]);
const adminAttachmentInput = ref<HTMLInputElement | null>(null);
const isCompressing = ref(false);
const totalAttachments = computed(() => pendingAttachments.value.length);
const hasContent = computed(() => draftBody.value.trim().length > 0 || totalAttachments.value > 0);

const openAttachmentPicker = () => adminAttachmentInput.value?.click();

const addChatAttachments = async (files: File[]) => {
    let remaining = maxChatAttachments - totalAttachments.value;
    if (remaining <= 0) {
        toast.error(`You can attach up to ${maxChatAttachments} files per message.`);
        return;
    }

    for (const file of files) {
        const isImage = allowedImageTypes.has(file.type);
        const isVideo = allowedVideoTypes.has(file.type);

        if (!isImage && !isVideo) {
            toast.error(`"${file.name}" is not supported. Use JPG, PNG, WEBP, MP4, MOV, or WEBM.`);
            continue;
        }

        if (isImage && file.size > maxChatImageBytes) {
            toast.error(`"${file.name}" exceeds the 5 MB image limit.`);
            continue;
        }

        if (isVideo && file.size > maxChatVideoBytes) {
            toast.error(`"${file.name}" exceeds the 25 MB video limit.`);
            continue;
        }

        if (remaining <= 0) {
            toast.error(`You can attach up to ${maxChatAttachments} files per message.`);
            break;
        }

        let finalFile = file;
        if (isImage) {
            try {
                isCompressing.value = true;
                const compressed = await imageCompression(file, { maxSizeMB: 1, maxWidthOrHeight: 1920, useWebWorker: true, fileType: file.type });
                finalFile = new File([compressed], file.name, { type: file.type, lastModified: file.lastModified });
            } catch { /* use original */ } finally {
                isCompressing.value = false;
            }
        }

        pendingAttachments.value.push({
            key: `${file.name}-${file.size}-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
            file: finalFile,
            previewUrl: URL.createObjectURL(finalFile),
            kind: isImage ? 'image' : 'video',
        });
        remaining--;
    }
};

const removePendingAttachment = (key: string) => {
    const item = pendingAttachments.value.find((a) => a.key === key);
    if (item) URL.revokeObjectURL(item.previewUrl);
    pendingAttachments.value = pendingAttachments.value.filter((a) => a.key !== key);
};

const clearPendingAttachments = () => {
    pendingAttachments.value.forEach((a) => URL.revokeObjectURL(a.previewUrl));
    pendingAttachments.value = [];
    if (adminAttachmentInput.value) adminAttachmentInput.value.value = '';
};

const handleAdminFileSelection = (event: Event) => {
    const target = event.target as HTMLInputElement;
    addChatAttachments(Array.from(target.files ?? []));
    target.value = '';
};

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
    if (!selectedConversationId.value || isSending.value || !hasContent.value) {
        return;
    }

    isSending.value = true;

    try {
        const formData = new FormData();
        formData.append('body', draftBody.value);
        pendingAttachments.value.forEach((a, i) => formData.append(`attachments[${i}]`, a.file));

        const { data } = await axios.post<{ conversation: SupportConversationDetail; message: SupportMessage }>(
            route('admin.messages.messages.store', selectedConversationId.value),
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } },
        );

        selectedConversation.value = data.conversation;
        selectedConversationId.value = data.conversation.id;
        draftBody.value = '';
        clearPendingAttachments();
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
    clearPendingAttachments();

    if (!window.Echo) {
        return;
    }

    // Do NOT leave support.admin.inbox here — AppSidebar manages that channel globally

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
                                    <p v-if="message.body" class="text-sm whitespace-pre-wrap">{{ message.body }}</p>
                                    <div v-if="message.attachments?.length" class="mt-2 grid grid-cols-2 gap-1.5">
                                        <template v-for="attachment in message.attachments" :key="attachment.id">
                                            <video
                                                v-if="attachment.mime_type?.startsWith('video/')"
                                                :src="attachment.url"
                                                controls
                                                controlslist="nodownload"
                                                preload="metadata"
                                                playsinline
                                                class="h-28 w-full rounded-lg object-cover"
                                                @error="($event.target as HTMLVideoElement).replaceWith(Object.assign(document.createElement('div'), { className: 'flex h-28 w-full items-center justify-center rounded-lg bg-gray-100 text-xs text-gray-400', textContent: 'Expired' }))"
                                            />
                                            <img
                                                v-else
                                                :src="attachment.url"
                                                :alt="attachment.original_name ?? 'attachment'"
                                                class="h-28 w-full cursor-pointer rounded-lg object-cover"
                                                @error="($event.target as HTMLImageElement).replaceWith(Object.assign(document.createElement('div'), { className: 'flex h-28 w-full items-center justify-center rounded-lg bg-gray-100 text-xs text-gray-400', textContent: 'Expired' }))"
                                            />
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="border-t bg-white p-4 dark:bg-gray-900">
                            <input
                                ref="adminAttachmentInput"
                                type="file"
                                accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm"
                                multiple
                                class="hidden"
                                @change="handleAdminFileSelection"
                            />
                            <div v-if="pendingAttachments.length" class="mb-3 grid grid-cols-4 gap-2">
                                <div v-for="a in pendingAttachments" :key="a.key" class="group relative overflow-hidden rounded-lg border">
                                    <video v-if="a.kind === 'video'" :src="a.previewUrl" preload="metadata" playsinline muted class="h-20 w-full object-cover" />
                                    <img v-else :src="a.previewUrl" class="h-20 w-full object-cover" />
                                    <button type="button" @click="removePendingAttachment(a.key)" class="absolute top-1 right-1 rounded-full bg-black/70 p-0.5 text-white hover:bg-black">
                                        <X class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                            <div class="flex items-end gap-3">
                                <Button type="button" size="icon" variant="ghost" class="h-11 w-11 shrink-0" :disabled="totalAttachments >= maxChatAttachments || isCompressing" @click="openAttachmentPicker">
                                    <ImagePlus class="h-4 w-4" />
                                </Button>
                                <Textarea
                                    v-model="draftBody"
                                    rows="3"
                                    class="min-h-[52px] resize-none"
                                    placeholder="Reply to this client..."
                                    @keydown.enter.exact.prevent="sendMessage"
                                />
                                <Button type="button" class="h-11 shrink-0" :disabled="isSending || !hasContent" @click="sendMessage">
                                    <LoaderCircle v-if="isSending" class="mr-2 h-4 w-4 animate-spin" />
                                    <SendHorizontal v-else class="mr-2 h-4 w-4" />
                                    Send
                                </Button>
                            </div>
                            <p class="mt-2 text-[11px] text-slate-400">Images (5 MB) or videos (25 MB). Press Enter to send.</p>
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

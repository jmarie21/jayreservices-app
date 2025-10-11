<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { AppPageProps, Projects } from '@/types';
import { Paginated } from '@/types/app-page-prop';
import { linkify } from '@/utils/linkify';
import { mapStatusForClient } from '@/utils/statusMapper';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    isOpen: boolean;
    onClose: () => void;
    project: Projects;
    role: 'client' | 'editor' | 'admin';
}>();

const page = usePage<AppPageProps<{ projects: Paginated<Projects>; filters?: any }>>();
const comments = computed(() => {
    const projectFromPage = page.props.projects.data.find((p) => p.id === props.project.id);
    return projectFromPage?.comments ?? [];
});

const statusLabels: Record<'pending' | 'in_progress' | 'completed', string> = {
    pending: 'Pending',
    in_progress: 'In Progress',
    completed: 'Completed',
};

const mappedStatus = computed(() => mapStatusForClient(props.project.status));

const outputLink = ref(props.project.output_link || '');

function openNativeFullscreen(event: MouseEvent) {
    const img = event.target as HTMLElement;

    // Make sure we're targeting an element
    if (!img) return;

    // Enter fullscreen
    if (img.requestFullscreen) {
        img.requestFullscreen().catch((err) => {
            console.error('Failed to enter fullscreen:', err);
        });
    }
}

const saveOutputLink = () => {
    if (!outputLink.value) return;

    const routeName = props.role === 'admin' ? 'projects.admin_update' : 'editor.projects.update';

    router.patch(
        route(routeName, props.project.id),
        { output_link: outputLink.value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                props.project.output_link = outputLink.value;
            },
        },
    );
};

const commentForm = useForm({
    body: '',
    image: null as File | null,
});

const previewUrl = computed(() => (commentForm.image ? URL.createObjectURL(commentForm.image) : null));

const removeImage = () => {
    commentForm.image = null;
};

const getS3Url = (path: string) => {
    return `https://jayre-services.s3.us-east-1.amazonaws.com/${path}`;
};

const submitComment = () => {
    if (!commentForm.body && !commentForm.image) return;

    console.log('Submitting comment with:', {
        hasBody: !!commentForm.body,
        hasImage: !!commentForm.image,
        imageDetails: commentForm.image
            ? {
                  name: commentForm.image.name,
                  size: commentForm.image.size,
                  type: commentForm.image.type,
              }
            : null,
    });

    commentForm.post(route('projects.comments.store', props.project.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            console.log('Comment submitted successfully');
            commentForm.reset('body', 'image');
        },
        onError: (errors) => {
            console.error('Comment submission failed:', errors);
        },
        onFinish: () => {
            console.log('Request finished');
        },
    });
};

const handlePaste = (event: ClipboardEvent) => {
    if (!event.clipboardData) return;

    for (const item of event.clipboardData.items) {
        if (item.type.startsWith('image/')) {
            const file = item.getAsFile();
            if (file) {
                console.log('File captured:', file.name, file.size, file.type);
                commentForm.image = file;
            }
        }
    }
};
</script>

<template>
    <Dialog :open="isOpen" @update:open="onClose">
        <DialogContent class="!max-w-7xl overflow-hidden p-0">
            <div class="flex h-[80vh]">
                <!-- Left Column: Project Details (Scrollable) -->
                <div class="flex flex-1 flex-col bg-gray-50">
                    <ScrollArea class="min-h-0 flex-1 space-y-6 p-6">
                        <DialogHeader>
                            <DialogTitle class="mb-4 text-3xl font-bold">{{ project.project_name }}</DialogTitle>
                        </DialogHeader>

                        <!-- Project Overview -->
                        <div class="mb-4 space-y-3 rounded-xl bg-white p-5 shadow">
                            <h2 class="text-lg font-semibold">Project Details</h2>

                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-500">Service</span>
                                <span class="text-base font-semibold">{{ project.service?.name }}</span>
                            </div>

                            <div v-if="role === 'admin'" class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-500">Editor</span>
                                <span class="text-base font-semibold">{{ project.editor?.name || 'Unassigned' }}</span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-500">Style</span>
                                <span class="text-base font-semibold">{{ project.style }}</span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-500">Format</span>
                                <span class="text-base font-semibold">{{ project.format || 'N/A' }}</span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-500">Camera</span>
                                <span class="text-base font-semibold">{{ project.camera || 'N/A' }}</span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-500">Quality</span>
                                <span class="text-base font-semibold">{{ project.quality || 'N/A' }}</span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-500">Music</span>
                                <span class="text-base font-semibold">{{ project.music || 'N/A' }}</span>
                            </div>

                            <div v-if="project.music_link" class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-500">Music Link</span>
                                <a :href="project.music_link" target="_blank" class="text-blue-500 underline">View</a>
                            </div>

                            <div class="flex items-center justify-between" v-if="role === 'admin'">
                                <span class="text-sm font-medium text-gray-500">Company</span>
                                <span class="text-base font-semibold">{{ project.company_name }}</span>
                            </div>

                            <div class="flex items-center justify-between" v-if="role === 'admin'">
                                <span class="text-sm font-medium text-gray-500">Contact</span>
                                <span class="text-base font-semibold">{{ project.contact }}</span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-500">Status</span>
                                <span
                                    class="rounded-full px-2 py-1 text-xs"
                                    :class="{
                                        'bg-green-100 text-green-700': mappedStatus === 'completed',
                                        'bg-yellow-100 text-yellow-700': mappedStatus === 'in_progress',
                                        'bg-red-200 text-gray-600': mappedStatus === 'pending',
                                    }"
                                >
                                    {{ statusLabels[mappedStatus] }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-500">Total Price</span>
                                <span class="text-lg font-bold text-green-600">
                                    {{ role === 'editor' ? `₱${project.editor_price}` : `$${project.total_price}` }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-500">Created</span>
                                <span class="text-base">
                                    {{
                                        new Date(project.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
                                    }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-500">Last Updated</span>
                                <span class="text-base">
                                    {{
                                        new Date(project.updated_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
                                    }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-500">With Agent</span>
                                <span class="text-base">
                                    {{ project.with_agent === true ? 'Yes' : 'No' }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-500">With per property line</span>
                                <span class="text-base">
                                    {{ project.per_property === true ? 'Yes' : 'No' }}
                                </span>
                            </div>

                            <!-- Extra Fields -->
                            <div v-if="project.extra_fields" class="space-y-4">
                                <!-- Captions -->
                                <div v-if="project.extra_fields.captions?.length" class="flex items-start justify-between">
                                    <span class="text-sm font-medium text-gray-500">Captions</span>
                                    <ul class="list-inside list-disc space-y-1 text-right text-sm text-gray-700">
                                        <li v-for="(caption, index) in project.extra_fields.captions" :key="index">
                                            {{ caption }}
                                        </li>
                                    </ul>
                                </div>

                                <!-- Effects -->
                                <div v-if="project.extra_fields.effects?.length" class="flex items-start justify-between">
                                    <span class="text-sm font-medium text-gray-500">Effects</span>
                                    <ul class="list-inside list-disc space-y-1 text-right text-sm text-gray-700">
                                        <li v-for="(effect, index) in project.extra_fields.effects" :key="index">
                                            {{ effect }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="mb-4 rounded-xl bg-white p-5 shadow">
                            <h1 class="text-lg font-bold">Notes</h1>
                            <p class="whitespace-pre-line text-gray-700">{{ project.notes || 'No notes available.' }}</p>
                        </div>

                        <!-- Links Section -->
                        <div class="mb-4 space-y-4 rounded-xl bg-white p-5 shadow">
                            <h1 class="text-lg font-bold">Links</h1>
                            <a
                                :href="project.file_link"
                                target="_blank"
                                class="block w-full rounded-lg bg-blue-500 py-2 text-center text-white transition hover:bg-blue-600"
                            >
                                📁 Raw Files
                            </a>
                            <a
                                v-if="project.output_link"
                                :href="project.output_link"
                                target="_blank"
                                class="block w-full rounded-lg bg-green-500 py-2 text-center text-white transition hover:bg-green-600"
                            >
                                🎬 Finished Output
                            </a>

                            <!-- Editor Upload Output Link -->
                            <div v-if="role === 'editor' || role === 'admin'" class="space-y-2">
                                <Input v-model="outputLink" placeholder="Paste output link..." />
                                <Button class="w-full" @click="saveOutputLink">Upload Output Link</Button>
                            </div>
                        </div>
                    </ScrollArea>
                </div>

                <!-- Right Column: Comments -->
                <div class="flex w-[460px] flex-col border-l bg-white">
                    <div class="border-b p-4">
                        <h3 class="text-lg font-semibold">Comments</h3>
                    </div>

                    <!-- Scrollable comments area -->
                    <ScrollArea class="min-h-0 flex-1 p-4">
                        <div v-if="comments.length === 0" class="text-sm text-gray-500">No comments yet.</div>
                        <div v-else class="space-y-4">
                            <div v-for="comment in comments" :key="comment.id" class="flex items-start space-x-3">
                                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-gray-200 text-xs font-bold">
                                    {{
                                        role === 'admin'
                                            ? comment.user?.name?.charAt(0).toUpperCase()
                                            : comment.user?.role === 'client'
                                              ? 'C'
                                              : comment.user?.role === 'editor'
                                                ? 'E'
                                                : comment.user?.role === 'admin'
                                                  ? 'A'
                                                  : '?'
                                    }}
                                </div>
                                <div class="break-words">
                                    <p class="text-sm font-semibold">
                                        {{
                                            role === 'admin'
                                                ? comment.user?.name
                                                : comment.user?.role === 'client'
                                                  ? 'Client'
                                                  : comment.user?.role === 'editor'
                                                    ? 'Editor'
                                                    : comment.user?.role === 'admin'
                                                      ? 'Admin'
                                                      : 'Unknown'
                                        }}
                                    </p>
                                    <p class="text-sm break-words whitespace-pre-line text-gray-700" v-html="linkify(comment.body)"></p>

                                    <div v-if="comment.image_url" class="mt-2">
                                        <img
                                            :src="getS3Url(comment.image_url)"
                                            alt="comment screenshot"
                                            class="max-w-[300px] cursor-pointer rounded-lg border transition hover:opacity-80"
                                            @click="openNativeFullscreen"
                                        />
                                    </div>

                                    <span class="text-xs text-gray-400">
                                        {{ new Date(comment.created_at).toLocaleString() }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </ScrollArea>

                    <!-- Comment Input -->
                    <div class="flex flex-col border-t p-4">
                        <!-- Image preview -->
                        <div v-if="previewUrl" class="relative mb-2 inline-block">
                            <img :src="previewUrl" alt="screenshot preview" class="max-w-[150px] cursor-pointer rounded-lg border hover:opacity-80" />
                            <button
                                type="button"
                                @click="removeImage"
                                class="absolute -top-2 -right-2 rounded-full bg-black/70 px-1.5 py-0.5 text-xs text-white hover:bg-black"
                            >
                                ✕
                            </button>
                        </div>

                        <!-- Textarea + Send button -->
                        <div class="flex items-end space-x-2">
                            <textarea
                                v-model="commentForm.body"
                                placeholder="Write a comment or paste a screenshot..."
                                class="max-h-[150px] min-h-[40px] flex-1 resize-none overflow-y-auto rounded border p-2"
                                @input="
                                    (e: Event) => {
                                        const target = e.target as HTMLTextAreaElement;
                                        target.style.height = 'auto';
                                        target.style.height = target.scrollHeight + 'px';
                                    }
                                "
                                @keydown.enter.exact.prevent="submitComment"
                                @paste="handlePaste"
                            ></textarea>

                            <Button size="sm" @click="submitComment" :disabled="commentForm.processing || (!commentForm.body && !commentForm.image)">
                                Send
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

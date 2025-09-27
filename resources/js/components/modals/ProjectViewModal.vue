<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { AppPageProps, Projects } from '@/types';
import { Paginated } from '@/types/app-page-prop';
import { mapStatusForClient } from '@/utils/statusMapper';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Textarea } from '../ui/textarea';

const props = defineProps<{
    isOpen: boolean;
    onClose: () => void;
    project: import('@/types').Projects & {
        comments?: Array<{
            id: number;
            body: string;
            created_at: string;
            user: { id: number; name: string };
        }>;
    };
    role: 'client' | 'editor' | 'admin';
}>();

const page = usePage<AppPageProps<{ projects: Paginated<Projects>; filters?: any }>>();
// const comments = ref(props.project.comments ? [...props.project.comments] : []);
// const comments = computed(() => page.props.projects.data.flatMap((project) => project.comments ?? []));
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

const saveOutputLink = () => {
    if (!outputLink.value) return;

    router.patch(
        route('editor.projects.update', props.project.id),
        { output_link: outputLink.value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                props.project.output_link = outputLink.value; // update local view
            },
        },
    );
};

const commentForm = useForm({
    body: '',
});

const submitComment = () => {
    if (!commentForm.body) return;

    commentForm.post(route('projects.comments.store', props.project.id), {
        preserveScroll: true,
        onSuccess: (page) => {
            const newComment = (page.props.flash as any)?.newComment;
            if (newComment) {
                comments.value.push(newComment); // ✅ reactive update
            }
            commentForm.reset('body');
        },
    });
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

                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-500">Company</span>
                                <span class="text-base font-semibold">{{ project.company_name }}</span>
                            </div>

                            <div class="flex items-center justify-between">
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
                            <div v-if="role === 'editor'" class="space-y-2">
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
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-200 text-xs font-bold">
                                    {{ comment.user.name.charAt(0).toUpperCase() }}
                                </div>
                                <div class="break-words">
                                    <p class="text-sm font-semibold">{{ comment.user.name }}</p>
                                    <p class="text-sm whitespace-pre-line text-gray-700">{{ comment.body }}</p>
                                    <span class="text-xs text-gray-400">
                                        {{ new Date(comment.created_at).toLocaleString() }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </ScrollArea>

                    <!-- New comment input -->
                    <div class="flex items-center space-x-2 border-t p-4">
                        <Textarea
                            v-model="commentForm.body"
                            placeholder="Write a comment..."
                            class="max-h-[150px] min-h-[40px] flex-1 resize-none overflow-y-auto"
                            rows="1"
                            @input="
                                (e: Event) => {
                                    const target = e.target as HTMLTextAreaElement;
                                    target.style.height = 'auto';
                                    target.style.height = target.scrollHeight + 'px';
                                }
                            "
                            @keydown.enter.exact.prevent="() => submitComment()"
                        />
                        <Button size="sm" @click="submitComment" :disabled="commentForm.processing">Send</Button>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

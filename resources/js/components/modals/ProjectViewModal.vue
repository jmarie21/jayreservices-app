<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { mapStatusForClient } from '@/utils/statusMapper';
import { computed, ref } from 'vue';

const props = defineProps<{
    isOpen: boolean;
    onClose: () => void;
    project: import('@/types').Projects;
}>();

const statusLabels: Record<'pending' | 'in_progress' | 'completed', string> = {
    pending: 'Pending',
    in_progress: 'In Progress',
    completed: 'Completed',
};

const mappedStatus = computed(() => mapStatusForClient(props.project.status));

const newComment = ref('');
const comments = ref([
    { id: 1, author: 'John Doe', avatar: '', text: 'Great work so far!', time: '2h ago' },
    { id: 2, author: 'Jane Smith', avatar: '', text: 'Waiting for the final output link.', time: '1h ago' },
]);
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

                            <div class="flex items-center justify-between">
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
                                <span class="text-lg font-bold text-green-600">${{ project.total_price }}</span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-500">Created</span>
                                <span class="text-base">{{ new Date(project.created_at).toLocaleString() }}</span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-500">Last Updated</span>
                                <span class="text-base">{{ new Date(project.updated_at).toLocaleString() }}</span>
                            </div>
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
                        </div>

                        <!-- Notes -->
                        <div class="mb-4 rounded-xl bg-white p-5 shadow">
                            <h1 class="text-lg font-bold">Notes</h1>
                            <p class="whitespace-pre-line text-gray-700">{{ project.notes || 'No notes available.' }}</p>
                        </div>
                    </ScrollArea>
                </div>

                <!-- Right Column: Comments -->
                <div class="flex w-[350px] flex-col border-l bg-white">
                    <div class="border-b p-4">
                        <h3 class="text-lg font-semibold">Comments</h3>
                    </div>

                    <!-- Scrollable comments area -->
                    <ScrollArea class="min-h-0 flex-1 p-4">
                        <div class="space-y-4">
                            <div v-for="comment in comments" :key="comment.id" class="flex items-start space-x-3">
                                <Avatar class="h-8 w-8">
                                    <AvatarImage :src="comment.avatar" />
                                    <AvatarFallback>{{ comment.author[0] }}</AvatarFallback>
                                </Avatar>
                                <div class="break-words">
                                    <p class="text-sm font-semibold">{{ comment.author }}</p>
                                    <p class="text-sm text-gray-700">{{ comment.text }}</p>
                                    <span class="text-xs text-gray-400">{{ comment.time }}</span>
                                </div>
                            </div>
                        </div>
                    </ScrollArea>

                    <!-- New comment input -->
                    <div class="flex items-center space-x-2 border-t p-4">
                        <Input v-model="newComment" placeholder="Write a comment..." class="flex-1" />
                        <Button size="sm" @click="addComment">Send</Button>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

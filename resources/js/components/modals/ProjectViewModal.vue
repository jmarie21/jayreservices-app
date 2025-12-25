<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { AppPageProps, Projects } from '@/types';
import { Paginated } from '@/types/app-page-prop';
import { linkify } from '@/utils/linkify';
import { mapStatusForClient } from '@/utils/statusMapper';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { MoreVertical, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '../ui/alert-dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '../ui/dropdown-menu';
import { Input } from '../ui/input';

const props = defineProps<{
    isOpen: boolean;
    onClose: () => void;
    project: Projects;
    role: 'client' | 'editor' | 'admin';
}>();

const page = usePage<AppPageProps<{ projects: Paginated<Projects>; filters?: any }>>();
const user = usePage().props.auth.user;
const comments = computed(() => {
    const projectFromPage = page.props.projects.data.find((p) => p.id === props.project.id);
    return projectFromPage?.comments ?? [];
});

const linkedNotes = computed(() => linkify(props.project.notes));

const statusLabels: Record<'pending' | 'in_progress' | 'completed', string> = {
    pending: 'Pending',
    in_progress: 'In Progress',
    completed: 'Completed',
};

const renderMusicLink = (music: string) => {
    // If it contains a valid URL, let linkify make it clickable
    const urlRegex = /^(https?:\/\/|www\.)[^\s]+$/i;
    if (urlRegex.test(music.trim())) {
        return linkify(music.trim());
    }
    // Otherwise, return plain text (escaped for safety)
    return music.replace(/</g, '&lt;').replace(/>/g, '&gt;');
};

const mappedStatus = computed(() => mapStatusForClient(props.project.status));

const outputLinks = ref<string[]>(
    Array.isArray(props.project.output_link) ? [...props.project.output_link] : props.project.output_link ? [props.project.output_link] : [''],
);

watch(
    () => props.project,
    (newProject) => {
        outputLinks.value = Array.isArray(newProject.output_link)
            ? [...newProject.output_link]
            : newProject.output_link
              ? [newProject.output_link]
              : [''];
    },
    { deep: true },
);

const addOutputLink = () => {
    outputLinks.value.push('');
};

const removeOutputLink = (index: number) => {
    outputLinks.value.splice(index, 1);
    if (outputLinks.value.length === 0) {
        outputLinks.value.push('');
    }
};

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

const saveOutputLinks = () => {
    const filteredLinks = outputLinks.value.filter((link) => link.trim() !== '');
    if (filteredLinks.length === 0) return;

    const routeName = props.role === 'admin' ? 'projects.admin_update' : 'editor.projects.update';

    router.patch(
        route(routeName, props.project.id),
        { output_link: filteredLinks },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                props.project.output_link = filteredLinks;
                toast.success('Output links updated successfully!');
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

const markForRevision = (projectId: number) => {
    router.put(
        route('projects.updateStatus', projectId),
        {
            status: 'revision',
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Status updated successfully!', {
                    description: 'The status was updated successfully!',
                    position: 'top-right',
                });
                console.log('success');
            },
        },
    );
};

const submitComment = () => {
    if (!commentForm.body && !commentForm.image) return;

    // If editing an existing comment
    if (editingCommentId.value) {
        const formData = new FormData();
        formData.append('body', commentForm.body);
        if (commentForm.image) formData.append('image', commentForm.image);
        formData.append('_method', 'PUT');

        router.post(route('comments.update', editingCommentId.value), formData, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                editingCommentId.value = null;
                commentForm.reset('body', 'image');
            },
            onError: (errors) => {
                console.error('Edit failed:', errors);
            },
        });
        return;
    }

    // Otherwise, create a new comment
    commentForm.post(route('projects.comments.store', props.project.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            commentForm.reset('body', 'image');
        },
        onError: (errors) => {
            console.error('Comment submission failed:', errors);
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

const showDeleteDialog = ref(false);
const commentToDelete = ref<any>(null);
const editingCommentId = ref<number | null>(null);

const canManage = (comment: any) => {
    const user = page.props.auth.user;
    return comment.user_id === user.id || user.role === 'admin';
};

const editComment = (comment: any) => {
    editingCommentId.value = comment.id;
    commentForm.body = comment.body;
    window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' }); // optional
};

const openDeleteDialog = (comment: any) => {
    commentToDelete.value = comment;
    showDeleteDialog.value = true;
};

const confirmDelete = () => {
    if (!commentToDelete.value) return;

    router.delete(route('comments.destroy', commentToDelete.value.id), {
        onFinish: () => {
            showDeleteDialog.value = false;
            commentToDelete.value = null;
        },
        preserveScroll: true,
    });
};
</script>

<template>
    <Dialog :open="isOpen" @update:open="onClose">
        <DialogContent class="max-h-[90vh] !w-[95vw] !max-w-7xl overflow-y-auto rounded-xl p-0 sm:p-2 md:p-0">
            <div class="flex h-[80vh] flex-col md:flex-row">
                <!-- Left Column: Project Details (Scrollable) -->
                <div class="flex flex-1 flex-col bg-gray-50">
                    <ScrollArea class="min-h-0 flex-1 space-y-6 p-4 sm:p-6">
                        <DialogHeader>
                            <DialogTitle class="mb-4 text-2xl font-bold sm:text-3xl">
                                {{ project.project_name }}
                            </DialogTitle>
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
                                <span class="text-sm font-medium text-gray-500">Music</span>
                                <span class="text-right text-base" v-html="renderMusicLink(project.music_link)"></span>
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
                                        new Date(project.created_at).toLocaleString('en-US', {
                                            year: 'numeric',
                                            month: 'short',
                                            day: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit',
                                        })
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
                                    {{ project.per_property === true ? `Yes (${project.per_property_count || 0}x)` : 'No' }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-500">Rush</span>
                                <span class="text-base">
                                    {{ project.rush === true ? 'Yes' : 'No' }}
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
                                <div v-if="project.extra_fields.effects?.length || project.extra_fields.custom_effects" class="flex items-start justify-between">
                                    <span class="text-sm font-medium text-gray-500">Effects</span>
                                    <ul class="list-inside list-disc space-y-1 text-right text-sm text-gray-700">
                                        <li v-for="(effect, index) in project.extra_fields.effects" :key="index">
                                            {{ typeof effect === 'string' ? effect : `${effect.id} (x${effect.quantity})` }}
                                        </li>
                                        <li v-for="(customEffect, index) in (typeof project.extra_fields.custom_effects === 'string' ? JSON.parse(project.extra_fields.custom_effects) : project.extra_fields.custom_effects)" :key="'custom-' + index">
                                            {{ customEffect.description }} (Custom)
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="mb-4 rounded-xl bg-white p-5 shadow">
                            <h1 class="text-lg font-bold">Notes</h1>
                            <p class="break-words whitespace-pre-wrap text-gray-700" v-html="linkedNotes || 'No notes available.'"></p>
                        </div>

                        <!-- Links Section -->
                        <div class="mb-4 space-y-6 rounded-xl bg-white p-5 shadow">
                            <h1 class="text-lg font-bold">Links</h1>
                            <a
                                :href="project.file_link"
                                target="_blank"
                                class="block w-full rounded-lg bg-blue-500 py-2 text-center text-white transition hover:bg-blue-600"
                            >
                                📁 Raw Files
                            </a>

                            <template v-if="project.output_link && project.output_link.length > 0">
                                <a
                                    v-for="(link, index) in project.output_link"
                                    :key="index"
                                    :href="link"
                                    target="_blank"
                                    class="block w-full rounded-lg bg-green-500 py-2 text-center text-white transition hover:bg-green-600"
                                >
                                    🎬 Finished Output {{ project.output_link.length > 1 ? index + 1 : '' }}
                                </a>
                            </template>

                            <!-- Editor Upload Output Link -->
                            <div v-if="role === 'editor' || role === 'admin'" class="space-y-4 border-t pt-4">
                                <h1 class="text-lg font-bold">Manage Output Links</h1>
                                <div v-for="(link, index) in outputLinks" :key="index" class="flex items-center space-x-2">
                                    <Input v-model="outputLinks[index]" placeholder="Paste output link..." />
                                    <Button variant="destructive" size="icon" @click="removeOutputLink(index)" v-if="outputLinks.length > 1">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                                <Button variant="outline" class="w-full" @click="addOutputLink">
                                    <Plus class="mr-2 h-4 w-4" /> Add More Link
                                </Button>
                                <Button class="w-full" @click="saveOutputLinks">Save Output Links</Button>
                            </div>
                        </div>
                    </ScrollArea>
                </div>

                <!-- Right Column: Comments -->
                <div class="flex w-full flex-col border-t bg-white md:w-[520px] md:border-t-0 md:border-l">
                    <div class="border-b p-4">
                        <h3 class="text-lg font-semibold">Comments</h3>
                    </div>

                    <!-- Scrollable comments area -->
                    <ScrollArea class="min-h-0 flex-1 p-3 sm:p-4">
                        <div v-if="comments.length === 0" class="text-sm text-gray-500">No comments yet.</div>
                        <div v-else class="space-y-4">
                            <div v-for="comment in comments" :key="comment.id" class="flex items-start justify-between space-x-3">
                                <!-- Left side: avatar + content -->
                                <div class="flex space-x-3">
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

                                        <p
                                            class="overflow-wrap-anywhere text-sm break-words whitespace-pre-line text-gray-700"
                                            style="word-break: break-word; overflow-wrap: anywhere"
                                            v-html="linkify(comment.body)"
                                        ></p>

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

                                <!-- Right side: three-dot menu -->
                                <div v-if="canManage(comment)" class="relative">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <button class="rounded p-1 text-gray-500 hover:bg-gray-100" aria-label="Comment options">
                                                <MoreVertical class="h-5 w-5" />
                                            </button>
                                        </DropdownMenuTrigger>

                                        <DropdownMenuContent align="end" class="w-36">
                                            <DropdownMenuItem @click="editComment(comment)"> <Pencil class="mr-2 h-4 w-4" /> Edit </DropdownMenuItem>
                                            <DropdownMenuItem @click="openDeleteDialog(comment)" class="text-red-600 focus:text-red-700">
                                                <Trash2 class="mr-2 h-4 w-4" /> Delete
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
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

                            <Button
                                size="sm"
                                variant="outline"
                                v-if="editingCommentId"
                                @click="
                                    () => {
                                        editingCommentId = null;
                                        commentForm.reset('body', 'image');
                                    }
                                "
                            >
                                Cancel
                            </Button>

                            <div class="flex flex-col space-y-4">
                                <Button
                                    v-if="role === 'client'"
                                    @click="markForRevision(project.id)"
                                    variant="destructive"
                                    :disabled="mapStatusForClient(project.status) !== 'completed'"
                                >
                                    Mark for revision
                                </Button>

                                <Button
                                    size="sm"
                                    @click="submitComment"
                                    :disabled="commentForm.processing || (!commentForm.body && !commentForm.image)"
                                >
                                    {{ editingCommentId ? 'Update' : 'Send' }}
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>

    <!-- Delete Comment Confirmation Dialog -->
    <AlertDialog v-model:open="showDeleteDialog">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete Comment</AlertDialogTitle>
                <AlertDialogDescription> Are you sure you want to delete this comment? This action cannot be undone. </AlertDialogDescription>
            </AlertDialogHeader>

            <AlertDialogFooter>
                <AlertDialogCancel @click="showDeleteDialog = false"> Cancel </AlertDialogCancel>
                <AlertDialogAction @click="confirmDelete" class="bg-red-600 text-white hover:bg-red-700"> Delete </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>

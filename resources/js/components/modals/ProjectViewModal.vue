<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { AppPageProps, Comment, CommentAttachment, Projects } from '@/types';
import { Paginated } from '@/types/app-page-prop';
import { linkify } from '@/utils/linkify';
import { mapStatusForClient } from '@/utils/statusMapper';
import { router, useForm, usePage } from '@inertiajs/vue3';
import imageCompression from 'browser-image-compression';
import { ImagePlus, MoreVertical, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
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
    project: Projects;
    role: 'client' | 'editor' | 'admin';
}>();
const emit = defineEmits<{
    (e: 'close'): void;
}>();

const page = usePage<AppPageProps<{ projects: Paginated<Projects>; filters?: any }>>();
const user = usePage().props.auth.user;
const comments = computed<Comment[]>(() => {
    const projectFromPage = page.props.projects.data.find((p) => p.id === props.project.id);
    return projectFromPage?.comments ?? [];
});

const linkedNotes = computed(() => linkify(props.project.notes));
const extraFields = computed<Record<string, any>>(() => {
    return props.project.extra_fields && typeof props.project.extra_fields === 'object' ? props.project.extra_fields : {};
});

const availableServiceAddons = computed(() => props.project.service?.pricing_data?.addons ?? []);

const selectedAddonLines = computed(() => {
    const lines: string[] = [];
    const seen = new Set<string>();

    const pushLine = (label?: string | null, quantity = 1, showQuantity = false) => {
        const safeLabel = String(label ?? '').trim();
        if (!safeLabel) {
            return;
        }

        const normalizedKey = safeLabel.toLowerCase();

        if (seen.has(normalizedKey)) {
            return;
        }

        seen.add(normalizedKey);
        lines.push(showQuantity || quantity > 1 ? `${safeLabel} (${quantity}x)` : safeLabel);
    };

    if (props.project.with_agent) {
        pushLine('With Agent');
    }

    if (props.project.per_property) {
        pushLine('Per Property Line', Number(props.project.per_property_count ?? extraFields.value.per_property_quantity ?? 1), true);
    }

    if (props.project.rush) {
        pushLine('Rush');
    }

    if (Array.isArray(extraFields.value.service_addons) && extraFields.value.service_addons.length > 0) {
        extraFields.value.service_addons.forEach((addon: any) => {
            const matchedAddon = availableServiceAddons.value.find((serviceAddon: any) => {
                return Number(serviceAddon?.id ?? 0) === Number(addon?.addon_id ?? 0)
                    || normalizeAddonValue(serviceAddon?.slug) === normalizeAddonValue(addon?.slug)
                    || normalizeAddonValue(serviceAddon?.name) === normalizeAddonValue(addon?.name);
            });

            const shouldShowQuantity = Boolean(matchedAddon?.has_quantity || matchedAddon?.addon_type === 'quantity');
            pushLine(addon?.name ?? addon?.slug, Number(addon?.quantity ?? 1), shouldShowQuantity);
        });
    } else {
        if (Array.isArray(extraFields.value.captions)) {
            extraFields.value.captions.forEach((caption: string) => pushLine(caption));
        }

        if (Array.isArray(extraFields.value.effects)) {
            extraFields.value.effects.forEach((effect: any) => {
                if (typeof effect === 'string') {
                    pushLine(effect);
                    return;
                }

                pushLine(effect?.name ?? effect?.id ?? effect?.slug, Number(effect?.quantity ?? 1), Number(effect?.quantity ?? 0) > 0);
            });
        }
    }

    normalizeCustomEffects(extraFields.value.custom_effects).forEach((customEffect: any) => {
        pushLine(customEffect?.description ?? customEffect?.name, Number(customEffect?.quantity ?? 1), Number(customEffect?.quantity ?? 0) > 0);
    });

    return lines;
});

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

const outputLinks = ref<{ name: string; link: string }[]>(
    Array.isArray(props.project.output_link) && props.project.output_link.length > 0
        ? props.project.output_link.map((item: any) => (typeof item === 'string' ? { name: '', link: item } : { ...item }))
        : [{ name: '', link: '' }],
);

function normalizeCustomEffects(value: unknown) {
    if (Array.isArray(value)) {
        return value;
    }

    if (typeof value !== 'string' || value.trim() === '') {
        return [];
    }

    try {
        const parsed = JSON.parse(value);
        return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
        console.error('Failed to parse custom effects', error);
        return [];
    }
}

function normalizeAddonValue(value?: string | null) {
    return String(value ?? '')
        .trim()
        .toLowerCase()
        .replace(/&/g, 'and')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

watch(
    () => props.project,
    (newProject) => {
        outputLinks.value =
            Array.isArray(newProject.output_link) && newProject.output_link.length > 0
                ? newProject.output_link.map((item: any) => (typeof item === 'string' ? { name: '', link: item } : { ...item }))
                : [{ name: '', link: '' }];
    },
    { deep: true },
);

const addOutputLink = () => {
    outputLinks.value.push({ name: '', link: '' });
};

const removeOutputLink = (index: number) => {
    outputLinks.value.splice(index, 1);
    if (outputLinks.value.length === 0) {
        outputLinks.value.push({ name: '', link: '' });
    }
};

const showDeleteDialog = ref(false);
const commentToDelete = ref<Comment | null>(null);
const editingCommentId = ref<number | null>(null);

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
    const filteredLinks = outputLinks.value.filter((item) => item.link.trim() !== '');
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

type PendingCommentAttachment = {
    key: string;
    file: File;
    previewUrl: string;
    kind: 'image' | 'video';
};

const maxCommentAttachments = 3;
const maxCommentImageBytes = 5 * 1024 * 1024;
const maxCommentVideoBytes = 25 * 1024 * 1024;
const allowedCommentImageTypes = new Set(['image/jpeg', 'image/png', 'image/webp']);
const allowedCommentVideoTypes = new Set(['video/mp4', 'video/quicktime', 'video/webm']);

const commentForm = useForm({
    body: '',
});

const attachmentInput = ref<HTMLInputElement | null>(null);
const pendingAttachments = ref<PendingCommentAttachment[]>([]);
const retainedAttachments = ref<CommentAttachment[]>([]);
const isSubmittingComment = ref(false);
const isCompressingImage = ref(false);

const previewAttachments = computed(() => [
    ...retainedAttachments.value.map((attachment) => ({
        key: `existing-${attachment.id}-${attachment.position}-${attachment.is_legacy ? 'legacy' : 'db'}`,
        url: attachment.url,
        mimeType: attachment.mime_type ?? null,
        originalName: attachment.original_name ?? 'Existing attachment',
        existing: true as const,
        kind: (attachment.mime_type?.startsWith('video/') ? 'video' : 'image') as 'image' | 'video',
        attachment,
    })),
    ...pendingAttachments.value.map((pending) => ({
        key: pending.key,
        url: pending.previewUrl,
        mimeType: pending.file.type,
        originalName: pending.file.name,
        existing: false as const,
        kind: pending.kind,
        pending,
    })),
]);

const totalSelectedAttachments = computed(() => retainedAttachments.value.length + pendingAttachments.value.length);
const hasCommentContent = computed(() => commentForm.body.trim().length > 0 || totalSelectedAttachments.value > 0);

const revokePendingAttachment = (pending: PendingCommentAttachment) => {
    URL.revokeObjectURL(pending.previewUrl);
};

const clearPendingAttachments = () => {
    pendingAttachments.value.forEach(revokePendingAttachment);
    pendingAttachments.value = [];

    if (attachmentInput.value) {
        attachmentInput.value.value = '';
    }
};

const resetCommentComposer = () => {
    editingCommentId.value = null;
    commentForm.reset('body');
    retainedAttachments.value = [];
    clearPendingAttachments();
};

const openAttachmentPicker = () => {
    attachmentInput.value?.click();
};

const buildPendingAttachment = (file: File, kind: 'image' | 'video'): PendingCommentAttachment => ({
    key: `${file.name}-${file.size}-${file.lastModified}-${Math.random().toString(36).slice(2, 8)}`,
    file,
    previewUrl: URL.createObjectURL(file),
    kind,
});

const addPendingAttachments = async (files: File[]) => {
    let remainingSlots = maxCommentAttachments - totalSelectedAttachments.value;

    if (remainingSlots <= 0) {
        toast.error(`You can attach up to ${maxCommentAttachments} files per comment.`);
        return;
    }

    const accepted: PendingCommentAttachment[] = [];

    for (const file of files) {
        const isImage = allowedCommentImageTypes.has(file.type);
        const isVideo = allowedCommentVideoTypes.has(file.type);

        if (!isImage && !isVideo) {
            toast.error(`"${file.name}" is not a supported file type. Please upload JPG, PNG, WEBP images or MP4, MOV, WEBM videos.`);
            continue;
        }

        if (isImage && file.size > maxCommentImageBytes) {
            toast.error(`"${file.name}" exceeds the 5 MB image limit. Please choose a smaller image.`);
            continue;
        }

        if (isVideo && file.size > maxCommentVideoBytes) {
            toast.error(`"${file.name}" exceeds the 25 MB video limit. Please choose a shorter or lower-resolution clip.`);
            continue;
        }

        if (remainingSlots <= 0) {
            toast.error(`You can attach up to ${maxCommentAttachments} files per comment.`);
            break;
        }

        if (isImage) {
            try {
                isCompressingImage.value = true;
                const compressed = await imageCompression(file, {
                    maxSizeMB: 1,
                    maxWidthOrHeight: 1920,
                    useWebWorker: true,
                    fileType: file.type,
                });
                const compressedFile = new File([compressed], file.name, { type: file.type, lastModified: file.lastModified });
                accepted.push(buildPendingAttachment(compressedFile, 'image'));
            } catch {
                accepted.push(buildPendingAttachment(file, 'image'));
            } finally {
                isCompressingImage.value = false;
            }
        } else {
            accepted.push(buildPendingAttachment(file, 'video'));
        }

        remainingSlots--;
    }

    if (accepted.length > 0) {
        pendingAttachments.value = [...pendingAttachments.value, ...accepted];
    }
};

const handleFileSelection = (event: Event) => {
    const target = event.target as HTMLInputElement;
    addPendingAttachments(Array.from(target.files ?? []));
    target.value = '';
};

const removePendingAttachment = (key: string) => {
    const pending = pendingAttachments.value.find((item) => item.key === key);

    if (pending) {
        revokePendingAttachment(pending);
    }

    pendingAttachments.value = pendingAttachments.value.filter((item) => item.key !== key);
};

const removeRetainedAttachment = (attachmentToRemove: CommentAttachment) => {
    retainedAttachments.value = retainedAttachments.value.filter((attachment) => {
        return !(attachment.id === attachmentToRemove.id && Boolean(attachment.is_legacy) === Boolean(attachmentToRemove.is_legacy));
    });
};

const buildCommentFormData = () => {
    const formData = new FormData();

    formData.append('body', commentForm.body);
    pendingAttachments.value.forEach((pending, index) => {
        formData.append(`attachments[${index}]`, pending.file);
    });

    if (editingCommentId.value) {
        retainedAttachments.value
            .filter((attachment) => !attachment.is_legacy)
            .forEach((attachment, index) => {
                formData.append(`keep_attachment_ids[${index}]`, String(attachment.id));
            });

        formData.append(
            'keep_legacy_image',
            retainedAttachments.value.some((attachment) => attachment.is_legacy) ? '1' : '0',
        );
        formData.append('_method', 'PUT');
    }

    return formData;
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
    if (!hasCommentContent.value || isSubmittingComment.value) return;

    isSubmittingComment.value = true;

    const endpoint = editingCommentId.value
        ? route('comments.update', editingCommentId.value)
        : route('projects.comments.store', props.project.id);

    router.post(endpoint, buildCommentFormData(), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            resetCommentComposer();
        },
        onError: (errors) => {
            console.error('Comment submission failed:', errors);
            const message = Object.values(errors)[0];
            toast.error(typeof message === 'string' ? message : 'Comment submission failed.');
        },
        onFinish: () => {
            isSubmittingComment.value = false;
        },
    });
};

const handlePaste = (event: ClipboardEvent) => {
    if (!event.clipboardData) return;

    const pastedFiles: File[] = [];
    for (const item of event.clipboardData.items) {
        if (item.type.startsWith('image/') || item.type.startsWith('video/')) {
            const file = item.getAsFile();
            if (file) {
                pastedFiles.push(file);
            }
        }
    }

    if (pastedFiles.length > 0) {
        addPendingAttachments(pastedFiles);
    }
};

const canManage = (comment: Comment) => {
    const user = page.props.auth.user;
    return comment.user_id === user.id || user.role === 'admin';
};

const editComment = (comment: Comment) => {
    editingCommentId.value = comment.id;
    commentForm.body = comment.body ?? '';
    clearPendingAttachments();
    retainedAttachments.value = [...(comment.attachments ?? [])].sort((a, b) => a.position - b.position);
};

const openDeleteDialog = (comment: Comment) => {
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

onBeforeUnmount(() => {
    clearPendingAttachments();
});
</script>

<template>
    <Dialog :open="isOpen" @update:open="(open) => !open && emit('close')">
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

                            <div v-if="selectedAddonLines.length" class="flex items-start justify-between">
                                <span class="text-sm font-medium text-gray-500">Add-Ons</span>
                                <ul class="list-inside list-disc space-y-1 text-right text-sm text-gray-700">
                                    <li v-for="(addon, index) in selectedAddonLines" :key="index">
                                        {{ addon }}
                                    </li>
                                </ul>
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
                                    v-for="(item, index) in project.output_link"
                                    :key="index"
                                    :href="item.link"
                                    target="_blank"
                                    class="block w-full rounded-lg bg-green-500 py-2 text-center text-white transition hover:bg-green-600"
                                >
                                    🎬 {{ item.name || `Finished Output ${project.output_link.length > 1 ? index + 1 : ''}` }}
                                </a>
                            </template>

                            <!-- Editor Upload Output Link -->
                            <div v-if="role === 'editor' || role === 'admin'" class="space-y-4 border-t pt-4">
                                <h1 class="text-lg font-bold">Manage Output Links</h1>
                                <div v-for="(item, index) in outputLinks" :key="index" class="space-y-2 rounded-lg border p-3">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium">Link #{{ index + 1 }}</span>
                                        <Button variant="destructive" size="icon" @click="removeOutputLink(index)" v-if="outputLinks.length > 1">
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                    <Input v-model="outputLinks[index].name" placeholder="Link Name (e.g. Version 1, Final Edit)" />
                                    <Input v-model="outputLinks[index].link" placeholder="Paste output link..." />
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

                                        <div v-if="comment.attachments?.length" class="mt-2 space-y-2">
                                            <template
                                                v-for="attachment in comment.attachments"
                                                :key="`${comment.id}-${attachment.id}-${attachment.position}`"
                                            >
                                                <video
                                                    v-if="attachment.mime_type?.startsWith('video/')"
                                                    :src="attachment.url"
                                                    controls
                                                    controlslist="nodownload"
                                                    preload="metadata"
                                                    playsinline
                                                    class="max-h-64 w-full rounded-lg border object-contain"
                                                    @error="($event.target as HTMLVideoElement).replaceWith(Object.assign(document.createElement('div'), { className: 'flex h-28 w-full items-center justify-center rounded-lg border bg-gray-100 text-xs text-gray-400', textContent: 'Attachment expired' }))"
                                                />
                                                <img
                                                    v-else
                                                    :src="attachment.url"
                                                    :alt="attachment.original_name ?? 'comment attachment'"
                                                    class="max-h-52 w-full cursor-pointer rounded-lg border object-contain transition hover:opacity-80"
                                                    @click="openNativeFullscreen"
                                                    @error="($event.target as HTMLImageElement).replaceWith(Object.assign(document.createElement('div'), { className: 'flex h-28 w-full items-center justify-center rounded-lg border bg-gray-100 text-xs text-gray-400', textContent: 'Attachment expired' }))"
                                                />
                                            </template>
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
                        <input
                            ref="attachmentInput"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm"
                            multiple
                            class="hidden"
                            @change="handleFileSelection"
                        />

                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <p class="text-xs text-gray-500">Up to 3 files: images (5 MB) or videos (25 MB). JPG, PNG, WEBP, MP4, MOV, WEBM.</p>
                            <Button type="button" size="sm" variant="outline" @click="openAttachmentPicker" :disabled="totalSelectedAttachments >= maxCommentAttachments || isCompressingImage">
                                <ImagePlus class="mr-2 h-4 w-4" />
                                {{ isCompressingImage ? 'Compressing...' : 'Upload' }}
                            </Button>
                        </div>
                        <div v-if="previewAttachments.length" class="mb-3 grid grid-cols-3 gap-2">
                            <div
                                v-for="attachment in previewAttachments"
                                :key="attachment.key"
                                class="group relative overflow-hidden rounded-lg border bg-muted/30"
                            >
                                <video
                                    v-if="attachment.kind === 'video'"
                                    :src="attachment.url"
                                    preload="metadata"
                                    playsinline
                                    muted
                                    class="h-24 w-full object-cover"
                                />
                                <img
                                    v-else
                                    :src="attachment.url"
                                    :alt="attachment.originalName"
                                    class="h-24 w-full cursor-pointer object-cover transition group-hover:opacity-80"
                                    @click="openNativeFullscreen"
                                />
                            <button
                                type="button"
                                @click="
                                    attachment.existing
                                        ? removeRetainedAttachment(attachment.attachment)
                                        : removePendingAttachment(attachment.pending.key)
                                "
                                class="absolute top-2 right-2 rounded-full bg-black/70 p-1 leading-none text-[0px] transition hover:bg-black"
                            >
                                <X class="h-3.5 w-3.5 text-white" />
                                ✕
                            </button>
                            </div>
                        </div>

                        <!-- Textarea + Send button -->
                        <div class="flex items-end space-x-2">
                            <textarea
                                v-model="commentForm.body"
                                placeholder="Write a comment, paste screenshots, or upload files..."
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
                                @click="resetCommentComposer"
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
                                    :disabled="isSubmittingComment || !hasCommentContent"
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

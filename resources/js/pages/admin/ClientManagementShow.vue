<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { AppPageProps, type BreadcrumbItem } from '@/types';
import { ClientExtraRequest, EditorLevel } from '@/types/app-page-prop';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ExternalLink, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

type ClientShow = {
    id: number;
    name: string;
    email: string;
    recommended_editor_level: EditorLevel | null;
    dedicated_editor_rules_count: number;
    extra_requests: ClientExtraRequest[];
};

const pageProps = usePage<
    AppPageProps<{
        client: ClientShow;
    }>
>().props;

const client = computed(() => pageProps.client);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Client Management', href: '/client-management' },
    { title: client.value.name, href: `/client-management/${client.value.id}` },
];

const levelOptions: { value: EditorLevel | null; label: string }[] = [
    { value: null, label: 'Unassigned' },
    { value: 'senior', label: 'Senior' },
    { value: 'mid', label: 'Mid' },
    { value: 'junior', label: 'Junior' },
];

function updateRecommendedLevel(level: EditorLevel | null) {
    const previousLevel = client.value.recommended_editor_level;
    client.value.recommended_editor_level = level;

    router.patch(
        route('admin.client-levels.assign'),
        { level, user_ids: [client.value.id] },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                client.value.recommended_editor_level = previousLevel;
                toast.error('Something went wrong', {
                    description: `Could not update ${client.value.name}'s recommended level. Please try again.`,
                });
            },
        },
    );
}

const requestModalOpen = ref(false);
const editingRequest = ref<ClientExtraRequest | null>(null);
const requestForm = ref({ title: '', link: '', description: '' });

function openAddRequestModal() {
    editingRequest.value = null;
    requestForm.value = { title: '', link: '', description: '' };
    requestModalOpen.value = true;
}

function openEditRequestModal(request: ClientExtraRequest) {
    editingRequest.value = request;
    requestForm.value = { title: request.title, link: request.link ?? '', description: request.description ?? '' };
    requestModalOpen.value = true;
}

function applyFreshExtraRequests(page: { props: { client?: ClientShow } }) {
    if (page.props.client?.extra_requests) {
        client.value.extra_requests = page.props.client.extra_requests;
    }
}

function submitRequestForm() {
    const payload = {
        title: requestForm.value.title,
        link: requestForm.value.link || null,
        description: requestForm.value.description || null,
    };

    const onSuccess = (page: { props: { client?: ClientShow } }) => {
        applyFreshExtraRequests(page);
        requestModalOpen.value = false;
    };

    const onError = () => {
        toast.error('Something went wrong', {
            description: 'Could not save this request. Please check the form and try again.',
        });
    };

    if (editingRequest.value) {
        router.put(route('admin.client-management.extra-requests.update', [client.value.id, editingRequest.value.id]), payload, {
            preserveScroll: true,
            onSuccess,
            onError,
        });
    } else {
        router.post(route('admin.client-management.extra-requests.store', client.value.id), payload, {
            preserveScroll: true,
            onSuccess,
            onError,
        });
    }
}

const deleteTarget = ref<ClientExtraRequest | null>(null);

function confirmDeleteRequest(request: ClientExtraRequest) {
    deleteTarget.value = request;
}

function deleteRequest() {
    if (!deleteTarget.value) {
        return;
    }

    router.delete(route('admin.client-management.extra-requests.destroy', [client.value.id, deleteTarget.value.id]), {
        preserveScroll: true,
        onSuccess: applyFreshExtraRequests,
        onFinish: () => {
            deleteTarget.value = null;
        },
    });
}
</script>

<template>
    <Head :title="`${client.name} - Client Management`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4">
            <div class="space-y-1">
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">{{ client.name }}</h1>
                <p class="text-sm text-slate-500">{{ client.email }}</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-900">Recommended Editor Level</h2>
                <p class="mt-1 text-xs text-slate-500">Just a legend on the project pages — does not restrict editor assignment.</p>
                <Select :modelValue="client.recommended_editor_level" @update:modelValue="(value) => updateRecommendedLevel(value as EditorLevel | null)">
                    <SelectTrigger class="mt-3 w-48">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="option in levelOptions" :key="option.value ?? 'unassigned'" :value="option.value">{{ option.label }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Dedicated Editors</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ client.dedicated_editor_rules_count }} rule(s) configured. Hard-enforced when assigning projects to this client.
                    </p>
                </div>
                <Button size="sm" variant="outline" as-child>
                    <Link :href="route('admin.client-levels.dedicated-editors.edit', client.id)">Manage dedicated editors →</Link>
                </Button>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Extra Requests</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Custom requests that aren't part of the standard services (e.g. a specific colorgrading style). Always shown on
                            this client's project details.
                        </p>
                    </div>
                    <Button size="sm" @click="openAddRequestModal"><Plus class="mr-1 size-4" /> Add</Button>
                </div>

                <div v-if="client.extra_requests.length === 0" class="mt-4 text-sm text-slate-400">No extra requests on file.</div>

                <div v-else class="mt-4 space-y-3">
                    <div
                        v-for="request in client.extra_requests"
                        :key="request.id"
                        class="flex items-start justify-between gap-4 rounded-lg border border-slate-200 p-3"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-medium text-slate-900">{{ request.title }}</p>
                                <a
                                    v-if="request.link"
                                    :href="request.link"
                                    target="_blank"
                                    class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:underline"
                                >
                                    Link <ExternalLink class="size-3" />
                                </a>
                            </div>
                            <p v-if="request.description" class="text-sm whitespace-pre-wrap text-slate-600">{{ request.description }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <Button size="icon" variant="ghost" @click="openEditRequestModal(request)">
                                <Pencil class="size-4" />
                            </Button>
                            <Button size="icon" variant="ghost" class="text-rose-600 hover:text-rose-700" @click="confirmDeleteRequest(request)">
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Dialog :open="requestModalOpen" @update:open="(value) => (requestModalOpen = value)">
            <DialogContent class="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle>{{ editingRequest ? 'Edit Extra Request' : 'Add Extra Request' }}</DialogTitle>
                </DialogHeader>

                <div class="space-y-3">
                    <div class="space-y-1.5">
                        <Label>Title</Label>
                        <Input v-model="requestForm.title" placeholder="e.g. Colorgrading style" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Link (optional)</Label>
                        <Input v-model="requestForm.link" placeholder="Paste a reference link..." />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Description (optional)</Label>
                        <Textarea v-model="requestForm.description" placeholder="Describe the request..." rows="4" />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="requestModalOpen = false">Cancel</Button>
                    <Button :disabled="!requestForm.title.trim()" @click="submitRequestForm">Save</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="!!deleteTarget" @update:open="(value) => !value && (deleteTarget = null)">
            <DialogContent class="sm:max-w-[400px]">
                <DialogHeader>
                    <DialogTitle>Delete this request?</DialogTitle>
                </DialogHeader>
                <p class="text-sm text-slate-600">"{{ deleteTarget?.title }}" will be permanently removed. This cannot be undone.</p>
                <DialogFooter>
                    <Button variant="outline" @click="deleteTarget = null">Cancel</Button>
                    <Button variant="destructive" @click="deleteRequest">Delete</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<script setup lang="ts">
import BasicStyleForm from '@/components/forms/BasicStyleForm.vue';
import DeluxeStyleForm from '@/components/forms/DeluxeStyleForm.vue';
import LuxuryStyleForm from '@/components/forms/LuxuryStyleForm.vue';
import PremiumStyleForm from '@/components/forms/PremiumStyleForm.vue';
import TalkingHeadsForm from '@/components/forms/TalkingHeadsForm.vue';

import WeddingBasicForm from '@/components/forms/WeddingBasicForm.vue';
import WeddingPremiumForm from '@/components/forms/WeddingPremiumForm.vue';
import WeddingLuxuryForm from '@/components/forms/WeddingLuxuryForm.vue';

import EventBasicForm from '@/components/forms/EventBasicForm.vue';
import EventPremiumForm from '@/components/forms/EventPremiumForm.vue';
import EventLuxuryForm from '@/components/forms/EventLuxuryForm.vue';

import ConstructionBasicForm from '@/components/forms/ConstructionBasicForm.vue';
import ConstructionPremiumForm from '@/components/forms/ConstructionPremiumForm.vue';
import ConstructionLuxuryForm from '@/components/forms/ConstructionLuxuryForm.vue';

import ProjectViewModal from '@/components/modals/ProjectViewModal.vue';
import ProjectFilters from '@/components/ProjectFilters.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination, PaginationContent, PaginationItem, PaginationNext, PaginationPrevious } from '@/components/ui/pagination';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Toaster } from '@/components/ui/sonner';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { AppPageProps, Projects, type BreadcrumbItem } from '@/types';
import { Paginated } from '@/types/app-page-prop';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

type Status = 'todo' | 'in_progress' | 'for_qa' | 'done_qa' | 'sent_to_client' | 'revision' | 'revision_completed' | 'backlog';
type Priority = 'urgent' | 'high' | 'normal' | 'low';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Project Management', href: '/project-mgmt' }];

const pageProps = usePage<
    AppPageProps<{
        client: { id: number; name: string; email: string };
        projects: Paginated<Projects>;
        editors: { id: number; name: string }[];
        filters?: { status?: string; date_from?: string; date_to?: string; search?: string; editor_id: string };
    }>
>().props;

const page = usePage<
    AppPageProps<{
        client: { id: number; name: string; email: string };
        projects: Paginated<Projects>;
        editors: { id: number; name: string }[];
        filters?: { status?: string; date_from?: string; date_to?: string; search?: string; editor_id: string };
    }>
>();

const { client, editors } = pageProps;
const projects = computed(() => pageProps.projects);

const showModal = ref(false);
const selectedProject = ref<Projects | null>(null);
const editProject = ref<Projects | null>(null);

const showDeleteModal = ref(false);
const projectToDelete = ref<Projects | null>(null);

const editorPrices = ref<Record<number, number | undefined>>({});
watch(
    projects,
    (newProjects) => {
        newProjects.data.forEach((p) => {
            if (!(p.id in editorPrices.value)) {
                editorPrices.value[p.id] = p.editor_price ?? undefined;
            }
        });
    },
    { immediate: true },
);

const form = useForm<{
    editor_id: number | null;
    status: Status;
    editor_price: number | null;
    total_price: number | null;
    priority: Priority;
}>({
    editor_id: null,
    status: 'todo',
    editor_price: null,
    total_price: null,
    priority: 'normal',
});

// Status labels for badges
const statusLabels: Record<Status, string> = {
    todo: 'To Do',
    in_progress: 'In Progress',
    for_qa: 'For QA',
    done_qa: 'Done QA',
    revision: 'Revision',
    revision_completed: 'Revision Completed',
    backlog: 'Backlog',
    sent_to_client: 'Sent to Client',
};

const openViewModal = (project: Projects) => {
    selectedProject.value = project;
    showModal.value = true;
};

const closeViewModal = () => {
    showModal.value = false;
    selectedProject.value = null;
};

const totalPrices = ref<Record<number, number | undefined>>({});
watch(
    projects,
    (newProjects) => {
        newProjects.data.forEach((p) => {
            if (!(p.id in totalPrices.value)) {
                totalPrices.value[p.id] = p.total_price ?? undefined;
            }
        });
    },
    { immediate: true },
);

// Optimistic update for editor/status
const updateProject = <K extends keyof typeof form>(projectId: number, field: K, value: any) => {
    router.patch(
        route('projects.admin_update', projectId),
        { [field]: value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                // Optimistic update in frontend
                const project = projects.value.data.find((p) => p.id === projectId);
                if (project) (project as any)[field] = value;
            },
        },
    );
};

// Update project price
const updatePrice = (projectId: number, value: number | undefined) => {
    if (value !== undefined && value !== null) {
        router.patch(
            route('projects.update-price', projectId), // 👈 this hits your new backend method
            { total_price: value },
            {
                preserveScroll: true,
                onSuccess: () => {
                    const project = projects.value.data.find((p) => p.id === projectId);
                    if (project) project.total_price = value;
                    console.log('Total price updated!');
                },
            },
        );
    }
};

const confirmDelete = (project: Projects) => {
    projectToDelete.value = project;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    projectToDelete.value = null;
    showDeleteModal.value = false;
};

const isEditModalOpen = ref(false);
const basePrice = ref<number>(0);

function openEditModal(project: Projects) {
    editProject.value = project;
    isEditModalOpen.value = true;
    basePrice.value = project.service?.price ?? 0; // example: if your project has service.price
}

function closeEditModal() {
    isEditModalOpen.value = false;
    editProject.value = null;
}

const deleteProject = () => {
    if (!projectToDelete.value) return;

    router.delete(route('projects.destroy', projectToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            // Optimistic update
            const index = projects.value.data.findIndex((p) => p.id === projectToDelete.value?.id);
            if (index !== -1) {
                projects.value.data.splice(index, 1);
            }
            closeDeleteModal();
            toast.success('Project deleted successfully.', { position: 'top-right' });
        },
    });
};

// // Format date
// function formatLocalDate(d: Date) {
//     const year = d.getFullYear();
//     const month = String(d.getMonth() + 1).padStart(2, '0'); // Months are 0-indexed
//     const day = String(d.getDate()).padStart(2, '0');
//     return `${year}-${month}-${day}`;
// }

// // Get current week
// function getCurrentWeekRange() {
//     const now = new Date();
//     const day = now.getDay(); // 0 (Sun) - 6 (Sat)

//     // Start of week = Sunday
//     const sunday = new Date(now);
//     sunday.setDate(now.getDate() - day);
//     sunday.setHours(0, 0, 0, 0);

//     const saturday = new Date(sunday);
//     saturday.setDate(sunday.getDate() + 6);
//     saturday.setHours(23, 59, 59, 999);

//     return { date_from: formatLocalDate(sunday), date_to: formatLocalDate(saturday) };
// }

// const weekRange = getCurrentWeekRange();

// Filters state
const filters = ref({
    status: pageProps.filters?.status || '',
    date_from: pageProps.filters?.date_from || '',
    date_to: pageProps.filters?.date_to || '',
    search: pageProps.filters?.search || '',
    editor_id: pageProps.filters?.editor_id || '', // Add this line
});

// Apply filters
const applyFilters = (newFilters?: typeof filters.value) => {
    const query = newFilters || filters.value;
    // Update local filters to keep v-model in sync
    filters.value = { ...filters.value, ...query };

    router.get(route('client.projects', { client: client.id }), filters.value, {
        preserveScroll: true,
        replace: true,
    });
};

// Pagination with filters
const goToPage = (pageNumber: number) => {
    router.get(route('client.projects', { client: client.id }), { ...filters.value, page: pageNumber }, { preserveScroll: true, replace: true });
};
</script>

<template>
    <Head :title="`Projects - ${client.name}`" />
    <Toaster />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="mb-2 flex flex-col gap-4">
                <h1 class="text-3xl font-bold">{{ client.name }}'s Projects</h1>
                <p class="text-muted-foreground">{{ client.email }}</p>
            </div>

            <!-- Filters section -->
            <div class="flex justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <!-- Status & Date filters -->
                <div class="space-y-2">
                    <Label class="text-xl font-bold">Filter by:</Label>
                    <ProjectFilters :filters="filters" :role="page.props.auth.user.role" :editors="editors" @update:filters="applyFilters" />
                </div>

                <!-- Search input -->
                <div class="w-[320px] space-y-2">
                    <Label class="text-xl font-bold">Search project:</Label>
                    <Input v-model="filters.search" type="text" placeholder="Search..." @keyup.enter="applyFilters" />
                </div>
            </div>

            <!-- Table section -->
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Project Name</TableHead>
                        <TableHead>Service</TableHead>
                        <TableHead>Editor</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Priority Level</TableHead>
                        <TableHead>Total Price</TableHead>
                        <TableHead>Editor Price</TableHead>
                        <TableHead>Created At</TableHead>
                        <TableHead>Action</TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody v-for="project in projects.data" :key="project.id">
                    <TableRow>
                        <TableCell>
                            <div class="max-w-[200px] truncate" :title="project.project_name">
                                {{ project.project_name }}
                            </div>
                        </TableCell>
                        <TableCell>{{ project.service?.name || 'N/A' }}</TableCell>

                        <!-- Editor Select -->
                        <TableCell>
                            <Select :modelValue="project.editor_id" @update:modelValue="(value) => updateProject(project.id, 'editor_id', value)">
                                <SelectTrigger class="w-[180px]">
                                    <SelectValue placeholder="Assign an editor" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="null">Unassigned</SelectItem>
                                    <SelectItem v-for="editor in editors" :key="editor.id" :value="editor.id">
                                        {{ editor.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </TableCell>

                        <!-- Status Select -->
                        <TableCell>
                            <Select :modelValue="project.status" @update:modelValue="(value) => updateProject(project.id, 'status', value)">
                                <SelectTrigger
                                    class="w-[180px]"
                                    :class="{
                                        'text-gray-500': project.status === 'todo' || project.status === 'backlog',
                                        'text-yellow-500': project.status === 'in_progress',
                                        'text-orange-500': project.status === 'for_qa',
                                        'text-blue-500': project.status === 'done_qa',
                                        'text-purple-500': project.status === 'sent_to_client',
                                        'text-red-500': project.status === 'revision',
                                        'text-green-500': project.status === 'revision_completed',
                                    }"
                                >
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>

                                <SelectContent>
                                    <SelectItem
                                        v-for="(label, key) in statusLabels"
                                        :key="key"
                                        :value="key"
                                        :class="{
                                            'text-gray-500': key === 'todo' || key === 'backlog',
                                            'text-yellow-500': key === 'in_progress',
                                            'text-orange-500': key === 'for_qa',
                                            'text-blue-500': key === 'done_qa',
                                            'text-purple-500': key === 'sent_to_client',
                                            'text-red-500': key === 'revision',
                                            'text-green-500': key === 'revision_completed',
                                        }"
                                    >
                                        {{ label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </TableCell>

                        <!-- Priority Select -->
                        <TableCell>
                            <Select :modelValue="project.priority" @update:modelValue="(value) => updateProject(project.id, 'priority', value)">
                                <SelectTrigger
                                    class="w-[180px]"
                                    :class="{
                                        'font-semibold text-red-600': project.priority === 'urgent',
                                        'text-orange-500': project.priority === 'high',
                                        'text-blue-500': project.priority === 'normal',
                                        'text-gray-400': project.priority === 'low',
                                    }"
                                >
                                    <SelectValue placeholder="Select a priority" />
                                </SelectTrigger>

                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="urgent">Urgent</SelectItem>
                                        <SelectItem value="high">High</SelectItem>
                                        <SelectItem value="normal">Normal</SelectItem>
                                        <SelectItem value="low">Low</SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </TableCell>

                        <!-- Total Price -->
                        <TableCell>
                            <div class="relative">
                                <span class="absolute top-1/2 left-2 -translate-y-1/2 text-gray-500">$</span>
                                <Input
                                    type="number"
                                    step="0.01"
                                    class="w-28 pl-6"
                                    v-model.number="totalPrices[project.id]"
                                    @blur="updatePrice(project.id, totalPrices[project.id])"
                                    @keyup.enter="updatePrice(project.id, totalPrices[project.id])"
                                    :placeholder="String(project.total_price)"
                                />
                            </div>
                        </TableCell>

                        <TableCell>
                            <div class="relative">
                                <span class="absolute top-1/2 left-2 -translate-y-1/2 text-gray-500">₱</span>
                                <Input
                                    type="number"
                                    step="0.01"
                                    class="w-28 pl-6"
                                    v-model.number="editorPrices[project.id]"
                                    @keyup.enter="
                                        () => {
                                            updateProject(project.id, 'editor_price', editorPrices[project.id]);
                                        }
                                    "
                                />
                            </div>
                        </TableCell>

                        <TableCell>
                            {{
                                new Date(project.created_at).toLocaleString('en-US', {
                                    year: 'numeric',
                                    month: 'short',
                                    day: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit',
                                })
                            }}
                        </TableCell>

                        <TableCell class="space-x-4">
                            <Button @click="openEditModal(project)">Edit</Button>
                            <Button @click="openViewModal(project)">View Details</Button>
                            <Button variant="destructive" @click="confirmDelete(project)"> Delete </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <!-- Pagination -->
            <div class="mt-4 flex items-center justify-center">
                <Pagination
                    v-slot="{ page }"
                    :items-per-page="projects.per_page"
                    :total="projects.total"
                    :default-page="projects.current_page"
                    @update:page="goToPage"
                >
                    <PaginationContent v-slot="{ items }">
                        <PaginationPrevious />
                        <template v-for="(item, index) in items" :key="index">
                            <PaginationItem v-if="item.type === 'page'" :value="item.value" :is-active="item.value === page">
                                {{ item.value }}
                            </PaginationItem>
                        </template>
                        <PaginationNext />
                    </PaginationContent>
                </Pagination>
            </div>
        </div>

        <ProjectViewModal
            v-if="selectedProject"
            :isOpen="showModal"
            :project="selectedProject"
            :role="page.props.auth.user.role"
            @close="closeViewModal"
        />

        <BasicStyleForm
            v-if="editProject && editProject.service?.name === 'Real Estate Basic Style'"
            :open="isEditModalOpen"
            :serviceId="editProject.service_id"
            :project="editProject"
            :basePrice="basePrice"
            @close="closeEditModal"
        />

        <DeluxeStyleForm
            v-else-if="editProject && editProject.service?.name === 'Real Estate Deluxe Style'"
            :open="isEditModalOpen"
            :serviceId="editProject.service_id"
            :project="editProject"
            :basePrice="basePrice"
            @close="closeEditModal"
        />

        <PremiumStyleForm
            v-else-if="editProject && editProject.service?.name === 'Real Estate Premium Style'"
            :open="isEditModalOpen"
            :serviceId="editProject.service_id"
            :project="editProject"
            :basePrice="basePrice"
            @close="closeEditModal"
        />

        <LuxuryStyleForm
            v-else-if="editProject && editProject.service?.name === 'Real Estate Luxury Style'"
            :open="isEditModalOpen"
            :serviceId="editProject.service_id"
            :project="editProject"
            :basePrice="basePrice"
            @close="closeEditModal"
        />

        <TalkingHeadsForm
            v-else-if="editProject && editProject.service?.name === 'Talking Heads'"
            :open="isEditModalOpen"
            :serviceId="editProject.service_id"
            :project="editProject"
            :basePrice="basePrice"
            @close="closeEditModal"
        />

        <!-- Wedding Services Modal -->
        <WeddingBasicForm
            v-if="editProject && editProject.service?.name === 'Wedding Basic Style'"
            :open="isEditModalOpen"
            :serviceId="editProject.service_id"
            :project="editProject"
            :basePrice="basePrice"
            @close="closeEditModal"
        />

        <WeddingPremiumForm
            v-else-if="editProject && editProject.service?.name === 'Wedding Premium Style'"
            :open="isEditModalOpen"
            :serviceId="editProject.service_id"
            :project="editProject"
            :basePrice="basePrice"
            @close="closeEditModal"
        />

        <WeddingLuxuryForm
            v-else-if="editProject && editProject.service?.name === 'Wedding Luxury Style'"
            :open="isEditModalOpen"
            :serviceId="editProject.service_id"
            :project="editProject"
            :basePrice="basePrice"
            @close="closeEditModal"
        />

        <!-- Event Services Modal -->
        <EventBasicForm
            v-if="editProject && editProject.service?.name === 'Event Basic Style'"
            :open="isEditModalOpen"
            :serviceId="editProject.service_id"
            :project="editProject"
            :basePrice="basePrice"
            @close="closeEditModal"
        />

        <EventPremiumForm
            v-else-if="editProject && editProject.service?.name === 'Event Premium Style'"
            :open="isEditModalOpen"
            :serviceId="editProject.service_id"
            :project="editProject"
            :basePrice="basePrice"
            @close="closeEditModal"
        />

        <EventLuxuryForm
            v-else-if="editProject && editProject.service?.name === 'Event Luxury Style'"
            :open="isEditModalOpen"
            :serviceId="editProject.service_id"
            :project="editProject"
            :basePrice="basePrice"
            @close="closeEditModal"
        />

        <!-- Construction Services Modal -->
        <ConstructionBasicForm
            v-if="editProject && editProject.service?.name === 'Construction Basic Style'"
            :open="isEditModalOpen"
            :serviceId="editProject.service_id"
            :project="editProject"
            :basePrice="basePrice"
            @close="closeEditModal"
        />

        <ConstructionPremiumForm
            v-else-if="editProject && editProject.service?.name === 'Construction Premium Style'"
            :open="isEditModalOpen"
            :serviceId="editProject.service_id"
            :project="editProject"
            :basePrice="basePrice"
            @close="closeEditModal"
        />

        <ConstructionLuxuryForm
            v-else-if="editProject && editProject.service?.name === 'Construction Luxury Style'"
            :open="isEditModalOpen"
            :serviceId="editProject.service_id"
            :project="editProject"
            :basePrice="basePrice"
            @close="closeEditModal"
        />

        <!-- Confirmation Delete Modal -->
        <Dialog :open="showDeleteModal" @update:open="showDeleteModal = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Delete Project</DialogTitle>
                    <p class="text-sm text-muted-foreground">
                        Are you sure you want to delete
                        <span class="font-semibold">{{ projectToDelete?.project_name }}</span
                        >? This action cannot be undone.
                    </p>
                </DialogHeader>
                <div class="mt-4 flex justify-end gap-2">
                    <Button variant="outline" @click="closeDeleteModal">Cancel</Button>
                    <Button variant="destructive" @click="deleteProject">Delete</Button>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

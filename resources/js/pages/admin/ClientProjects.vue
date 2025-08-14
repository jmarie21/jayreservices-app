<script setup lang="ts">
import ProjectViewModal from '@/components/modals/ProjectViewModal.vue';
import ProjectFilters from '@/components/ProjectFilters.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination, PaginationContent, PaginationItem, PaginationNext, PaginationPrevious } from '@/components/ui/pagination';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { AppPageProps, Projects, type BreadcrumbItem } from '@/types';
import { Paginated } from '@/types/app-page-prop';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Project Management', href: '/project-mgmt' }];

const pageProps = usePage<
    AppPageProps<{
        client: { id: number; name: string; email: string };
        projects: Paginated<Projects>;
        editors: { id: number; name: string }[];
        filters?: { status?: string; date_from?: string; date_to?: string; search?: string };
    }>
>().props;

const { client, editors } = pageProps;
const projects = computed(() => pageProps.projects);

const showModal = ref(false);
const selectedProject = ref<Projects | null>(null);

const form = useForm<{ editor_id: number | null; status: 'pending' | 'in_progress' | 'completed' }>({
    editor_id: null,
    status: 'pending',
});

// Status labels for badges
const statusLabels: Record<'pending' | 'in_progress' | 'completed', string> = {
    pending: 'Pending',
    in_progress: 'In Progress',
    completed: 'Completed',
};

const openViewModal = (project: Projects) => {
    selectedProject.value = project;
    showModal.value = true;
};

const closeViewModal = () => {
    showModal.value = false;
    selectedProject.value = null;
};

// Optimistic update for editor/status
const updateProject = <K extends keyof typeof form>(projectId: number, field: K, value: any) => {
    router.patch(
        route('projects.update', projectId),
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
                    <ProjectFilters :filters="filters" @update:filters="applyFilters" />
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
                        <TableHead>Total Price</TableHead>
                        <TableHead>Created At</TableHead>
                        <TableHead>Action</TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody v-for="project in projects.data" :key="project.id">
                    <TableRow>
                        <TableCell>{{ project.project_name }}</TableCell>
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
                                    class="w-[150px]"
                                    :class="{
                                        'text-red-500': project.status === 'pending',
                                        'text-yellow-500': project.status === 'in_progress',
                                        'text-green-500': project.status === 'completed',
                                    }"
                                >
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>

                                <SelectContent>
                                    <SelectItem value="pending" class="text-red-500">Pending</SelectItem>
                                    <SelectItem value="in_progress" class="text-yellow-500">In Progress</SelectItem>
                                    <SelectItem value="completed" class="text-green-500">Completed</SelectItem>
                                </SelectContent>
                            </Select>
                        </TableCell>

                        <TableCell>${{ Number(project.total_price).toLocaleString() }}</TableCell>
                        <TableCell>{{ new Date(project.created_at).toLocaleDateString() }}</TableCell>
                        <TableCell>
                            <Button @click="openViewModal(project)">View Details</Button>
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

        <ProjectViewModal v-if="selectedProject" :isOpen="showModal" :project="selectedProject" @close="closeViewModal" />
    </AppLayout>
</template>

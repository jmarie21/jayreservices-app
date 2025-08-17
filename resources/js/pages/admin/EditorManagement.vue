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

type Status = 'todo' | 'in_progress' | 'for_qa' | 'done_qa' | 'sent_to_client' | 'revision' | 'revision_completed' | 'backlog';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Editor Management', href: '/editor-mgmt' }];

const pageProps = usePage<
    AppPageProps<{
        editor: { id: number; name: string; email: string };
        projects: Paginated<Projects>;
        filters?: { status?: string; date_from?: string; date_to?: string; search?: string };
    }>
>().props;

const { editor } = pageProps;
const projects = computed(() => pageProps.projects);

const showModal = ref(false);
const selectedProject = ref<Projects | null>(null);

const form = useForm<{ status: Status }>({
    status: 'todo',
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

// Optimistic update for status
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
    filters.value = { ...filters.value, ...query };

    router.get(route('editor.projects', { editor: editor.id }), filters.value, {
        preserveScroll: true,
        replace: true,
    });
};

// Pagination with filters
const goToPage = (pageNumber: number) => {
    router.get(route('editor.projects', { editor: editor.id }), { ...filters.value, page: pageNumber }, { preserveScroll: true, replace: true });
};
</script>

<template>
    <Head :title="`Projects - ${editor.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="mb-2 flex flex-col gap-4">
                <h1 class="text-3xl font-bold">{{ editor.name }}'s Assigned Projects</h1>
                <p class="text-muted-foreground">{{ editor.email }}</p>
            </div>

            <!-- Filters section -->
            <div class="flex justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="space-y-2">
                    <Label class="text-xl font-bold">Filter by:</Label>
                    <ProjectFilters :filters="filters" @update:filters="applyFilters" />
                </div>

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
                        <TableHead>Client</TableHead>
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
                        <TableCell>{{ project.client?.name || 'N/A' }}</TableCell>

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

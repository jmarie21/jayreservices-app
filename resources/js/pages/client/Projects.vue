<script setup lang="ts">
import BasicStyleForm from '@/components/forms/BasicStyleForm.vue';
import DeluxeStyleForm from '@/components/forms/DeluxeStyleForm.vue';
import LuxuryStyleForm from '@/components/forms/LuxuryStyleForm.vue';
import PremiumStyleForm from '@/components/forms/PremiumStyleForm.vue';
import ProjectViewModal from '@/components/modals/ProjectViewModal.vue';
import ProjectFilters from '@/components/ProjectFilters.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination, PaginationContent, PaginationEllipsis, PaginationItem, PaginationNext, PaginationPrevious } from '@/components/ui/pagination';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { AppPageProps, Projects, type BreadcrumbItem } from '@/types';
import { Paginated } from '@/types/app-page-prop';
import { mapStatusForClient } from '@/utils/statusMapper';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Projects',
        href: '/projects',
    },
];

// Labels for display
const clientStatusLabels: Record<string, string> = {
    pending: 'Pending',
    in_progress: 'In Progress',
    completed: 'Completed',
};

// Colors for statuses
const clientStatusClasses: Record<string, string> = {
    pending: 'bg-yellow-500 text-white',
    in_progress: 'bg-blue-500 text-white',
    completed: 'bg-green-600 text-white',
};

const page = usePage<AppPageProps<{ projects: Paginated<Projects>; filters?: any }>>();
const projects = computed(() => page.props.projects);

const showModal = ref(false);
const showViewModal = ref(false);
const selectedProject = ref<Projects | null>(null);
const selectedStyle = computed(() => selectedProject.value?.service.name ?? '');
const viewProject = ref<Projects | null>(null);

const openEditModal = (project: Projects) => {
    selectedProject.value = project;
    showModal.value = true;
    console.log(selectedProject.value);
    console.log(selectedStyle.value);
};

const closeModal = () => {
    showModal.value = false;
    selectedProject.value = null;
};

const openViewModal = (project: Projects) => {
    viewProject.value = project;
    showViewModal.value = true;
};

const closeViewModal = () => {
    showViewModal.value = false;
    viewProject.value = null;
};

// const goToPage = (page: number) => {
//     router.get(route('projects'), { page }, { preserveScroll: true });
// };

const filters = ref({
    status: page.props.filters?.status || '',
    date_from: page.props.filters?.date_from || '',
    date_to: page.props.filters?.date_to || '',
    search: page.props.filters?.search || '',
});

const applyFilters = (newFilters: typeof filters.value) => {
    filters.value = newFilters;
    router.get(route('projects'), filters.value, {
        preserveScroll: true,
    });
};

const onSearch = () => {
    router.get(route('projects'), filters.value, {
        preserveScroll: true,
        replace: true,
    });
};

const goToPage = (page: number) => {
    router.get(
        route('projects'),
        { ...filters.value, page },
        {
            preserveScroll: true,
            replace: true,
        },
    );
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
</script>

<template>
    <Head title="Projects" />
    <Toaster />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <!-- Page header -->
            <div class="mb-2 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h1 class="text-3xl font-bold">My Projects</h1>
                <Button><Link href="/services">Add new project</Link></Button>
            </div>

            <!-- Filters section -->
            <div class="flex justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="space-y-2">
                    <Label class="text-xl font-bold">Filter by:</Label>
                    <ProjectFilters
                        :filters="filters"
                        :role="page.props.auth.user.role === 'client' ? 'client' : 'admin'"
                        @update:filters="applyFilters"
                    />
                </div>
                <div class="w-[320px] space-y-2">
                    <Label class="text-xl font-bold">Search project:</Label>
                    <Input v-model="filters.search" type="text" placeholder="Search..." @keyup.enter="onSearch" />
                </div>
            </div>

            <!-- Table section -->
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-[200px]"> Service </TableHead>
                        <TableHead> Project Name </TableHead>
                        <TableHead> Style </TableHead>
                        <TableHead> Created At </TableHead>
                        <TableHead> Status </TableHead>
                        <TableHead> Total Amount </TableHead>
                        <TableHead> Finished Output </TableHead>
                        <TableHead> Action </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody v-for="project in projects.data" :key="project.id">
                    <TableRow>
                        <TableCell class="font-bold">{{ project.service?.name }}</TableCell>
                        <TableCell>
                            <div class="max-w-[200px] truncate" :title="project.project_name">
                                {{ project.project_name }}
                            </div>
                        </TableCell>
                        <TableCell>{{ project.style }}</TableCell>
                        <TableCell>{{ new Date(project.created_at).toLocaleDateString() }}</TableCell>

                        <TableCell>
                            <Badge :class="clientStatusClasses[mapStatusForClient(project.status)]">
                                {{ clientStatusLabels[mapStatusForClient(project.status)] }}
                            </Badge>
                        </TableCell>
                        <TableCell>${{ project.total_price }}</TableCell>
                        <TableCell>
                            <template v-if="project.output_link">
                                <a
                                    :href="project.output_link.startsWith('http') ? project.output_link : `https://${project.output_link}`"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-blue-500 hover:underline"
                                >
                                    Finished Output
                                </a>
                            </template>
                            <template v-else> No Output Yet </template>
                        </TableCell>

                        <TableCell class="space-x-4">
                            <Button @click="openEditModal(project)" :disabled="mapStatusForClient(project.status) === 'completed'"> Edit </Button>
                            <Button @click="openViewModal(project)" class="bg-blue-500 hover:bg-blue-600 focus:ring-blue-300">View order</Button>
                            <Button
                                @click="markForRevision(project.id)"
                                variant="destructive"
                                :disabled="mapStatusForClient(project.status) !== 'completed'"
                            >
                                Mark for revision
                            </Button>
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
                        <PaginationEllipsis v-if="projects.last_page > 5" :index="4" />
                        <PaginationNext />
                    </PaginationContent>
                </Pagination>
            </div>
        </div>

        <BasicStyleForm
            v-if="selectedStyle === 'Basic Style'"
            :open="showModal"
            :base-price="selectedProject?.service?.price ?? 0"
            :service-id="selectedProject?.service_id ?? 1"
            :project="selectedProject"
            @close="closeModal"
        />

        <DeluxeStyleForm
            v-if="selectedStyle === 'Deluxe Style'"
            :open="showModal"
            :base-price="selectedProject?.service?.price ?? 0"
            :service-id="selectedProject?.service_id ?? 1"
            :project="selectedProject"
            @close="closeModal"
        />

        <PremiumStyleForm
            v-if="selectedStyle === 'Premium Style'"
            :open="showModal"
            :base-price="selectedProject?.service?.price ?? 0"
            :service-id="selectedProject?.service_id ?? 1"
            :project="
                selectedProject
                    ? {
                          ...selectedProject,
                          extra_fields: {
                              effects: selectedProject.extra_fields?.effects ?? [],
                              captions: selectedProject.extra_fields?.captions ?? [],
                          },
                      }
                    : null
            "
            @close="closeModal"
        />

        <LuxuryStyleForm
            v-if="selectedStyle === 'Luxury Style'"
            :open="showModal"
            :base-price="selectedProject?.service?.price ?? 0"
            :service-id="selectedProject?.service_id ?? 1"
            :project="
                selectedProject
                    ? {
                          ...selectedProject,
                          extra_fields: {
                              effects: selectedProject.extra_fields?.effects ?? [],
                              captions: selectedProject.extra_fields?.captions ?? [],
                          },
                      }
                    : null
            "
            @close="closeModal"
        />

        <ProjectViewModal
            v-if="viewProject"
            :isOpen="showViewModal"
            :project="viewProject"
            :role="page.props.auth.user.role"
            @close="closeViewModal"
        />
    </AppLayout>
</template>

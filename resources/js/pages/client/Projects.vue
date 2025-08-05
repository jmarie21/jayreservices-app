<script setup lang="ts">
import BasicStyleForm from '@/components/forms/BasicStyleForm.vue';
import ProjectViewModal from '@/components/modals/ProjectViewModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { AppPageProps, Projects, type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Projects',
        href: '/projects',
    },
];
const projects = computed(() => usePage<AppPageProps<{ projects: Projects[] }>>().props.projects);

const showModal = ref(false);
const showViewModal = ref(false);
const selectedProject = ref<Projects | null>(null);
const viewProject = ref<Projects | null>(null);

const openEditModal = (project: Projects) => {
    selectedProject.value = project;
    showModal.value = true;
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
</script>

<template>
    <Head title="Projects" />
    <Toaster />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="mb-8">
                <h1 class="text-3xl font-bold">My Projects</h1>
            </div>
            <Table>
                <TableCaption>A list of your recent projects.</TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-[250px]"> Project Name </TableHead>
                        <TableHead>Style</TableHead>
                        <TableHead>Created At</TableHead>
                        <TableHead> Status </TableHead>
                        <TableHead> Total Amount </TableHead>
                        <TableHead> Finished Output </TableHead>
                        <TableHead> Action </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody v-for="project in projects" :key="project.id">
                    <TableRow>
                        <TableCell class="font-bold"> {{ project.project_name }} </TableCell>
                        <TableCell>{{ project.style }}</TableCell>
                        <TableCell>{{ project.created_at }}</TableCell>
                        <TableCell
                            ><Badge>{{ project.status }}</Badge></TableCell
                        >
                        <TableCell> ${{ project.total_price }} </TableCell>
                        <TableCell>{{ project.output_link }}</TableCell>
                        <TableCell class="space-x-4">
                            <Button @click="openEditModal(project)">Edit</Button>
                            <Button @click="openViewModal(project)">View order</Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <BasicStyleForm
            :open="showModal"
            :base-price="selectedProject?.total_price ?? 0"
            :service-id="selectedProject?.service_id ?? 1"
            :project="selectedProject"
            @close="closeModal"
        />

        <ProjectViewModal v-if="viewProject" :open="showViewModal" :project="viewProject" @close="closeViewModal" />
    </AppLayout>
</template>

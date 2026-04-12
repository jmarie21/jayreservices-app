<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AppPageProps, BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface EditorProjectRow {
    client_name: string;
    project_name: string;
    service: string;
    video_format: string;
    add_ons: string;
    priority: string | null;
    total_price: number | null;
    editor_price: number | null;
    created_at: string;
}

interface EditorProjectsGroup {
    id: number;
    name: string;
    projects: EditorProjectRow[];
}

interface Filters {
    date_from: string;
    date_to: string;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Editors Projects', href: '/editors-projects' }];

const pageProps = usePage<
    AppPageProps<{
        filters: Filters;
        editors: EditorProjectsGroup[];
    }>
>().props;

const editors = computed(() => pageProps.editors ?? []);
const localFilters = ref<Filters>({
    date_from: pageProps.filters?.date_from ?? '',
    date_to: pageProps.filters?.date_to ?? '',
});

const customerMoneyFormatter = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
});

const editorMoneyFormatter = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
});

const createdAtFormatter = new Intl.DateTimeFormat('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

function applyFilters() {
    router.get(
        route('admin.editors-projects.index'),
        {
            date_from: localFilters.value.date_from,
            date_to: localFilters.value.date_to,
        },
        {
            preserveScroll: true,
            preserveState: false,
            replace: true,
        },
    );
}

function clearDates() {
    localFilters.value = {
        date_from: '',
        date_to: '',
    };

    applyFilters();
}

function formatCustomerMoney(value: number | null) {
    return value != null ? customerMoneyFormatter.format(Number(value)) : '—';
}

function formatEditorMoney(value: number | null) {
    return value != null ? editorMoneyFormatter.format(Number(value)) : '—';
}

function formatCreatedAt(value: string) {
    const parsedDate = new Date(value);

    return Number.isNaN(parsedDate.getTime()) ? value : createdAtFormatter.format(parsedDate);
}

function formatPriority(value: string | null) {
    return value ? value.replaceAll('_', ' ').replace(/\b\w/g, (match) => match.toUpperCase()) : 'N/A';
}
</script>

<template>
    <Head title="Editors Projects" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4">
            <div class="space-y-2">
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">Editors Projects</h1>
                <p class="max-w-4xl text-sm text-slate-500">
                    Review assigned projects grouped by editor. New visits default to today, and clearing the dates will show all assigned projects.
                </p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                        <div class="space-y-2">
                            <Label for="date-from">From Date</Label>
                            <Input id="date-from" v-model="localFilters.date_from" type="date" class="w-[180px]" />
                        </div>

                        <div class="space-y-2">
                            <Label for="date-to">To Date</Label>
                            <Input id="date-to" v-model="localFilters.date_to" type="date" class="w-[180px]" />
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button variant="outline" @click="clearDates">Clear Dates</Button>
                        <Button class="bg-indigo-600 hover:bg-indigo-700" @click="applyFilters">Apply Filter</Button>
                    </div>
                </div>
            </div>

            <div v-if="editors.length === 0" class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500">
                No editors found.
            </div>

            <div v-else class="space-y-6">
                <section v-for="editor in editors" :key="editor.id" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 md:flex-row md:items-center md:justify-between">
                        <div class="space-y-1">
                            <h2 class="text-xl font-semibold text-slate-900">{{ editor.name }}</h2>
                        </div>

                        <div class="inline-flex w-fit items-center rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-700">
                            {{ editor.projects.length }} {{ editor.projects.length === 1 ? 'project' : 'projects' }}
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Client Name</TableHead>
                                    <TableHead>Project Name</TableHead>
                                    <TableHead>Service</TableHead>
                                    <TableHead>Video Format</TableHead>
                                    <TableHead class="w-[320px]">Add-Ons</TableHead>
                                    <TableHead>Priority</TableHead>
                                    <TableHead>Total Price</TableHead>
                                    <TableHead>Editor Price</TableHead>
                                    <TableHead>Created At</TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                <TableRow v-if="editor.projects.length === 0">
                                    <TableCell colspan="9" class="py-8 text-center text-sm text-slate-500">
                                        No assigned projects for this editor in the selected date range.
                                    </TableCell>
                                </TableRow>

                                <TableRow v-for="(project, index) in editor.projects" :key="`${editor.id}-${index}-${project.created_at}`">
                                    <TableCell>{{ project.client_name }}</TableCell>
                                    <TableCell>
                                        <div class="max-w-[220px] truncate" :title="project.project_name">
                                            {{ project.project_name }}
                                        </div>
                                    </TableCell>
                                    <TableCell>{{ project.service }}</TableCell>
                                    <TableCell>{{ project.video_format }}</TableCell>
                                    <TableCell class="max-w-[320px] whitespace-normal align-top">
                                        <div class="max-w-[320px] break-words whitespace-normal">
                                            {{ project.add_ons || '—' }}
                                        </div>
                                    </TableCell>
                                    <TableCell>{{ formatPriority(project.priority) }}</TableCell>
                                    <TableCell>{{ formatCustomerMoney(project.total_price) }}</TableCell>
                                    <TableCell>{{ formatEditorMoney(project.editor_price) }}</TableCell>
                                    <TableCell>{{ formatCreatedAt(project.created_at) }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>

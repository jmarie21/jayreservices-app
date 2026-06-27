<script setup lang="ts">
import EditorLevelBadge from '@/components/EditorLevelBadge.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { AppPageProps, type BreadcrumbItem } from '@/types';
import { EditorLevel } from '@/types/app-page-prop';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type ClientRow = {
    id: number;
    name: string;
    email: string;
    recommended_editor_level: EditorLevel | null;
    dedicated_editor_rules_count: number;
    extra_requests_count: number;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Client Management', href: '/client-management' }];

const pageProps = usePage<
    AppPageProps<{
        clients: ClientRow[];
    }>
>().props;

const clients = computed(() => pageProps.clients ?? []);
</script>

<template>
    <Head title="Client Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4">
            <div class="flex items-start justify-between gap-4">
                <div class="space-y-2">
                    <h1 class="text-3xl font-bold tracking-tight text-slate-900">Client Management</h1>
                    <p class="max-w-4xl text-sm text-slate-500">
                        The central place for all per-client setup — recommended editor level, dedicated editors, and any extra requests
                        they've made outside of the standard services.
                    </p>
                </div>
                <Button variant="outline" as-child class="shrink-0">
                    <Link :href="route('admin.client-levels.index')">View by Level (Board) →</Link>
                </Button>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Email</TableHead>
                            <TableHead>Recommended Level</TableHead>
                            <TableHead>Dedicated Editor Rules</TableHead>
                            <TableHead>Extra Requests</TableHead>
                            <TableHead class="text-right">Action</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="clients.length === 0">
                            <TableCell colspan="6" class="text-center text-sm text-slate-500">No clients found.</TableCell>
                        </TableRow>
                        <TableRow v-for="client in clients" :key="client.id">
                            <TableCell class="font-medium text-slate-900">{{ client.name }}</TableCell>
                            <TableCell class="text-slate-500">{{ client.email }}</TableCell>
                            <TableCell>
                                <EditorLevelBadge :level="client.recommended_editor_level" />
                            </TableCell>
                            <TableCell>{{ client.dedicated_editor_rules_count }}</TableCell>
                            <TableCell>{{ client.extra_requests_count }}</TableCell>
                            <TableCell class="text-right">
                                <Button size="sm" variant="outline" as-child>
                                    <Link :href="route('admin.client-management.show', client.id)">Manage</Link>
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
    </AppLayout>
</template>

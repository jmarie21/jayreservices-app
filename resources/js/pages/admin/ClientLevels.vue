<script setup lang="ts">
import LevelBoard from '@/components/LevelBoard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AppPageProps, BreadcrumbItem, User } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Client Management', href: '/client-management' },
    { title: 'Client Levels', href: '/client-levels' },
];

const pageProps = usePage<
    AppPageProps<{
        clients: User[];
    }>
>().props;

const clients = computed(() => pageProps.clients ?? []);
</script>

<template>
    <Head title="Client Levels" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4">
            <div class="space-y-2">
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">Client Levels</h1>
                <p class="max-w-4xl text-sm text-slate-500">
                    Tag clients with a recommended editor level. This is just a legend on the project pages — admins can still assign any
                    editor regardless of this recommendation. For dedicated editors, extra requests, and everything else client-specific,
                    head to Client Management.
                </p>
            </div>

            <LevelBoard :users="clients" level-field="recommended_editor_level" assign-route-name="admin.client-levels.assign" entity-noun="client">
                <template #card-footer="{ user }: { user: User }">
                    <Link
                        :href="route('admin.client-management.show', user.id)"
                        class="level-board-no-drag mt-2 inline-block text-xs font-medium text-indigo-600 hover:underline"
                    >
                        Manage client →
                    </Link>
                </template>
            </LevelBoard>
        </div>
    </AppLayout>
</template>

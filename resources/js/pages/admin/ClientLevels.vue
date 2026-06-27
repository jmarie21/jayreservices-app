<script setup lang="ts">
import LevelBoard from '@/components/LevelBoard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AppPageProps, BreadcrumbItem, User } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Client Levels', href: '/client-levels' }];

const pageProps = usePage<
    AppPageProps<{
        clients: User[];
        editors: { id: number; name: string }[];
    }>
>().props;

const clients = computed(() => pageProps.clients ?? []);
const editors = computed(() => pageProps.editors ?? []);
</script>

<template>
    <Head title="Client Levels" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4">
            <div class="space-y-2">
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">Client Levels</h1>
                <p class="max-w-4xl text-sm text-slate-500">
                    Tag clients with a recommended editor level. This is just a legend on the project pages — admins can still assign any
                    editor regardless of this recommendation. If a client needs to always use one specific editor, set a "Dedicated editor"
                    on their card instead — that one is enforced when assigning projects.
                </p>
            </div>

            <LevelBoard
                :users="clients"
                level-field="recommended_editor_level"
                assign-route-name="admin.client-levels.assign"
                entity-noun="client"
                :editors="editors"
                dedicated-editor-route-name="admin.client-levels.dedicated-editor"
            />
        </div>
    </AppLayout>
</template>

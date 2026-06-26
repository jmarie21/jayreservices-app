<script setup lang="ts">
import LevelBoard from '@/components/LevelBoard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AppPageProps, BreadcrumbItem, User } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Editor Levels', href: '/editor-levels' }];

const pageProps = usePage<
    AppPageProps<{
        editors: User[];
    }>
>().props;

const editors = computed(() => pageProps.editors ?? []);
</script>

<template>
    <Head title="Editor Levels" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4">
            <div class="space-y-2">
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">Editor Levels</h1>
                <p class="max-w-4xl text-sm text-slate-500">
                    Group editors by seniority. Drag an editor into a column, or use "+ Add" to assign several at once. This grouping is just a
                    legend to help you pick an editor when assigning projects.
                </p>
            </div>

            <LevelBoard :users="editors" level-field="editor_level" assign-route-name="admin.editor-levels.assign" entity-noun="editor" />
        </div>
    </AppLayout>
</template>

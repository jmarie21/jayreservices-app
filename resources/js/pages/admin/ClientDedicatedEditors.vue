<script setup lang="ts">
import EditorPickerModal from '@/components/EditorPickerModal.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AppPageProps, BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

type Editor = { id: number; name: string };
type ServiceRow = { id: number; name: string; service_category_id: number; category?: { id: number; name: string } };
type ServiceDedication = { service_id: number; editor_ids: number[] };

const pageProps = usePage<
    AppPageProps<{
        client: { id: number; name: string; email: string };
        editors: Editor[];
        services: ServiceRow[];
        generalEditorIds: number[];
        serviceDedications: ServiceDedication[];
    }>
>().props;

const client = computed(() => pageProps.client);
const editors = computed(() => pageProps.editors ?? []);
const services = computed(() => pageProps.services ?? []);

const generalEditorIds = ref<number[]>([...(pageProps.generalEditorIds ?? [])]);
const serviceEditorIds = ref<Record<number, number[]>>(
    Object.fromEntries((pageProps.serviceDedications ?? []).map((dedication) => [dedication.service_id, dedication.editor_ids])),
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Client Levels', href: '/client-levels' },
    { title: `${client.value.name}'s Dedicated Editors`, href: `/client-levels/${client.value.id}/dedicated-editors` },
];

const groupedServices = computed(() => {
    const groups = new Map<string, { categoryName: string; services: ServiceRow[] }>();

    for (const service of services.value) {
        const categoryName = service.category?.name ?? 'Other';

        if (!groups.has(categoryName)) {
            groups.set(categoryName, { categoryName, services: [] });
        }

        groups.get(categoryName)!.services.push(service);
    }

    return Array.from(groups.values());
});

function editorName(editorId: number): string {
    return editors.value.find((editor) => editor.id === editorId)?.name ?? 'Unknown';
}

const modalOpen = ref(false);
const modalServiceId = ref<number | null>(null);
const modalTitle = ref('');
const modalSelectedIds = ref<number[]>([]);

function openGeneralModal() {
    modalServiceId.value = null;
    modalTitle.value = 'General dedicated editors (all services)';
    modalSelectedIds.value = generalEditorIds.value;
    modalOpen.value = true;
}

function openServiceModal(service: ServiceRow) {
    modalServiceId.value = service.id;
    modalTitle.value = `Dedicated editors for ${service.name}`;
    modalSelectedIds.value = serviceEditorIds.value[service.id] ?? [];
    modalOpen.value = true;
}

function submitModal(editorIds: number[]) {
    const serviceId = modalServiceId.value;
    const previousGeneral = generalEditorIds.value;
    const previousService = serviceId !== null ? serviceEditorIds.value[serviceId] : undefined;

    if (serviceId === null) {
        generalEditorIds.value = editorIds;
    } else {
        serviceEditorIds.value = { ...serviceEditorIds.value, [serviceId]: editorIds };
    }

    router.patch(
        route('admin.client-levels.dedicated-editors.update', client.value.id),
        { service_id: serviceId, editor_ids: editorIds },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                generalEditorIds.value = previousGeneral;

                if (serviceId !== null) {
                    serviceEditorIds.value = { ...serviceEditorIds.value, [serviceId]: previousService ?? [] };
                }

                toast.error('Something went wrong', {
                    description: 'Could not update dedicated editors. Please try again.',
                });
            },
        },
    );
}
</script>

<template>
    <Head :title="`${client.name} - Dedicated Editors`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4">
            <div class="space-y-2">
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">{{ client.name }}'s Dedicated Editors</h1>
                <p class="max-w-4xl text-sm text-slate-500">
                    The general rule applies to all of this client's projects. A service-specific rule adds editors on top of the general
                    rule for that one service only — both sets are allowed there.
                </p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">General (all services)</h2>
                        <p class="mt-1 flex flex-wrap gap-1.5 text-sm">
                            <span v-if="generalEditorIds.length === 0" class="text-slate-400">No dedicated editors</span>
                            <span
                                v-for="editorId in generalEditorIds"
                                :key="editorId"
                                class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700"
                            >
                                {{ editorName(editorId) }}
                            </span>
                        </p>
                    </div>
                    <Button size="sm" variant="outline" @click="openGeneralModal">Manage</Button>
                </div>
            </div>

            <div v-for="group in groupedServices" :key="group.categoryName" class="space-y-2">
                <h2 class="text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ group.categoryName }}</h2>

                <div class="divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div v-for="service in group.services" :key="service.id" class="flex items-center justify-between gap-4 p-4">
                        <div>
                            <p class="text-sm font-medium text-slate-900">{{ service.name }}</p>
                            <p class="mt-1 flex flex-wrap items-center gap-1.5 text-sm">
                                <span v-if="(serviceEditorIds[service.id] ?? []).length === 0" class="text-slate-400">No override</span>
                                <span
                                    v-for="editorId in serviceEditorIds[service.id] ?? []"
                                    :key="editorId"
                                    class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700"
                                >
                                    {{ editorName(editorId) }}
                                </span>
                                <span v-if="generalEditorIds.length > 0" class="text-xs text-slate-400">
                                    (+ {{ generalEditorIds.length }} general)
                                </span>
                            </p>
                        </div>
                        <Button size="sm" variant="outline" @click="openServiceModal(service)">Manage</Button>
                    </div>
                </div>
            </div>
        </div>

        <EditorPickerModal v-model:open="modalOpen" :title="modalTitle" :editors="editors" :selected-ids="modalSelectedIds" @submit="submitModal" />
    </AppLayout>
</template>

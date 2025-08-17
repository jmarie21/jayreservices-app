<script setup lang="ts">
import InvoiceForm from '@/components/forms/InvoiceForm.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { AppPageProps, Projects, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Invoice Management', href: '/invoice-mgmt' }];

const pageProps = usePage<
    AppPageProps<{
        clients: { id: number; name: string }[];
        projects: Projects[];
    }>
>().props;

const clients = computed(() => pageProps.clients);
const projects = ref<Projects[]>(pageProps.projects ?? []);

// Modal state
const isModalOpen = ref(false);

// Filter state (single source of truth)
const selectedClient = ref<number | null>(null);
const dateFrom = ref('');
const dateTo = ref('');

// Watch filters and fetch projects from backend
watch([selectedClient, dateFrom, dateTo], ([client, from, to]) => {
    // Only fetch if client is selected
    if (!client) {
        projects.value = [];
        return;
    }

    router.get(
        '/invoice-mgmt',
        {
            client_id: client,
            date_from: from || undefined,
            date_to: to || undefined,
        },
        {
            preserveState: true,
            replace: true,
            onSuccess: (page) => {
                projects.value = (page.props.projects as Projects[]) ?? [];
            },
        },
    );
});

// Handle invoice creation
const handleCreateInvoice = (invoice: { client_id: number; paypal_link: string; date_from: string; date_to: string }) => {
    router.post('/invoices', invoice, {
        onSuccess: () => {
            isModalOpen.value = false;
            selectedClient.value = null;
            dateFrom.value = '';
            dateTo.value = '';
            projects.value = [];
        },
    });
};
</script>

<template>
    <Head title="Invoice Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="flex justify-end">
                <Button @click="isModalOpen = true">Create Invoice</Button>
            </div>

            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <!-- invoice cards/table here -->
            </div>
        </div>

        <!-- Modal -->
        <InvoiceForm
            :isOpen="isModalOpen"
            :clients="clients"
            :projects="projects"
            v-model:selectedClient="selectedClient"
            v-model:dateFrom="dateFrom"
            v-model:dateTo="dateTo"
            @close="isModalOpen = false"
            @createInvoice="handleCreateInvoice"
        />
    </AppLayout>
</template>

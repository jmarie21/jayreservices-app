<script setup lang="ts">
import InvoiceForm from '@/components/forms/InvoiceForm.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { AppPageProps, Invoice, Projects, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Toaster } from 'vue-sonner';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Invoice Management', href: '/invoice-mgmt' }];

const page = usePage<
    AppPageProps<{
        clients: { id: number; name: string }[];
        projects: Projects[];
        invoices: Invoice[];
    }>
>();

const clients = computed(() => page.props.clients);
const projects = ref<Projects[]>(page.props.projects ?? []);
const invoices = computed(() => page.props.invoices);

// Modal state
const isModalOpen = ref(false);

// Filter state (single source of truth)
const selectedClient = ref<number | null>(null);
const selectedInvoice = ref<Invoice | null>(null);
const dateFrom = ref('');
const dateTo = ref('');

const openEditInvoice = (invoice: Invoice) => {
    selectedInvoice.value = invoice;
    isModalOpen.value = true;
};

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

// Handle invoice update
const handleUpdateInvoice = (invoice: { client_id: number; paypal_link: string; date_from: string; date_to: string }) => {
    if (!selectedInvoice.value) return;

    router.put(`/invoices/${selectedInvoice.value.id}`, invoice, {
        onSuccess: () => {
            isModalOpen.value = false;
            selectedInvoice.value = null;
            selectedClient.value = null;
            dateFrom.value = '';
            dateTo.value = '';
        },
    });
};

// Handle invoice creation
const handleCreateInvoice = (invoice: { client_id: number; paypal_link: string; date_from: string; date_to: string }) => {
    router.post('/invoices', invoice, {
        onSuccess: () => {
            isModalOpen.value = false;
            selectedClient.value = null;
            dateFrom.value = '';
            dateTo.value = '';
        },
    });
};
</script>

<template>
    <Head title="Invoice Management" />
    <Toaster />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="flex justify-between">
                <h2 class="mb-2 text-3xl font-bold">Invoices</h2>
                <Button @click="isModalOpen = true">Create Invoice</Button>
            </div>

            <!-- Invoice List -->
            <div>
                <div v-if="invoices.length > 0" class="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Invoice #</TableHead>
                                <TableHead>Client</TableHead>
                                <TableHead>PayPal Link</TableHead>
                                <TableHead>Created At</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Action</TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            <TableRow v-for="invoice in invoices" :key="invoice.id">
                                <TableCell>{{ invoice.invoice_number }}</TableCell>
                                <TableCell>{{ invoice.client?.name ?? 'N/A' }}</TableCell>
                                <TableCell>
                                    <a :href="invoice.paypal_link" target="_blank" class="text-blue-500 underline"> Pay Now </a>
                                </TableCell>
                                <TableCell>{{ new Date(invoice.created_at).toLocaleDateString() }}</TableCell>
                                <TableCell>
                                    <Badge>
                                        {{ invoice.status }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="space-x-4">
                                    <Button @click="openEditInvoice(invoice)">Edit</Button>
                                    <Button>View</Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <p v-else class="text-gray-500">No invoices found.</p>
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
            :invoice="selectedInvoice"
            @close="isModalOpen = false"
            @createInvoice="handleCreateInvoice"
            @updateInvoice="handleUpdateInvoice"
        />
    </AppLayout>
</template>

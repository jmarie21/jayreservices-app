<script setup lang="ts">
import InvoiceForm from '@/components/forms/InvoiceForm.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Pagination, PaginationContent, PaginationItem, PaginationNext, PaginationPrevious } from '@/components/ui/pagination';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { AppPageProps, Invoice, Projects, type BreadcrumbItem } from '@/types';
import { Paginated } from '@/types/app-page-prop';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast, Toaster } from 'vue-sonner';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Invoice Management', href: '/invoice-mgmt' }];

const pageProps = usePage<
    AppPageProps<{
        clients: { id: number; name: string }[];
        projects: Projects[];
        invoices: Paginated<Invoice>;
    }>
>().props;

const clients = computed(() => pageProps.clients);
const projects = ref<Projects[]>(pageProps.projects ?? []);
const invoices = computed<Paginated<Invoice>>(
    () => usePage<AppPageProps<{ invoices: Paginated<Invoice> }>>().props.invoices ?? { data: [], total: 0, per_page: 10, current_page: 1 },
);

// Modal state
const isModalOpen = ref(false);

// Filter state (single source of truth)
const selectedClient = ref<number | null>(null);
const selectedInvoice = ref<Invoice | null>(null);
const dateFrom = ref('');
const dateTo = ref('');

const getStatusBadgeClass = (status: string) => {
    switch (status) {
        case 'sent':
            return 'bg-green-500 ';
        case 'pending':
            return 'bg-yellow-500';
        case 'overdue':
            return 'bg-red-500';
        default:
            return 'bg-gray-500';
    }
};

const openEditInvoice = (invoice: Invoice) => {
    selectedInvoice.value = invoice;

    // Pre-fill filters for watcher to fetch projects
    selectedClient.value = invoice.client_id ?? null;
    dateFrom.value = invoice.date_from ?? '';
    dateTo.value = invoice.date_to ?? '';

    isModalOpen.value = true;
};

watch([selectedClient, dateFrom, dateTo, selectedInvoice], ([client, from, to, invoice]) => {
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
            invoice_id: invoice?.id || undefined, // ✅ include invoice_id
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

    router.put(`/invoice-mgmt/${selectedInvoice.value.id}`, invoice, {
        onSuccess: () => {
            toast.success('Invoice updated!', {
                description: 'Invoice updated successfully!',
                position: 'top-right',
            });
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
    router.post('/invoice-mgmt', invoice, {
        onSuccess: () => {
            toast.success('Invoice created!', {
                description: 'Invoice created successfully!',
                position: 'top-right',
            });
            isModalOpen.value = false;
            selectedClient.value = null;
            dateFrom.value = '';
            dateTo.value = '';
        },
    });
};

// --- Helper to close modal and reset filters/URL ---
const closeModal = () => {
    isModalOpen.value = false;
    selectedInvoice.value = null;
    selectedClient.value = null;
    dateFrom.value = '';
    dateTo.value = '';

    // Reset URL to base route without query params
    router.replace(route('invoice.index'));
};

const openInvoice = (id: number) => {
    window.open(route('invoice.view', id), '_blank');
};

const sendingInvoiceIds = ref<number[]>([]); // Track invoices currently being sent

const sendInvoice = (id: number) => {
    if (sendingInvoiceIds.value.includes(id)) return; // prevent double-click
    sendingInvoiceIds.value.push(id);

    router.post(
        `/invoice-mgmt/${id}/send`,
        {},
        {
            onSuccess: () => {
                toast.success('Invoice sent!', {
                    description: 'Invoice sent successfully!',
                    position: 'top-right',
                });
                router.reload({ only: ['invoices'] });
            },
            onError: () => {
                toast.error('Failed to send invoice', {
                    description: 'There was an error sending the invoice, please try again',
                    position: 'top-right',
                });
            },
            onFinish: () => {
                sendingInvoiceIds.value = sendingInvoiceIds.value.filter((i) => i !== id);
            },
        },
    );
};

const goToPage = (pageNumber: number) => {
    router.get(
        route('invoice.index'),
        {
            page: pageNumber,
            client_id: selectedClient.value ?? undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
        },
        { preserveScroll: true, replace: true },
    );
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
                <div v-if="invoices.data.length > 0" class="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Invoice #</TableHead>
                                <TableHead>Client</TableHead>
                                <TableHead>Created At</TableHead>
                                <TableHead>Total Price</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Action</TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            <TableRow v-for="invoice in invoices.data" :key="invoice.id">
                                <TableCell>{{ invoice.invoice_number }}</TableCell>
                                <TableCell>{{ invoice.client?.name ?? 'N/A' }}</TableCell>
                                <TableCell>{{ new Date(invoice.created_at).toLocaleDateString() }}</TableCell>
                                <TableCell>${{ invoice.total_amount ?? 'N/A' }}</TableCell>
                                <TableCell>
                                    <Badge :class="getStatusBadgeClass(invoice.status)">
                                        {{ invoice.status }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="flex items-center space-x-2">
                                    <Button
                                        @click="sendInvoice(invoice.id)"
                                        :disabled="invoice.status === 'sent' || sendingInvoiceIds.includes(invoice.id)"
                                        class="flex items-center gap-2 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        <span
                                            v-if="sendingInvoiceIds.includes(invoice.id)"
                                            class="loader h-4 w-4 animate-spin rounded-full border-2 border-blue-500 border-t-transparent"
                                        ></span>
                                        {{ sendingInvoiceIds.includes(invoice.id) ? 'Sending...' : 'Send Invoice' }}
                                    </Button>
                                    <Button
                                        @click="openEditInvoice(invoice)"
                                        :disabled="invoice.status === 'sent'"
                                        class="disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        Edit
                                    </Button>
                                    <Button @click="openInvoice(invoice.id)">View</Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
                <!-- Pagination -->
                <div v-if="invoices?.data?.length" class="mt-4 flex items-center justify-center">
                    <Pagination
                        v-slot="paginationProps"
                        :items-per-page="invoices?.per_page ?? 10"
                        :total="invoices?.total ?? 0"
                        :default-page="invoices?.current_page ?? 1"
                        @update:page="goToPage"
                    >
                        <PaginationContent v-slot="contentProps">
                            <PaginationPrevious />
                            <template v-for="(item, index) in contentProps.items" :key="index">
                                <PaginationItem v-if="item.type === 'page'" :value="item.value" :is-active="item.value === paginationProps.page">
                                    {{ item.value }}
                                </PaginationItem>
                            </template>
                            <PaginationNext />
                        </PaginationContent>
                    </Pagination>
                </div>

                <p v-else class="text-gray-500">No invoices found.</p>
            </div>
        </div>

        <!-- Modal -->
        <InvoiceForm
            :isOpen="isModalOpen"
            :clients="clients"
            :projects="projects"
            :selectedClient="selectedClient"
            :dateFrom="dateFrom"
            :dateTo="dateTo"
            :invoice="selectedInvoice"
            @close="closeModal"
            @createInvoice="handleCreateInvoice"
            @update:selectedClient="(val) => (selectedClient = val)"
            @update:dateFrom="(val) => (dateFrom = val)"
            @update:dateTo="(val) => (dateTo = val)"
        />
    </AppLayout>
</template>

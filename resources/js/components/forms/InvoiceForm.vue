<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { Invoice, Projects } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Badge } from '../ui/badge';
import { Checkbox } from '../ui/checkbox';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../ui/table';

const props = defineProps<{
    isOpen: boolean;
    clients: { id: number; name: string }[];
    projects: Projects[];
    selectedClient: number | null;
    dateFrom: string;
    dateTo: string;
    invoice?: Invoice | null;
}>();

const emit = defineEmits(['close', 'createInvoice', 'update:selectedClient', 'update:dateFrom', 'update:dateTo']);

const formatDate = (date: string | undefined | null) => {
    if (!date) return '';
    return new Date(date).toISOString().split('T')[0]; // YYYY-MM-DD
};

// Only local state for PayPal link
const paypalLink = ref('');

// --- Invoice form ---
const form = useForm({
    client_id: props.invoice?.client_id ?? props.selectedClient ?? null,
    // paypal_link: props.invoice?.paypal_link ?? '',
    date_from: formatDate(props.invoice?.date_from ?? props.dateFrom),
    date_to: formatDate(props.invoice?.date_to ?? props.dateTo),
    projects: props.invoice
        ? props.invoice.projects.map((p: any) => (typeof p === 'number' ? p : p.id)) // ✅ IDs only
        : [],
});

// Update form when invoice prop changes
watch(
    () => props.invoice,
    (invoice) => {
        if (invoice) {
            form.client_id = invoice.client_id;
            // form.paypal_link = invoice.paypal_link ?? '';
            form.date_from = formatDate(invoice.date_from);
            form.date_to = formatDate(invoice.date_to);
            form.projects = invoice.projects.map((p: any) => (typeof p === 'number' ? p : p.id));
        } else {
            form.client_id = props.selectedClient ?? null;
            // form.paypal_link = '';
            form.date_from = formatDate(props.dateFrom);
            form.date_to = formatDate(props.dateTo);
            form.projects = [];
        }
    },
    { immediate: true },
);

watch(
    () => form.client_id,
    (val) => {
        emit('update:selectedClient', val);
        // updateFilters(); // fetch projects for new client
    },
);

// const selectedProjects = reactive<number[]>(form.projects);

// Compute total from selected projects
const invoiceTotal = computed(() => props.projects.filter((p) => form.projects.includes(p.id)).reduce((sum, p) => sum + Number(p.total_price), 0));

// const localDateFrom = ref(props.dateFrom);
// const localDateTo = ref(props.dateTo);

watch(
    () => form.date_from,
    (val) => {
        emit('update:dateFrom', val);
        // updateFilters();
    },
);

watch(
    () => form.date_to,
    (val) => {
        emit('update:dateTo', val);
        // updateFilters();
    },
);

// const updateFilters = () => {
//     router.get(
//         route('invoice.index'),
//         {
//             client_id: form.client_id, // ✅ use form.client_id
//             date_from: form.date_from,
//             date_to: form.date_to,
//             invoice_id: props.invoice?.id ?? null, // ✅ include invoice_id when editing
//         },
//         {
//             preserveState: true,
//             replace: true,
//         },
//     );
// };

// --- Submit handler ---
const handleSubmit = () => {
    const isEditing = !!props.invoice;
    if (isEditing) {
        form.put(route('invoice.update', props.invoice!.id), {
            onSuccess: () => {
                emit('close');
                form.reset();
                router.replace(route('invoice.index'));
            },
        });
    } else {
        form.post(route('invoice.store'), {
            onSuccess: () => {
                emit('close');
                console.log(form);
                form.reset();
                router.replace(route('invoice.index'));
            },
        });
    }
};
</script>

<template>
    <Dialog :open="props.isOpen" @update:open="emit('close')">
        <DialogContent class="!max-w-5xl">
            <DialogHeader>
                <DialogTitle>{{ props.invoice ? 'Edit Invoice' : 'Create Invoice' }}</DialogTitle>
            </DialogHeader>

            <div class="space-y-4">
                <!-- Client Select -->
                <div class="space-y-2">
                    <Label>Client</Label>
                    <Select v-model="form.client_id">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Select a client" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="client in props.clients" :key="client.id" :value="client.id">
                                {{ client.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Date Range -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="date-from">From Date</Label>
                        <Input id="date-from" type="date" v-model="form.date_from" />
                    </div>
                    <div class="space-y-2">
                        <Label for="date-to">To Date</Label>
                        <Input id="date-to" type="date" v-model="form.date_to" />
                    </div>
                </div>

                <!-- PayPal Link -->
                <!-- <div class="space-y-2">
                    <Label for="paypal">PayPal Link</Label>
                    <Input id="paypal" v-model="form.paypal_link" placeholder="https://paypal.me/yourlink" />
                </div> -->

                <!-- Projects Table -->
                <div class="mt-6">
                    <Label>Projects for Invoice</Label>
                    <div class="mt-2 rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="text-left">Select</TableHead>
                                    <TableHead class="text-left">Project Name</TableHead>
                                    <TableHead class="text-left">Client</TableHead>
                                    <TableHead class="text-left">Service</TableHead>
                                    <TableHead class="text-left">Date Created</TableHead>
                                    <TableHead class="text-left">Status</TableHead>
                                    <TableHead class="text-left">Price</TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                <template v-if="props.projects.length > 0">
                                    <TableRow v-for="project in props.projects" :key="project.id">
                                        <TableCell>
                                            <Checkbox
                                                :model-value="form.projects.includes(project.id)"
                                                @update:model-value="
                                                    (checked) => {
                                                        if (checked) {
                                                            if (!form.projects.includes(project.id)) {
                                                                form.projects.push(project.id);
                                                            }
                                                        } else {
                                                            const idx = form.projects.indexOf(project.id);
                                                            if (idx > -1) form.projects.splice(idx, 1);
                                                        }
                                                    }
                                                "
                                            />
                                        </TableCell>
                                        <TableCell>{{ project.project_name }}</TableCell>
                                        <TableCell>{{ project.client?.name ?? 'N/A' }}</TableCell>
                                        <TableCell>{{ project.service?.name ?? 'N/A' }}</TableCell>
                                        <TableCell>{{ new Date(project.created_at).toLocaleDateString() }}</TableCell>
                                        <TableCell>
                                            <Badge>{{ project.status }}</Badge>
                                        </TableCell>
                                        <TableCell class="text-left">${{ Number(project.total_price).toFixed(2) }}</TableCell>
                                    </TableRow>
                                </template>

                                <template v-else>
                                    <TableRow>
                                        <TableCell colspan="7" class="py-6 text-gray-500"> No projects found, please select a client. </TableCell>
                                    </TableRow>
                                </template>
                            </TableBody>
                        </Table>
                    </div>

                    <!-- Total -->
                    <div class="mt-4 flex justify-end">
                        <p class="text-lg font-semibold">Total: ${{ invoiceTotal.toFixed(2) }}</p>
                    </div>
                </div>
            </div>

            <DialogFooter class="mt-6">
                <Button variant="outline" @click="emit('close')">Cancel</Button>
                <Button type="button" @click="handleSubmit" :disabled="form.processing">
                    <span v-if="form.processing" class="mr-2 animate-spin">⏳</span>
                    {{ props.invoice ? 'Save Changes' : 'Create Invoice' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { Projects } from '@/types';
import { ref } from 'vue';
import { TableBody, TableCell, TableHead, TableHeader, TableRow } from '../ui/table';

const props = defineProps<{
    isOpen: boolean;
    clients: { id: number; name: string }[];
    projects: Projects[];
    selectedClient: number | null;
    dateFrom: string;
    dateTo: string;
}>();

const emit = defineEmits(['close', 'createInvoice', 'update:selectedClient', 'update:dateFrom', 'update:dateTo']);

// Only local state for PayPal link
const paypalLink = ref('');

// Handle submit
const handleCreate = () => {
    if (!props.selectedClient) return;
    emit('createInvoice', {
        client_id: props.selectedClient,
        paypal_link: paypalLink.value,
        date_from: props.dateFrom,
        date_to: props.dateTo,
    });
    emit('close');
};
</script>

<template>
    <Dialog :open="props.isOpen" @update:open="emit('close')">
        <DialogContent class="!max-w-5xl">
            <DialogHeader>
                <DialogTitle>Create Invoice</DialogTitle>
            </DialogHeader>

            <div class="space-y-4">
                <!-- Client Select -->
                <div class="space-y-2">
                    <Label>Client</Label>
                    <Select v-model="props.selectedClient" @update:model-value="(val) => emit('update:selectedClient', val)">
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
                        <Input id="date-from" type="date" v-model="props.dateFrom" @input="emit('update:dateFrom', props.dateFrom)" />
                    </div>
                    <div class="space-y-2">
                        <Label for="date-to">To Date</Label>
                        <Input id="date-to" type="date" v-model="props.dateTo" @input="emit('update:dateTo', props.dateTo)" />
                    </div>
                </div>

                <!-- PayPal Link -->
                <div class="space-y-2">
                    <Label for="paypal">PayPal Link</Label>
                    <Input id="paypal" v-model="paypalLink" placeholder="https://paypal.me/yourlink" />
                </div>

                <!-- Projects Table -->
                <div v-if="props.projects.length > 0" class="mt-6">
                    <Label>Projects for Invoice</Label>
                    <div class="mt-2 rounded-lg border">
                        <TableHeader>
                            <TableRow>
                                <TableHead class="text-left">Project Name</TableHead>
                                <TableHead class="text-left">Client</TableHead>
                                <TableHead class="text-left">Service</TableHead>
                                <TableHead class="text-left">Status</TableHead>
                                <TableHead class="text-right">Price</TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            <TableRow v-for="project in props.projects" :key="project.id">
                                <TableCell>{{ project.project_name }}</TableCell>
                                <TableCell>{{ project.client?.name ?? 'N/A' }}</TableCell>
                                <TableCell>{{ project.service?.name ?? 'N/A' }}</TableCell>
                                <TableCell>{{ project.status }}</TableCell>
                                <TableCell class="text-right">${{ Number(project.total_price).toFixed(2) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">No projects found for the selected client and date range.</p>
            </div>

            <DialogFooter class="mt-6">
                <Button variant="outline" @click="emit('close')">Cancel</Button>
                <Button @click="handleCreate">Create Invoice</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

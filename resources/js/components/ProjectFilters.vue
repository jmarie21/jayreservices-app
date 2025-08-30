<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { reactive, watch } from 'vue';

interface Props {
    filters: {
        status?: string;
        date_from?: string;
        date_to?: string;
    };
    role?: 'admin' | 'client';
}

const props = defineProps<Props>();
const emit = defineEmits(['update:filters']);

const localFilters = reactive({ ...props.filters });

watch(
    () => ({ ...localFilters }),
    (newFilters) => {
        emit('update:filters', newFilters);
    },
    { deep: true },
);

const handleStatusChange = (value: any) => {
    localFilters.status = value === 'all' || !value ? '' : String(value);
};
</script>

<template>
    <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-end">
        <!-- Status filter -->
        <div class="space-y-2">
            <Label for="status-select">Status</Label>
            <Select :model-value="localFilters.status || 'all'" @update:model-value="handleStatusChange">
                <SelectTrigger id="status-select" class="w-[180px]">
                    <SelectValue placeholder="Select status" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Statuses</SelectItem>

                    <!-- Admin sees raw statuses -->
                    <template v-if="props.role === 'admin'">
                        <SelectItem value="todo">To Do</SelectItem>
                        <SelectItem value="in_progress">In Progress</SelectItem>
                        <SelectItem value="for_qa">For QA</SelectItem>
                        <SelectItem value="done_qa">Done QA</SelectItem>
                        <SelectItem value="sent_to_client">Sent to Client</SelectItem>
                        <SelectItem value="revision">Revision</SelectItem>
                        <SelectItem value="revision_completed">Revision Completed</SelectItem>
                        <SelectItem value="backlog">Backlog</SelectItem>
                    </template>

                    <!-- Client sees simplified statuses -->
                    <template v-else>
                        <SelectItem value="pending">Pending</SelectItem>
                        <SelectItem value="in_progress">In Progress</SelectItem>
                        <SelectItem value="completed">Completed</SelectItem>
                    </template>
                </SelectContent>
            </Select>
        </div>

        <!-- Date filters -->
        <div class="space-y-2">
            <Label for="date-from">From Date</Label>
            <Input id="date-from" type="date" v-model="localFilters.date_from" class="w-[180px]" />
        </div>
        <div class="space-y-2">
            <Label for="date-to">To Date</Label>
            <Input id="date-to" type="date" v-model="localFilters.date_to" class="w-[180px]" />
        </div>
    </div>
</template>

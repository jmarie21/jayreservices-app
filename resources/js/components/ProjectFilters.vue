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
}

const props = defineProps<Props>();
const emit = defineEmits(['update:filters']);

// Local copy of filters for 2-way binding
const localFilters = reactive({ ...props.filters });

// Watch for changes and emit
watch(
    () => ({ ...localFilters }),
    (newFilters) => {
        emit('update:filters', newFilters);
    },
    { deep: true },
);

// Handle select change
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
                    <SelectItem value="pending">Pending</SelectItem>
                    <SelectItem value="in_progress">In Progress</SelectItem>
                    <SelectItem value="completed">Completed</SelectItem>
                </SelectContent>
            </Select>
        </div>

        <!-- Date from filter -->
        <div class="space-y-2">
            <Label for="date-from">From Date</Label>
            <Input id="date-from" type="date" v-model="localFilters.date_from" class="w-[180px]" />
        </div>

        <!-- Date to filter -->
        <div class="space-y-2">
            <Label for="date-to">To Date</Label>
            <Input id="date-to" type="date" v-model="localFilters.date_to" class="w-[180px]" />
        </div>
    </div>
</template>

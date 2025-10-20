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
        editor_id?: string;
    };
    role?: 'admin' | 'editor' | 'client';
    editors?: { id: number; name: string }[];
}

const props = defineProps<Props>();
const emit = defineEmits(['update:filters']);

const localFilters = reactive({ ...props.filters });

// Format date to ensure YYYY-MM-DD format
const formatDate = (dateString: string | undefined): string => {
    if (!dateString) return '';

    // If already in YYYY-MM-DD format, return as is
    if (/^\d{4}-\d{2}-\d{2}$/.test(dateString)) {
        return dateString;
    }

    // Parse and format
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '';

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

watch(
    () => ({ ...localFilters }),
    (newFilters) => {
        // Format dates before emitting
        const formattedFilters = {
            ...newFilters,
            date_from: formatDate(newFilters.date_from),
            date_to: formatDate(newFilters.date_to),
        };

        // Debug log (remove after fixing)
        console.log('Emitting filters:', formattedFilters);

        emit('update:filters', formattedFilters);
    },
    { deep: true },
);

const handleStatusChange = (value: any) => {
    localFilters.status = value === 'all' || !value ? '' : String(value);
};

const handleEditorChange = (value: any) => {
    localFilters.editor_id = value === 'all' || !value ? '' : String(value);
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

                    <!-- Admin & Editor see raw statuses -->
                    <template v-if="props.role === 'admin' || props.role === 'editor'">
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

        <!-- Editor filter (Admin only) -->
        <div v-if="props.role === 'admin'" class="space-y-2">
            <Label for="editor-select">Editor</Label>
            <Select :model-value="localFilters.editor_id || 'all'" @update:model-value="handleEditorChange">
                <SelectTrigger id="editor-select" class="w-[180px]">
                    <SelectValue placeholder="Select editor" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Editors</SelectItem>
                    <SelectItem value="unassigned">Unassigned</SelectItem>
                    <SelectItem v-for="editor in props.editors" :key="editor.id" :value="String(editor.id)">
                        {{ editor.name }}
                    </SelectItem>
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

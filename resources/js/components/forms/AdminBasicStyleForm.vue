<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { AdminBasicForm } from '@/types/app-page-prop';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    open: boolean;
    basePrice: number;
    serviceId: number;
    project?: AdminBasicForm | null;
    clients?: { id: number; name: string }[]; // passed from Laravel controller
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

// Agent selection
const agentOption = ref<'with-agent' | 'no-agent' | ''>('');

// Per property selection
const perPropertyOption = ref<'add-per-property' | 'no' | ''>('');

// Form initialization
const form = useForm({
    client_id: props.project?.client_id ?? '',
    style: props.project?.style ?? '',
    project_name: props.project?.project_name ?? '',
    format: props.project?.format ?? '',
    camera: props.project?.camera ?? '',
    quality: props.project?.quality ?? '',
    music: props.project?.music ?? '',
    music_link: props.project?.music_link ?? '',
    file_link: props.project?.file_link ?? '',
    notes: props.project?.notes ?? '',
    total_price: Number(props.project?.total_price ?? props.basePrice),
    with_agent: props.project?.with_agent ?? false,
    service_id: props.serviceId,
    per_property: props.project?.per_property ?? false,
});

// Computed total price
const totalPrice = computed(() => {
    let extra = 0;
    if (form.style === 'basic video') {
        if (form.format === 'horizontal') extra += 40;
        else if (form.format === 'vertical') extra += 25;
        else if (form.format === 'horizontal and vertical package') extra += 65;
    } else if (form.style === 'basic drone only') {
        if (form.format === 'horizontal') extra += 25;
        else if (form.format === 'vertical') extra += 20;
        else if (form.format === 'horizontal and vertical package') extra += 45;
    }
    if (agentOption.value === 'with-agent') extra += 10;
    if (perPropertyOption.value === 'add-per-property') extra += 5;
    return extra;
});

// Watch total price
watch(
    totalPrice,
    (val) => {
        form.total_price = val;
    },
    { immediate: true },
);

// Watch toggles
watch(agentOption, () => (form.with_agent = agentOption.value === 'with-agent'));
watch(perPropertyOption, () => (form.per_property = perPropertyOption.value === 'add-per-property'));

// Submit handler
const handleSubmit = () => {
    const isEditing = !!props.project;

    if (isEditing) {
        form.put(route('projects.admin_update', props.project!.id), {
            onSuccess: () => {
                toast.success('Project updated successfully!');
                emit('close');
            },
            onError: (error) => console.error('Validation errors:', form.errors, error),
        });
    } else {
        form.post(route('projects.admin_store'), {
            onSuccess: () => {
                toast.success('Project created successfully!');
                emit('close');
            },
            onError: (error) => console.error('Validation errors:', form.errors, error),
        });
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="(v) => !v && emit('close')">
        <DialogContent class="max-h-[90vh] !w-full !max-w-6xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle>
                    {{ props.project ? `Edit Project - ${form.project_name}` : 'Create Project (Admin)' }}
                </DialogTitle>
            </DialogHeader>

            <form @submit.prevent="handleSubmit">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <!-- Assign Client -->
                    <div class="space-y-2">
                        <Label>Assign to Client</Label>
                        <Select v-model="form.client_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Select client" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="client in props.clients" :key="client.id" :value="client.id">
                                    {{ client.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <span v-if="form.errors.client_id" class="text-sm text-red-500">{{ form.errors.client_id }}</span>
                    </div>

                    <!-- Style -->
                    <div class="space-y-2">
                        <Label>Select Style</Label>
                        <Select v-model="form.style">
                            <SelectTrigger><SelectValue placeholder="Style" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="basic video">Basic Video</SelectItem>
                                <SelectItem value="basic drone only">Basic Drone Only</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Project Name -->
                    <div class="space-y-2">
                        <Label>Project Name</Label>
                        <Input v-model="form.project_name" placeholder="Enter project name" />
                    </div>

                    <!-- Format -->
                    <div class="space-y-2">
                        <Label>Video Format</Label>
                        <Input v-model="form.format" placeholder="e.g. Horizontal, Vertical" />
                    </div>

                    <!-- Camera -->
                    <div class="space-y-2">
                        <Label>Camera</Label>
                        <Input v-model="form.camera" placeholder="Enter camera details" />
                    </div>

                    <!-- Quality -->
                    <div class="space-y-2">
                        <Label>Video Quality</Label>
                        <Select v-model="form.quality">
                            <SelectTrigger><SelectValue placeholder="Select quality" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="4k quality">4K Quality</SelectItem>
                                <SelectItem value="1080p HD quality">1080P HD Quality</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- File Link -->
                    <div class="space-y-2">
                        <Label>File Link</Label>
                        <Input v-model="form.file_link" placeholder="Enter file link" />
                    </div>

                    <!-- Notes -->
                    <div class="space-y-2">
                        <Label>Notes</Label>
                        <Input v-model="form.notes" placeholder="Optional notes" />
                    </div>
                </div>

                <div class="mt-8 text-xl font-semibold">Total: ${{ form.total_price.toFixed(2) }}</div>

                <div class="mt-8 flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        <span v-if="form.processing" class="mr-2 animate-spin">⏳</span>
                        {{ props.project ? 'Save Changes' : 'Create Project' }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>

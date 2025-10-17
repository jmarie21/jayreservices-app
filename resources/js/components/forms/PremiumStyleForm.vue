<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { AppPageProps, User } from '@/types';
import { PremiumForm } from '@/types/app-page-prop';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Textarea } from '../ui/textarea';

const props = defineProps<{
    open: boolean;
    serviceId: number;
    project?: PremiumForm | null;
}>();
const { props: page } = usePage<AppPageProps>();
const userRole = page.auth.user.role;
const isAdmin = computed(() => userRole === 'admin');
const { clients } = usePage<AppPageProps<{ clients: User[] }>>().props;

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const agentOption = ref<'with-agent' | 'no-agent' | ''>('');
const perPropertyOption = ref<'add-per-property' | 'no' | ''>('');
const rushOption = ref<'true' | 'false' | ''>('');

interface Option {
    id: string;
    label: string;
    link?: string | null;
}

// Effects & captions options
const effectsOptions: Option[] = [
    { id: 'Ken Burns', label: 'Ken Burns', link: 'https://www.youtube.com/watch?v=lIK2S0eIvwY&list=TLGG7aKmePKcyR8xMzEwMjAyNQ' },
    { id: 'Building A House Transition', label: 'Building A House Transition', link: 'https://www.youtube.com/watch?v=ERrkbiFAOow' },
    { id: 'Painting Transition', label: 'Painting Transition (Add $10)', link: 'https://youtu.be/vCW4H7puU1c?si=GoI72aCscroTvYqk)' },
    { id: 'Earth Zoom Transition', label: 'Earth Zoom Transition (Add $15)', link: 'https://www.youtube.com/watch?v=dyuRMbjDJas&feature=youtu.be' },
    { id: 'No Effects', label: 'I DONT WANT ANY TRANSITIONS FOR THIS PROJECT' },
];

const captionsOptions: Option[] = [
    { id: '3D Text behind the Agent Talking', label: '3D Text behind the Agent Talking (Add $10)' },
    { id: 'Captions while the agent is talking', label: 'Captions while the agent is talking (Add $10)' },
    { id: 'No Captions', label: 'No text or captions needed' },
];

// Initialize form
const form = useForm<PremiumForm>({
    style: props.project?.style ?? '',
    project_name: props.project?.project_name ?? '',
    format: props.project?.format ?? '',
    camera: props.project?.camera ?? '',
    quality: props.project?.quality ?? '',
    music: props.project?.music ?? '',
    music_link: props.project?.music_link ?? '',
    file_link: props.project?.file_link ?? '',
    notes: props.project?.notes ?? '',
    total_price: 0, // Will be calculated
    with_agent: props.project?.with_agent ?? false,
    service_id: props.serviceId,
    rush: props.project?.rush ?? false,
    extra_fields: {
        effects: props.project?.extra_fields?.effects ? [...props.project.extra_fields.effects] : [],
        captions: props.project?.extra_fields?.captions ? [...props.project.extra_fields.captions] : [],
    },
    per_property: props.project?.per_property ?? false,
    ...(isAdmin.value ? { client_id: props.project?.client_id ?? null } : {}),
});

// Computed total price
const totalPrice = computed(() => {
    let total = 0;

    // Style & format extras
    if (form.style === 'Premium video') {
        if (form.format === 'horizontal') total += 80;
        else if (form.format === 'vertical') total += 50;
        else if (form.format === 'horizontal and vertical package') total += 130;
    } else if (form.style === 'Premium drone only') {
        if (form.format === 'horizontal') total += 45;
        else if (form.format === 'vertical') total += 40;
        else if (form.format === 'horizontal and vertical package') total += 85;
    }

    // Agent, per-property & rush
    if (agentOption.value === 'with-agent') total += 10;
    if (perPropertyOption.value === 'add-per-property') total += 5;
    if (rushOption.value === 'true') total += 20;

    // Captions
    if (form.extra_fields?.captions.includes('3D Text behind the Agent Talking')) total += 10;
    if (form.extra_fields?.captions.includes('Captions while the agent is talking')) total += 10;

    //Effects
    if (form.extra_fields?.effects.includes('Painting Transition')) total += 10;
    if (form.extra_fields?.effects.includes('Earth Zoom Transition')) total += 15;
    // if (form.extra_fields.effects.includes('Day to Night AI')) total += 15;

    return total;
});

// Watch totalPrice to update form
watch(
    totalPrice,
    (val) => {
        form.total_price = val;
    },
    { immediate: true },
);

// Update flags when options change
watch(agentOption, () => (form.with_agent = agentOption.value === 'with-agent'));
watch(perPropertyOption, () => (form.per_property = perPropertyOption.value === 'add-per-property'));

// Format options
const formatOptions = computed(() => {
    if (form.style === 'Premium video') {
        return [
            { value: 'horizontal', label: 'Horizontal ($80)' },
            { value: 'vertical', label: 'Vertical ($50)' },
            { value: 'horizontal and vertical package', label: 'Horizontal & Vertical Package ($130)' },
        ];
    } else if (form.style === 'Premium drone only') {
        return [
            { value: 'horizontal', label: 'Horizontal ($45)' },
            { value: 'vertical', label: 'Vertical ($40)' },
            { value: 'horizontal and vertical package', label: 'Horizontal & Vertical Package ($85)' },
        ];
    } else {
        return [
            { value: 'horizontal', label: 'Horizontal' },
            { value: 'vertical', label: 'Vertical' },
            { value: 'horizontal and vertical package', label: 'Horizontal & Vertical Package' },
        ];
    }
});

// Computed to get the label of the selected format
const selectedFormatLabel = computed(() => {
    const option = formatOptions.value.find((o) => o.value === form.format);
    return option ? option.label : '';
});

// Handle checkbox changes
function handleEffectChange(id: string, checked: boolean | 'indeterminate') {
    form.extra_fields ??= { effects: [], captions: [] };
    const isChecked = checked === true;
    const arr = [...form.extra_fields.effects];
    if (isChecked && !arr.includes(id)) arr.push(id);
    if (!isChecked && arr.includes(id)) arr.splice(arr.indexOf(id), 1);
    form.extra_fields.effects = arr;
    form.extra_fields = { ...form.extra_fields };
}

function handleCaptionChange(id: string, checked: boolean | 'indeterminate') {
    form.extra_fields ??= { effects: [], captions: [] };
    const isChecked = checked === true;
    const arr = [...form.extra_fields.captions];
    if (isChecked && !arr.includes(id)) arr.push(id);
    if (!isChecked && arr.includes(id)) arr.splice(arr.indexOf(id), 1);
    form.extra_fields.captions = arr;
    form.extra_fields = { ...form.extra_fields };
}

// Watch project changes and modal open
watch(
    [() => props.project, () => props.open],
    ([project, open]) => {
        if (!open) return;
        if (project) {
            form.style = project.style || '';
            form.format = project.format || '';
            form.project_name = project.project_name || '';
            form.camera = project.camera || '';
            form.quality = project.quality || '';
            form.music = project.music || '';
            form.music_link = project.music_link || '';
            form.file_link = project.file_link || '';
            form.notes = project.notes || '';
            agentOption.value = project.with_agent ? 'with-agent' : 'no-agent';
            perPropertyOption.value = project.per_property ? 'add-per-property' : 'no';
            rushOption.value = project.rush ? 'true' : 'false';
            form.extra_fields = {
                effects: project.extra_fields?.effects ? [...project.extra_fields.effects] : [],
                captions: project.extra_fields?.captions ? [...project.extra_fields.captions] : [],
            };
        } else {
            form.style = '';
            form.format = '';
            form.project_name = '';
            form.camera = '';
            form.quality = '';
            form.music = '';
            form.music_link = '';
            form.file_link = '';
            form.notes = '';
            agentOption.value = '';
            perPropertyOption.value = '';
            rushOption.value = '';
            form.extra_fields = { effects: [], captions: [] };
        }
    },
    { immediate: true },
);

// Submit handler
const handleSubmit = () => {
    const isEditing = !!props.project;

    // Determine if the current user is an admin
    const isAdminUser = isAdmin.value; // assuming you already have `isAdmin` ref/computed

    // Choose the correct route names based on role
    const createRoute = isAdminUser ? 'admin.project.create' : 'projects.store';
    const updateRoute = isAdminUser ? 'admin.project.update' : 'projects.client_update';

    if (isEditing) {
        form.put(route(updateRoute, props.project!.id), {
            onSuccess: () => {
                toast.success('Updated successfully!', {
                    description: isAdminUser ? 'Project updated successfully (admin side).' : 'Your order was updated successfully!',
                    position: 'top-right',
                });
                emit('close');
            },
            onError: (error) => {
                console.error('Validation errors:', form.errors, error);
            },
        });
    } else {
        form.post(route(createRoute), {
            onSuccess: () => {
                toast.success('Project created!', {
                    description: isAdminUser ? 'Project has been created successfully (admin side).' : 'Your order has been placed.',
                    position: 'top-right',
                });
                emit('close');
            },
            onError: (error) => {
                console.error('Validation errors:', form.errors, error);
            },
        });
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="(v) => !v && emit('close')">
        <DialogContent class="max-h-[90vh] !w-full !max-w-6xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle>
                    {{ props.project ? `Edit Project - ${form.project_name}` : 'Order: Premium Style' }}
                </DialogTitle>
            </DialogHeader>

            <form @submit.prevent="handleSubmit">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <!-- Client Dropdown (Admin only) -->
                    <div v-if="isAdmin" class="space-y-2">
                        <Label>Client</Label>
                        <Select v-model="form.client_id">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select a client" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="client in clients" :key="client.id" :value="client.id">
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
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Style" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="Premium video">Premium Video</SelectItem>
                                <SelectItem value="Premium drone only">Premium Drone Only</SelectItem>
                            </SelectContent>
                        </Select>
                        <span v-if="form.errors.style" class="text-sm text-red-500">{{ form.errors.style }}</span>
                    </div>

                    <!-- Project Name -->
                    <div class="space-y-2">
                        <Label>Project Name</Label>
                        <Input v-model="form.project_name" placeholder="Enter your project name" />
                        <span v-if="form.errors.project_name" class="text-sm text-red-500">{{ form.errors.project_name }}</span>
                    </div>

                    <!-- Format -->
                    <div class="space-y-2">
                        <Label>Video Format</Label>
                        <Select v-model="form.format">
                            <SelectTrigger class="w-full">
                                <!-- Display selected label -->
                                <SelectValue :value="form.format" placeholder="Format">
                                    {{ selectedFormatLabel }}
                                </SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="option in formatOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <span v-if="form.errors.format" class="text-sm text-red-500">{{ form.errors.format }}</span>
                    </div>

                    <!-- Camera -->
                    <div class="space-y-2">
                        <Label>Camera</Label>
                        <Input v-model="form.camera" placeholder="Enter your camera brand and model" />
                        <span v-if="form.errors.camera" class="text-sm text-red-500">{{ form.errors.camera }}</span>
                    </div>

                    <!-- Agent Option -->
                    <div class="space-y-2">
                        <Label>With agent or voiceover?</Label>
                        <Select v-model="agentOption">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Agent or Voiceover?" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="with-agent">With Agent (Add $10)</SelectItem>
                                <SelectItem value="no-agent">No Agent</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Quality -->
                    <div class="space-y-2">
                        <Label>Video Quality</Label>
                        <Select v-model="form.quality">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Quality" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="4k quality">4K Quality</SelectItem>
                                <SelectItem value="1080p HD quality">1080P HD Quality</SelectItem>
                            </SelectContent>
                        </Select>
                        <span v-if="form.errors.quality" class="text-sm text-red-500">{{ form.errors.quality }}</span>
                    </div>

                    <!-- Music -->
                    <div class="space-y-2">
                        <Label>Music Preference</Label>
                        <Select v-model="form.music">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select type of music" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="copyright music with vocals">Copyright music with vocals</SelectItem>
                                <SelectItem value="copyright music without vocals">Copyright music without vocals</SelectItem>
                                <SelectItem value="IG trendy music with vocals">IG trendy music with vocals</SelectItem>
                                <SelectItem value="IG trendy music without vocals">IG trendy music without vocals</SelectItem>
                                <SelectItem value="I will provide my own music">I will provide my own music</SelectItem>
                            </SelectContent>
                        </Select>
                        <span v-if="form.errors.music" class="text-sm text-red-500">{{ form.errors.music }}</span>
                    </div>

                    <!-- Music Link -->
                    <div class="space-y-2">
                        <Label>If providing music, link or title</Label>
                        <Input v-model="form.music_link" placeholder="Enter song link and title" />
                        <span v-if="form.errors.music_link" class="text-sm text-red-500">{{ form.errors.music_link }}</span>
                    </div>

                    <!-- Per Property Option -->
                    <div class="space-y-2">
                        <Label>With per property line?</Label>
                        <Select v-model="perPropertyOption">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select an option" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="add-per-property">Add per property line (Add $5)</SelectItem>
                                <SelectItem value="no">No</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- File Link -->
                    <div class="space-y-2">
                        <Label>File Link</Label>
                        <Input v-model="form.file_link" placeholder="Enter your file link" />
                        <span v-if="form.errors.file_link" class="text-sm text-red-500">{{ form.errors.file_link }}</span>
                    </div>

                    <!-- Rush Option -->
                    <div class="space-y-2">
                        <Label>Rush (with additional charges)</Label>
                        <Select v-model="rushOption" @update:modelValue="(val) => (form.rush = val === 'true')">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select option" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="true">Yes ($20)</SelectItem>
                                <SelectItem value="false">No</SelectItem>
                            </SelectContent>
                        </Select>

                        <span v-if="form.errors.rush" class="text-sm text-red-500">
                            {{ form.errors.rush }}
                        </span>
                    </div>

                    <!-- Notes -->
                    <div class="space-y-2">
                        <Label>More Instructions (Optional)</Label>
                        <Textarea v-model="form.notes" placeholder="Enter more instructions" class="min-h-[120px]" />
                    </div>

                    <!-- Customize the Effects -->
                    <div class="space-y-2">
                        <Label>Do you want to customize the effects?</Label>
                        <div class="flex flex-col gap-2">
                            <div v-for="effect in effectsOptions" :key="effect.id" class="mb-1 flex items-center gap-2">
                                <Checkbox
                                    :id="effect.id"
                                    :model-value="form.extra_fields?.effects.includes(effect.id)"
                                    @update:model-value="(value) => handleEffectChange(effect.id, value)"
                                />
                                <label :for="effect.id" class="cursor-pointer">{{ effect.label }}</label>
                                <a
                                    v-if="effect.link"
                                    :href="effect.link"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-sm text-blue-400 hover:underline"
                                >
                                    (see sample)
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- 3D Text and Captions -->
                    <div class="space-y-2">
                        <Label>Do you need 3D text and captions?</Label>
                        <div class="flex flex-col gap-2">
                            <div v-for="caption in captionsOptions" :key="caption.id" class="mb-1 flex items-center gap-2">
                                <Checkbox
                                    :id="caption.id"
                                    :model-value="form.extra_fields?.captions.includes(caption.id)"
                                    @update:model-value="(value) => handleCaptionChange(caption.id, value)"
                                />
                                <label :for="caption.id" class="cursor-pointer">
                                    {{ caption.label }}
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Total & Submit -->
                <div class="mt-8 text-xl font-semibold">Total: ${{ Number(form.total_price).toFixed(2) }}</div>
                <div class="mt-8 flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        <span v-if="form.processing" class="mr-2 animate-spin">⏳</span>
                        {{ props.project ? 'Save Changes' : 'Place Order' }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>

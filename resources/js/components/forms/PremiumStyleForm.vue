<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PremiumForm } from '@/types/app-page-prop';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    open: boolean;
    serviceId: number;
    project?: PremiumForm | null;
}>();
const emit = defineEmits<{
    (e: 'close'): void;
}>();

const agentOption = ref<'with-agent' | 'no-agent' | ''>('');
const perPropertyOption = ref<'add-per-property' | 'no' | ''>('');

interface Option {
    id: string;
    label: string;
}

// Effects & captions options
const effectsOptions: Option[] = [
    { id: 'Ken Burns', label: 'Ken Burns' },
    { id: 'Building A House Transition', label: 'Building A House Transition' },
    { id: 'Virtual Staging AI', label: 'Virtual Staging AI ($20)' },
    { id: 'Day to Night AI', label: 'Day to Night AI ($15)' },
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
    company_name: props.project?.company_name ?? '',
    contact: props.project?.contact ?? '',
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
    extra_fields: {
        effects: props.project?.extra_fields?.effects ? [...props.project.extra_fields.effects] : [],
        captions: props.project?.extra_fields?.captions ? [...props.project.extra_fields.captions] : [],
    },
    per_property: props.project?.per_property ?? false,
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

    // Agent & per-property
    if (agentOption.value === 'with-agent') total += 10;
    if (perPropertyOption.value === 'add-per-property') total += 5;

    // Captions
    if (form.extra_fields.captions.includes('3D Text behind the Agent Talking')) total += 10;
    if (form.extra_fields.captions.includes('Captions while the agent is talking')) total += 10;

    //Effects
    if (form.extra_fields.effects.includes('Virtual Staging AI')) total += 20;
    if (form.extra_fields.effects.includes('Day to Night AI')) total += 15;

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

// Handle checkbox changes
function handleEffectChange(id: string, checked: boolean | 'indeterminate') {
    const isChecked = checked === true;
    const arr = [...form.extra_fields.effects];
    if (isChecked && !arr.includes(id)) arr.push(id);
    if (!isChecked && arr.includes(id)) arr.splice(arr.indexOf(id), 1);
    form.extra_fields.effects = arr;
    form.extra_fields = { ...form.extra_fields };
}

function handleCaptionChange(id: string, checked: boolean | 'indeterminate') {
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
            form.company_name = project.company_name || '';
            form.contact = project.contact || '';
            form.project_name = project.project_name || '';
            form.camera = project.camera || '';
            form.quality = project.quality || '';
            form.music = project.music || '';
            form.music_link = project.music_link || '';
            form.file_link = project.file_link || '';
            form.notes = project.notes || '';
            agentOption.value = project.with_agent ? 'with-agent' : 'no-agent';
            perPropertyOption.value = project.per_property ? 'add-per-property' : 'no';
            form.extra_fields = {
                effects: project.extra_fields?.effects ? [...project.extra_fields.effects] : [],
                captions: project.extra_fields?.captions ? [...project.extra_fields.captions] : [],
            };
        } else {
            form.style = '';
            form.format = '';
            form.company_name = '';
            form.contact = '';
            form.project_name = '';
            form.camera = '';
            form.quality = '';
            form.music = '';
            form.music_link = '';
            form.file_link = '';
            form.notes = '';
            agentOption.value = '';
            perPropertyOption.value = '';
            form.extra_fields = { effects: [], captions: [] };
        }
    },
    { immediate: true },
);

// Submit handler
const handleSubmit = () => {
    const isEditing = !!props.project;
    const submitData = { ...form, extra_fields: { effects: [...form.extra_fields.effects], captions: [...form.extra_fields.captions] } };

    if (isEditing) {
        form.transform(() => submitData).put(route('projects.client_update', props.project!.id), {
            onSuccess: () => {
                toast.success('Updated successfully!', { description: 'Your order was updated successfully!', position: 'top-right' });
                emit('close');
            },
        });
    } else {
        form.transform(() => submitData).post(route('projects.store'), {
            onSuccess: () => {
                toast.success('Order placed', { description: 'Your order has been placed.', position: 'top-right' });
                emit('close');
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

                    <!-- Company Name -->
                    <div class="space-y-2">
                        <Label>Company Name</Label>
                        <Input v-model="form.company_name" placeholder="Enter your company name" />
                        <span v-if="form.errors.company_name" class="text-sm text-red-500">{{ form.errors.company_name }}</span>
                    </div>

                    <!-- Contact -->
                    <div class="space-y-2">
                        <Label>Email or Social Media</Label>
                        <Input v-model="form.contact" placeholder="Enter your email or any social media" />
                        <span v-if="form.errors.contact" class="text-sm text-red-500">{{ form.errors.contact }}</span>
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
                                <SelectValue placeholder="Format" />
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

                    <!-- Customize the Effects -->
                    <div class="space-y-2">
                        <Label>Do you want to customize the effects?</Label>
                        <div class="flex flex-col gap-2">
                            <div v-for="effect in effectsOptions" :key="effect.id" class="mb-1 flex items-center gap-2">
                                <Checkbox
                                    :id="effect.id"
                                    :model-value="form.extra_fields.effects.includes(effect.id)"
                                    @update:model-value="(value) => handleEffectChange(effect.id, value)"
                                />
                                <label :for="effect.id" class="cursor-pointer">{{ effect.label }}</label>
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
                                    :model-value="form.extra_fields.captions.includes(caption.id)"
                                    @update:model-value="(value) => handleCaptionChange(caption.id, value)"
                                />
                                <label :for="caption.id" class="cursor-pointer">
                                    {{ caption.label }}
                                </label>
                            </div>
                        </div>
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

                    <!-- Notes -->
                    <div class="space-y-2">
                        <Label>More Instructions (Optional)</Label>
                        <Input v-model="form.notes" placeholder="Enter more instructions" />
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

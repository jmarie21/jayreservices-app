<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { LuxuryForm } from '@/types/app-page-prop';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Checkbox } from '../ui/checkbox';

const props = defineProps<{
    open: boolean;
    basePrice: number;
    serviceId: number;
    project?: LuxuryForm | null;
}>();
const emit = defineEmits<{
    (e: 'close'): void;
}>();

const agentOption = ref<'with-agent' | 'no-agent' | ''>('');
const perPropertyOption = ref<'add-per-property' | 'no' | ''>(props.project ? (props.project.per_property ? 'add-per-property' : 'no') : 'no');

interface Option {
    id: string;
    label: string;
}

const effectsOptions: Option[] = [
    { id: 'Ken Burns', label: 'Ken Burns' },
    { id: 'House Drop', label: 'House Drop' },
    { id: 'Pillar Masking', label: 'Pillar Masking  (This is only applicable if you have the footage)' },
    { id: 'Virtual Staging AI', label: 'Virtual Staging AI ($20)' },
    { id: 'Day to Night AI', label: 'Day to Night AI ($15)' },
    { id: 'No Effects', label: 'I DONT WANT ANY TRANSITIONS FOR THIS PROJECT' },
];

const captionsOptions: Option[] = [
    { id: '3D Text behind the Agent Talking', label: '3D Text behind the Agent Talking (ADD $10)' },
    { id: '3D Text tracked on the ground etc.', label: '3D Text tracked on the ground etc. (ADD $15)' },
    { id: 'Captions while the agent is talking', label: 'Captions while the agent is talking (ADD $10)' },
    { id: 'No Captions', label: 'NO NEED TO ADD TEXT OR CAPTIONS' },
];

const form = useForm<LuxuryForm>({
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
    total_price: Number(props.project?.total_price ?? props.basePrice ?? 0),
    with_agent: props.project?.with_agent ?? false,
    service_id: props.serviceId,
    extra_fields: {
        effects: props.project?.extra_fields?.effects ? [...props.project.extra_fields.effects] : [],
        captions: props.project?.extra_fields?.captions ? [...props.project.extra_fields.captions] : [],
    },
    per_property: props.project?.per_property,
});

function calculateTotalPrice() {
    let total = Number(props.basePrice);

    // Style & Format cost
    if (form.style === 'Luxury video') {
        if (form.format === 'horizontal') total += 100;
        else if (form.format === 'vertical') total += 70;
        else if (form.format === 'horizontal and vertical package') total += 100 + 70;
    } else if (form.style === 'Luxury drone only') {
        if (form.format === 'horizontal') total += 60;
        else if (form.format === 'vertical') total += 50;
        else if (form.format === 'horizontal and vertical package') total += 60 + 50;
    }

    // Agent cost
    if (agentOption.value === 'with-agent') total += 10;

    // Per property line
    if (perPropertyOption.value === 'add-per-property') total += 5;

    // Captions cost
    if (form.extra_fields.captions.includes('3D Text behind the Agent Talking')) total += 10;
    if (form.extra_fields.captions.includes('3D Text tracked on the ground etc.')) total += 15;
    if (form.extra_fields.captions.includes('Captions while the agent is talking')) total += 10;

    //Effects
    if (form.extra_fields.effects.includes('Virtual Staging AI')) total += 20;
    if (form.extra_fields.effects.includes('Day to Night AI')) total += 15;

    form.total_price = total;
}

const formatOptions = computed(() => {
    if (form.style === 'Luxury video') {
        return [
            { value: 'horizontal', label: 'Horizontal ($100)' },
            { value: 'vertical', label: 'Vertical ($70)' },
            { value: 'horizontal and vertical package', label: 'Horizontal & Vertical ($170)' },
        ];
    } else if (form.style === 'Luxury drone only') {
        return [
            { value: 'horizontal', label: 'Horizontal ($60)' },
            { value: 'vertical', label: 'Vertical ($50)' },
            { value: 'horizontal and vertical package', label: 'Horizontal & Vertical ($110)' },
        ];
    } else {
        return [
            { value: 'horizontal', label: 'Horizontal' },
            { value: 'vertical', label: 'Vertical' },
            { value: 'horizontal and vertical package', label: 'Horizontal & Vertical' },
        ];
    }
});

watch(agentOption, () => {
    form.with_agent = agentOption.value === 'with-agent';
    calculateTotalPrice();
});
watch(perPropertyOption, () => {
    form.per_property = perPropertyOption.value === 'add-per-property';
    calculateTotalPrice();
});
watch(() => form.extra_fields.captions, calculateTotalPrice, { deep: true });
watch(() => form.style, calculateTotalPrice);
watch(() => form.format, calculateTotalPrice);

// Reset / load project data on modal open
watch(
    [() => props.project, () => props.open],
    ([project, isOpen]) => {
        if (isOpen) {
            if (project) {
                Object.assign(form, {
                    style: project.style || '',
                    company_name: project.company_name || '',
                    contact: project.contact || '',
                    project_name: project.project_name || '',
                    format: project.format || '',
                    camera: project.camera || '',
                    quality: project.quality || '',
                    music: project.music || '',
                    music_link: project.music_link || '',
                    file_link: project.file_link || '',
                    notes: project.notes || '',
                    with_agent: project.with_agent ?? false,
                    per_property: project.per_property ?? false,
                    extra_fields: {
                        effects: project.extra_fields?.effects ? [...project.extra_fields.effects] : [],
                        captions: project.extra_fields?.captions ? [...project.extra_fields.captions] : [],
                    },
                });
                agentOption.value = project.with_agent ? 'with-agent' : 'no-agent';
                perPropertyOption.value = project.per_property ? 'add-per-property' : 'no';
                calculateTotalPrice();
            } else {
                // Reset for new project
                Object.assign(form, {
                    style: '',
                    company_name: '',
                    contact: '',
                    project_name: '',
                    format: '',
                    camera: '',
                    quality: '',
                    music: '',
                    music_link: '',
                    file_link: '',
                    notes: '',
                    total_price: props.basePrice,
                    with_agent: false,
                    per_property: false,
                    extra_fields: { effects: [], captions: [] },
                });
                agentOption.value = '';
                perPropertyOption.value = '';
            }
        }
    },
    { immediate: true },
);

// Handle checkbox changes
function handleEffectChange(effectId: string, value: boolean | 'indeterminate') {
    const checked = value === true;
    const current = [...form.extra_fields.effects];
    if (checked && !current.includes(effectId)) {
        current.push(effectId);
    } else {
        current.splice(current.indexOf(effectId), 1);
    }
    form.extra_fields.effects = current;
    form.extra_fields = { ...form.extra_fields };
}

function handleCaptionChange(captionId: string, value: boolean | 'indeterminate') {
    const checked = value === true;
    const current = [...form.extra_fields.captions];
    if (checked && !current.includes(captionId)) {
        current.push(captionId);
    } else {
        current.splice(current.indexOf(captionId), 1);
    }
    form.extra_fields.captions = current;
    form.extra_fields = { ...form.extra_fields };
}

// Submit handler
const handleSubmit = () => {
    const isEditing = !!props.project;
    const submitData = {
        ...form,
        per_property: form.per_property ? 1 : 0,
        extra_fields: { effects: [...form.extra_fields.effects], captions: [...form.extra_fields.captions] },
    };

    if (isEditing) {
        form.transform(() => submitData).put(route('projects.client_update', props.project!.id), {
            onSuccess: () => {
                toast.success('Updated successfully!', { description: 'Your order was updated successfully!', position: 'top-right' });
                emit('close');
            },
            onError: () => console.error(form.errors),
        });
    } else {
        form.transform(() => submitData).post(route('projects.store'), {
            onSuccess: () => {
                toast.success('Order placed', { description: 'Your order has been placed.', position: 'top-right' });
                emit('close');
                console.log(form);
            },
            onError: () => console.error(form.errors),
        });
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="(v) => !v && emit('close')">
        <DialogContent class="max-h-[90vh] !w-full !max-w-6xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle>
                    {{ props.project ? `Edit Project - ${form.project_name}` : 'Order: Luxury Style' }}
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
                                <SelectItem value="Luxury video">Luxury Video</SelectItem>
                                <SelectItem value="Luxury drone only">Luxury Drone Only</SelectItem>
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

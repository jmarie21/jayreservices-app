<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { LuxuryForm } from '@/types/app-page-prop';
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
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

interface Option {
    id: string;
    label: string;
}

// Available options
const effectsOptions: Option[] = [
    { id: 'ken_burns', label: 'Ken Burns' },
    { id: 'house_drop', label: 'House Drop' },
    { id: 'pillar_masking', label: 'Pillar Masking  (This is only applicable if you have the footage)' },
    { id: 'no_effect', label: 'I DONT WANT ANY TRANSITIONS FOR THIS PROJECT' },
];

const captionsOptions: Option[] = [
    { id: '3d_text', label: '3D Text behind the Agent Talking (ADD $10)' },
    { id: '3d_text_ground', label: '3D Text that was tracked on the ground or etc, (ADD 15)' },
    { id: 'captions', label: 'Captions while the agent is talking (ADD $10)' },
    { id: 'no_captions', label: 'NO NEED TO ADD TEXT OR CAPTIONS' },
];

// Initialize form with proper default values
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
});

function calculateTotalPrice() {
    const agentCost = agentOption.value === 'with-agent' ? 10 : 0;

    let extraCaptionCost = 0;
    if (form.extra_fields.captions.includes('3d_text')) extraCaptionCost += 10;
    if (form.extra_fields.captions.includes('captions')) extraCaptionCost += 10;
    if (form.extra_fields.captions.includes('3d_text_ground')) extraCaptionCost += 15;

    form.total_price = Number(props.basePrice) + agentCost + extraCaptionCost;
}

watch(agentOption, () => {
    form.with_agent = agentOption.value === 'with-agent';
    calculateTotalPrice();
});

watch(() => form.extra_fields.captions, calculateTotalPrice, { deep: true });

// Watch for modal opening and project changes
watch(
    [() => props.project, () => props.open],
    ([project, isOpen]) => {
        if (isOpen) {
            if (project) {
                // Editing existing project
                form.style = project.style || '';
                form.company_name = project.company_name || '';
                form.contact = project.contact || '';
                form.project_name = project.project_name || '';
                form.format = project.format || '';
                form.camera = project.camera || '';
                form.quality = project.quality || '';
                form.music = project.music || '';
                form.music_link = project.music_link || '';
                form.file_link = project.file_link || '';
                form.notes = project.notes || '';
                agentOption.value = project.with_agent ? 'with-agent' : 'no-agent';
                form.with_agent = project.with_agent ?? false;

                // Set extra_fields properly for editing - ensure clean arrays
                form.extra_fields = {
                    effects: project.extra_fields?.effects ? [...project.extra_fields.effects] : [],
                    captions: project.extra_fields?.captions ? [...project.extra_fields.captions] : [],
                };

                const extraCost = project.with_agent ? 10 : 0;
                form.total_price = Number(props.basePrice) + extraCost;

                console.log('Loading project for editing:', {
                    project,
                    effects: form.extra_fields.effects,
                    captions: form.extra_fields.captions,
                });
            } else {
                // Creating new project - reset form
                form.style = '';
                form.company_name = '';
                form.contact = '';
                form.project_name = '';
                form.format = '';
                form.camera = '';
                form.quality = '';
                form.music = '';
                form.music_link = '';
                form.file_link = '';
                form.notes = '';
                form.total_price = props.basePrice;
                form.with_agent = false;
                agentOption.value = '';

                // Reset extra_fields arrays
                form.extra_fields = {
                    effects: [],
                    captions: [],
                };
            }
        }
    },
    { immediate: true },
);

// Handle checkbox changes using model-value approach
function handleEffectChange(effectId: string, value: boolean | 'indeterminate') {
    // Convert indeterminate to false, since we're dealing with simple checked/unchecked states
    const checked = value === true;

    const currentArray = [...form.extra_fields.effects];

    if (checked) {
        if (!currentArray.includes(effectId)) {
            currentArray.push(effectId);
        }
    } else {
        const index = currentArray.indexOf(effectId);
        if (index > -1) {
            currentArray.splice(index, 1);
        }
    }

    // Update form data
    form.extra_fields.effects = currentArray;
    form.extra_fields = { ...form.extra_fields }; // Force reactivity

    console.log(`Effect ${effectId} changed to ${checked}:`, currentArray);
}

function handleCaptionChange(captionId: string, value: boolean | 'indeterminate') {
    // Convert indeterminate to false, since we're dealing with simple checked/unchecked states
    const checked = value === true;

    const currentArray = [...form.extra_fields.captions];

    if (checked) {
        if (!currentArray.includes(captionId)) {
            currentArray.push(captionId);
        }
    } else {
        const index = currentArray.indexOf(captionId);
        if (index > -1) {
            currentArray.splice(index, 1);
        }
    }

    // Update form data
    form.extra_fields.captions = currentArray;
    form.extra_fields = { ...form.extra_fields }; // Force reactivity

    console.log(`Caption ${captionId} changed to ${checked}:`, currentArray);
}

const handleSubmit = () => {
    const isEditing = !!props.project;

    // Create a clean data object to ensure proper serialization
    const submitData = {
        style: form.style,
        company_name: form.company_name,
        contact: form.contact,
        project_name: form.project_name,
        format: form.format,
        camera: form.camera,
        quality: form.quality,
        music: form.music,
        music_link: form.music_link,
        file_link: form.file_link,
        notes: form.notes,
        total_price: form.total_price,
        with_agent: form.with_agent,
        service_id: form.service_id,
        extra_fields: {
            effects: [...form.extra_fields.effects], // Create clean arrays
            captions: [...form.extra_fields.captions],
        },
    };

    // Debug log to check what's being sent
    console.log('Clean form data being submitted:', submitData);
    console.log('Original form extra_fields:', form.extra_fields);

    if (isEditing) {
        // Use transform to send clean data
        form.transform(() => submitData).put(route('projects.update', props.project!.id), {
            onSuccess: () => {
                toast('Updated successfully!', {
                    description: 'Your order was updated successfully!',
                    position: 'top-right',
                });
                emit('close');
            },
            onError: (error) => {
                console.error('error saving to db:', error);
                emit('close');
            },
        });
    } else {
        // Use transform to send clean data
        form.transform(() => submitData).post(route('projects.store'), {
            onSuccess: () => {
                toast('Order placed', {
                    description: 'Your order has been placed.',
                    position: 'top-right',
                });
                console.log('Form extra_fields on success:', form.extra_fields);
                emit('close');
            },
            onError: (error) => {
                console.error('error saving to db:', error);
                emit('close');
            },
        });
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="(v) => !v && emit('close')">
        <DialogContent class="!w-full !max-w-6xl">
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
                                <SelectItem value="horizontal">Horizontal</SelectItem>
                                <SelectItem value="vertical">Vertical</SelectItem>
                                <SelectItem value="horizontal and vertical package"> Horizontal and Vertical Package </SelectItem>
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

                    <!-- Total & Submit -->
                    <div class="mt-8 text-xl font-semibold">Total: ${{ Number(form.total_price).toFixed(2) }}</div>
                    <div class="mt-8 flex justify-end">
                        <Button type="submit" :disabled="form.processing">
                            {{ props.project ? 'Save Changes' : 'Place Order' }}
                        </Button>
                    </div>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>

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

export interface CustomEffect {
    id: string;
    description: string;
    price: number;
}

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
const perPropertyQuantity = ref(1);
const rushOption = ref<'true' | 'false' | ''>('');
const isEditing = !!props.project;

// Custom effects state
const customEffects = ref<CustomEffect[]>([]);
const newEffectDescription = ref('');
const newEffectPrice = ref<number>(0);

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
    total_price: 0,
    with_agent: props.project?.with_agent ?? false,
    service_id: props.serviceId,
    rush: props.project?.rush ?? false,
    extra_fields: {
        effects: props.project?.extra_fields?.effects ? formatEffectsFromBackend(props.project.extra_fields.effects) : [],
        captions: props.project?.extra_fields?.captions ? [...props.project.extra_fields.captions] : [],
        custom_effects: '[]', // Store as JSON string
    },
    per_property: props.project?.per_property ?? false,
    per_property_count: props.project?.per_property_count ?? 0,
    ...(isAdmin.value ? { client_id: props.project?.client_id ?? null } : {}),
});

// Helper function to format effects from backend (handles both old and new format)
function formatEffectsFromBackend(effects: any): Array<{ id: string; quantity: number }> {
    if (!Array.isArray(effects)) return [];

    return effects.map((effect) => {
        if (typeof effect === 'string') {
            return { id: effect, quantity: 1 };
        }
        return effect;
    });
}

// Check if effect is selected
function isEffectSelected(id: string): boolean {
    return form.extra_fields?.effects.some((e) => e.id === id) ?? false;
}

// Get effect quantity
function getEffectQuantity(id: string): number {
    const effect = form.extra_fields?.effects.find((e) => e.id === id);
    return effect?.quantity ?? 1;
}

// Increment effect quantity
function incrementEffect(id: string) {
    if (!form.extra_fields) return;

    const arr = [...form.extra_fields.effects];
    const effect = arr.find((e) => e.id === id);

    if (effect) {
        effect.quantity = (effect.quantity || 1) + 1;
        form.extra_fields.effects = arr;
        form.extra_fields = { ...form.extra_fields };
    }
}

// Decrement effect quantity
function decrementEffect(id: string) {
    if (!form.extra_fields) return;

    const arr = [...form.extra_fields.effects];
    const effect = arr.find((e) => e.id === id);

    if (effect && effect.quantity > 1) {
        effect.quantity -= 1;
        form.extra_fields.effects = arr;
        form.extra_fields = { ...form.extra_fields };
    }
}

// Update the totalPrice computed to account for quantities
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
    if (perPropertyOption.value === 'add-per-property') total += 5 * perPropertyQuantity.value;
    if (rushOption.value === 'true') total += 20;

    // Captions
    if (form.extra_fields?.captions.includes('3D Text behind the Agent Talking')) total += 10;
    if (form.extra_fields?.captions.includes('Captions while the agent is talking')) total += 10;

    // Effects with quantities
    form.extra_fields?.effects.forEach((effect) => {
        const quantity = effect.quantity || 1;
        if (effect.id === 'Painting Transition') total += 10 * quantity;
        if (effect.id === 'Earth Zoom Transition') total += 15 * quantity;
    });

    // Custom effects extra
    const customEffectsTotal = customEffects.value.reduce((sum, effect) => sum + effect.price, 0);
    total += customEffectsTotal;

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
watch(perPropertyOption, (val) => {
    form.per_property = val === 'add-per-property';
    if (val === 'add-per-property' && perPropertyQuantity.value < 1) {
        perPropertyQuantity.value = 1;
    }
    if (val !== 'add-per-property') {
        perPropertyQuantity.value = 0;
    }
});
watch(perPropertyQuantity, (val) => {
    form.per_property_count = val;
});

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
    form.extra_fields ??= { effects: [], captions: [], custom_effects: '[]' };
    const isChecked = checked === true;
    const arr = [...form.extra_fields.effects];

    if (isChecked) {
        if (!arr.some((e) => e.id === id)) {
            arr.push({ id, quantity: 1 });
        }
    } else {
        const index = arr.findIndex((e) => e.id === id);
        if (index !== -1) arr.splice(index, 1);
    }

    form.extra_fields.effects = arr;
    form.extra_fields = { ...form.extra_fields };
}

function handleCaptionChange(id: string, checked: boolean | 'indeterminate') {
    form.extra_fields ??= { effects: [], captions: [], custom_effects: '[]' };
    const isChecked = checked === true;
    const arr = [...form.extra_fields.captions];
    if (isChecked && !arr.includes(id)) arr.push(id);
    if (!isChecked && arr.includes(id)) arr.splice(arr.indexOf(id), 1);
    form.extra_fields.captions = arr;
    form.extra_fields = { ...form.extra_fields };
}

// Custom effects functions
function addCustomEffect() {
    if (!newEffectDescription.value.trim()) {
        toast.error('Please enter an effect description');
        return;
    }
    if (newEffectPrice.value < 0) {
        toast.error('Price cannot be negative');
        return;
    }

    const newEffect: CustomEffect = {
        id: `custom-${Date.now()}`,
        description: newEffectDescription.value.trim(),
        price: newEffectPrice.value,
    };

    customEffects.value.push(newEffect);

    // Reset inputs
    newEffectDescription.value = '';
    newEffectPrice.value = 0;

    toast.success('Custom effect added!');
}

function removeCustomEffect(id: string) {
    customEffects.value = customEffects.value.filter((effect) => effect.id !== id);
    toast.success('Custom effect removed');
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
            perPropertyQuantity.value = project.per_property_count ?? 1;
            rushOption.value = project.rush ? 'true' : 'false';
            form.extra_fields = {
                effects: project.extra_fields?.effects ? formatEffectsFromBackend(project.extra_fields.effects) : [],
                captions: project.extra_fields?.captions ? [...project.extra_fields.captions] : [],
                custom_effects: '[]',
            };

            // Load custom effects - parse if string, use directly if array
            if (project.extra_fields?.custom_effects) {
                try {
                    customEffects.value =
                        typeof project.extra_fields.custom_effects === 'string'
                            ? JSON.parse(project.extra_fields.custom_effects)
                            : [...project.extra_fields.custom_effects];
                } catch (e) {
                    console.error('Failed to parse custom_effects:', e);
                    customEffects.value = [];
                }
            } else {
                customEffects.value = [];
            }
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
            perPropertyQuantity.value = 0;
            rushOption.value = '';
            form.extra_fields = { effects: [], captions: [], custom_effects: '[]' };
            customEffects.value = [];
        }
    },
    { immediate: true },
);

// Submit handler
const handleSubmit = () => {
    const isEditing = !!props.project;
    const isAdminUser = isAdmin.value;
    const createRoute = isAdminUser ? 'admin.project.create' : 'projects.store';
    const updateRoute = isAdminUser ? 'admin.project.update' : 'projects.client_update';

    // Serialize custom_effects to JSON string for FormData compatibility
    const submissionData = {
        ...form.data(),
        extra_fields: {
            ...form.extra_fields,
            custom_effects: JSON.stringify(customEffects.value),
        },
    };

    if (isEditing) {
        form.transform(() => submissionData).put(route(updateRoute, props.project!.id), {
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
        form.transform(() => submissionData).post(route(createRoute), {
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

// Sort clients alphabetically
const sortedClients = computed(() => [...clients].sort((a, b) => a.name.localeCompare(b.name)));

function incrementPerProperty() {
    perPropertyQuantity.value++;
}

function decrementPerProperty() {
    if (perPropertyQuantity.value > 1) {
        perPropertyQuantity.value--;
    }
}
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
                                <SelectItem v-for="client in sortedClients" :key="client.id" :value="client.id">
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
                        <div class="flex items-center gap-2">
                            <div :class="['flex-1 transition-all duration-200', perPropertyOption === 'add-per-property' ? 'w-[80%]' : 'w-full']">
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
                            <div v-if="perPropertyOption === 'add-per-property'" class="flex items-center gap-1">
                                <Button type="button" size="icon" variant="outline" class="h-8 w-8" @click="decrementPerProperty">
                                    <span class="text-lg leading-none">−</span>
                                </Button>
                                <span class="w-5 text-center text-sm">{{ perPropertyQuantity }}</span>
                                <Button type="button" size="icon" variant="outline" class="h-8 w-8" @click="incrementPerProperty">
                                    <span class="text-lg leading-none">+</span>
                                </Button>
                            </div>
                        </div>
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
                        <span v-if="form.errors.rush" class="text-sm text-red-500">{{ form.errors.rush }}</span>
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
                                    :model-value="isEffectSelected(effect.id)"
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
                                <!-- Quantity Controls -->
                                <div v-if="isEffectSelected(effect.id)" class="ml-1 flex items-center gap-1">
                                    <button
                                        type="button"
                                        @click="decrementEffect(effect.id)"
                                        class="flex h-6 w-6 items-center justify-center rounded border border-gray-300 hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-700"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-4 w-4"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <line x1="5" y1="12" x2="19" y2="12"></line>
                                        </svg>
                                    </button>
                                    <span class="min-w-[2rem] text-center font-medium">{{ getEffectQuantity(effect.id) }}</span>
                                    <button
                                        type="button"
                                        @click="incrementEffect(effect.id)"
                                        class="flex h-6 w-6 items-center justify-center rounded border border-gray-300 hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-700"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-4 w-4"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <line x1="12" y1="5" x2="12" y2="19"></line>
                                            <line x1="5" y1="12" x2="19" y2="12"></line>
                                        </svg>
                                    </button>
                                </div>
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

                <!-- Custom Effects Section -->
                <div v-if="isEditing && isAdmin" class="mt-6 space-y-4 rounded-lg border border-gray-200 p-4">
                    <div class="space-y-2">
                        <Label class="text-base font-semibold">Additional Effects</Label>
                        <p class="text-sm text-gray-600">Add any additional custom effects with their associated costs</p>
                    </div>

                    <!-- Add Custom Effect Form -->
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
                        <div class="md:col-span-7">
                            <Input v-model="newEffectDescription" placeholder="Describe the custom effect..." />
                        </div>
                        <div class="md:col-span-3">
                            <Input v-model.number="newEffectPrice" type="number" step="0.01" min="0" placeholder="Price ($)" />
                        </div>
                        <div class="md:col-span-2">
                            <Button type="button" variant="outline" class="w-full" @click="addCustomEffect"> Add Effect </Button>
                        </div>
                    </div>

                    <!-- List of Custom Effects -->
                    <div v-if="customEffects.length > 0" class="space-y-2">
                        <div class="text-sm font-medium">Added Custom Effects:</div>
                        <div class="space-y-2">
                            <div
                                v-for="effect in customEffects"
                                :key="effect.id"
                                class="flex items-center justify-between rounded-md border border-gray-200 bg-gray-50 p-3"
                            >
                                <div class="flex-1">
                                    <span class="text-sm">{{ effect.description }}</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-semibold">${{ effect.price.toFixed(2) }}</span>
                                    <Button type="button" variant="destructive" size="sm" @click="removeCustomEffect(effect.id)"> Remove </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total & Submit -->
                <div class="mt-8 text-xl font-semibold">Total: ${{ Number(form.total_price).toFixed(2) }}</div>
                <div class="mt-8 flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        <span v-if="form.processing" class="mr-2 animate-spin">⳨</span>
                        {{ props.project ? 'Save Changes' : 'Place Order' }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>

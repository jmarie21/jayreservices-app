<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { AppPageProps, User } from '@/types';
import { LuxuryForm } from '@/types/app-page-prop';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Checkbox } from '../ui/checkbox';
import { Textarea } from '../ui/textarea';

export interface CustomEffect {
    id: string;
    description: string;
    price: number;
}

const props = defineProps<{
    open: boolean;
    basePrice: number;
    serviceId: number;
    project?: LuxuryForm | null;
}>();

const { props: page } = usePage<AppPageProps>();
const userRole = page.auth.user.role;
const isAdmin = computed(() => userRole === 'admin');
const { clients } = usePage<AppPageProps<{ clients: User[] }>>().props;

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const agentOption = ref<'with-agent' | 'no-agent' | ''>('');
const perPropertyOption = ref<'add-per-property' | 'no' | ''>(props.project ? (props.project.per_property ? 'add-per-property' : 'no') : 'no');
const perPropertyQuantity = ref(props.project?.per_property_count ?? 1);
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

const effectsOptions: Option[] = [
    { id: 'Ken Burns', label: 'Ken Burns', link: 'https://www.youtube.com/watch?v=lIK2S0eIvwY&list=TLGG7aKmePKcyR8xMzEwMjAyNQ' },
    { id: 'House Drop', label: 'House Drop', link: 'https://youtu.be/3vVfB8AZkMw ' },
    {
        id: 'Pillar Masking',
        label: 'Pillar Masking',
        link: 'https://www.youtube.com/watch?v=byh1nKAE3Pk&list=TLGG_YXdMMvhwfsxMzEwMjAyNQ&t=2s',
    },
    { id: 'Virtual Staging AI', label: 'Virtual Staging AI ($20 per clip)', link: 'https://youtu.be/79vg5WqKgYE?si=TkXflrhPmUfTAQFX' },
    { id: 'Day to Night AI', label: 'Day to Night AI ($15 per clip)', link: 'https://youtu.be/OPpyyb77ijs?si=q-IjufGmarVw8kMu' },
    { id: 'Painting Transition', label: 'Painting Transition (Add $10)', link: 'https://youtu.be/vCW4H7puU1c?si=GoI72aCscroTvYqk)' },
    { id: 'Earth Zoom Transition', label: 'Earth Zoom Transition (Add $15)', link: 'https://www.youtube.com/watch?v=dyuRMbjDJas&feature=youtu.be' },
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
    rush: props.project?.rush ?? false,
    extra_fields: {
        effects: props.project?.extra_fields?.effects ? formatEffectsFromBackend(props.project.extra_fields.effects) : [],
        captions: props.project?.extra_fields?.captions ? [...props.project.extra_fields.captions] : [],
        custom_effects: '[]', // Store as JSON string
    },
    per_property: props.project?.per_property,
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

function calculateTotalPrice() {
    let total = Number(props.basePrice);

    // Style & Format cost
    if (form.style === 'Luxury video') {
        if (form.format === 'horizontal') total += 120;
        else if (form.format === 'vertical') total += 120;
        else if (form.format === 'horizontal and vertical package') total += 240;
    } else if (form.style === 'Luxury drone only') {
        if (form.format === 'horizontal') total += 60;
        else if (form.format === 'vertical') total += 50;
        else if (form.format === 'horizontal and vertical package') total += 60 + 50;
    }

    // Agent cost
    if (agentOption.value === 'with-agent') total += 10;

    // Per property line
    if (perPropertyOption.value === 'add-per-property') total += 5 * perPropertyQuantity.value;

    // Rush extra
    if (rushOption.value === 'true') total += 20;

    // Captions cost
    if (form.extra_fields?.captions.includes('3D Text behind the Agent Talking')) total += 10;
    if (form.extra_fields?.captions.includes('3D Text tracked on the ground etc.')) total += 15;
    if (form.extra_fields?.captions.includes('Captions while the agent is talking')) total += 10;

    // Effects with quantities
    form.extra_fields?.effects.forEach((effect) => {
        const quantity = effect.quantity || 1;
        if (effect.id === 'Painting Transition') total += 10 * quantity;
        if (effect.id === 'Earth Zoom Transition') total += 15 * quantity;
        if (effect.id === 'Day to Night AI') total += 15 * quantity;
        if (effect.id === 'Virtual Staging AI') total += 20 * quantity;
    });

    // Custom effects extra
    const customEffectsTotal = customEffects.value.reduce((sum, effect) => sum + effect.price, 0);
    total += customEffectsTotal;

    form.total_price = total;
}

const formatOptions = computed(() => {
    if (form.style === 'Luxury video') {
        return [
            { value: 'horizontal', label: 'Horizontal ($120)' },
            { value: 'vertical', label: 'Vertical ($120)' },
            { value: 'horizontal and vertical package', label: 'Horizontal & Vertical ($240)' },
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

// Computed to get the label of the selected format
const selectedFormatLabel = computed(() => {
    const option = formatOptions.value.find((o) => o.value === form.format);
    return option ? option.label : '';
});

watch(agentOption, () => {
    form.with_agent = agentOption.value === 'with-agent';
    calculateTotalPrice();
});
watch(perPropertyOption, () => {
    form.per_property = perPropertyOption.value === 'add-per-property';
    calculateTotalPrice();
});
watch(perPropertyOption, (val) => {
    form.per_property = val === 'add-per-property';
    if (val === 'add-per-property' && perPropertyQuantity.value < 1) {
        perPropertyQuantity.value = 1;
    }
    if (val !== 'add-per-property') {
        perPropertyQuantity.value = 0;
    }

    calculateTotalPrice();
});
watch(perPropertyQuantity, () => {
    calculateTotalPrice();
});

watch(perPropertyQuantity, (val) => {
    form.per_property_count = val;
});

watch(rushOption, () => {
    form.rush = rushOption.value === 'true';
    calculateTotalPrice();
});

watch(() => form.extra_fields?.captions, calculateTotalPrice, { deep: true });
watch(() => form.style, calculateTotalPrice);
watch(() => form.format, calculateTotalPrice);

// Watch custom effects for price recalculation
watch(customEffects, calculateTotalPrice, { deep: true });

// Reset / load project data on modal open
watch(
    [() => props.project, () => props.open],
    ([project, isOpen]) => {
        if (isOpen) {
            if (project) {
                Object.assign(form, {
                    style: project.style || '',
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
                    per_property_count: project.per_property_count ?? 0,
                    extra_fields: {
                        effects: project.extra_fields?.effects ? formatEffectsFromBackend(project.extra_fields.effects) : [],
                        captions: project.extra_fields?.captions ? [...project.extra_fields.captions] : [],
                        custom_effects: '[]',
                    },
                });
                agentOption.value = project.with_agent ? 'with-agent' : 'no-agent';
                perPropertyOption.value = project.per_property ? 'add-per-property' : 'no';
                rushOption.value = project.rush ? 'true' : 'false';

                // Ensure the displayed per-property quantity matches the project value
                perPropertyQuantity.value = project.per_property_count ?? 1;

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
                    per_property_count: 0,
                    rushOption: false,
                    extra_fields: { effects: [], captions: [], custom_effects: '[]' },
                });
                agentOption.value = '';
                perPropertyOption.value = '';
                rushOption.value = '';
                customEffects.value = [];
            }
        }
    },
    { immediate: true },
);

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

function handleCaptionChange(captionId: string, value: boolean | 'indeterminate') {
    form.extra_fields ??= { effects: [], captions: [], custom_effects: '[]' };
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
                    {{ props.project ? `Edit Project - ${form.project_name}` : 'Order: Construction Luxury Style' }}
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
                                <SelectItem value="Luxury video">Luxury Video</SelectItem>
                                <!-- <SelectItem value="Luxury drone only">Luxury Drone Only</SelectItem> -->
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

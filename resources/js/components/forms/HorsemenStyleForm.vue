<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { AppPageProps, User } from '@/types';
import { TalkingHeadsForm } from '@/types/app-page-prop';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Checkbox } from '../ui/checkbox';
import { Textarea } from '../ui/textarea';

interface Option {
    id: string;
    label: string;
    link?: string | null;
}

const effectsOptions: Option[] = [
    { id: 'Virtual Staging AI', label: 'Virtual Staging AI ($20 per clip)', link: 'https://youtu.be/79vg5WqKgYE?si=TkXflrhPmUfTAQFX' },
    { id: 'Day to Night AI', label: 'Day to Night AI ($15 per clip)', link: 'https://youtu.be/OPpyyb77ijs?si=q-IjufGmarVw8kMu' },
];

const captionsOptions: Option[] = [
    { id: '3D Text behind the Agent Talking', label: '3D Text behind the Agent Talking (ADD $10)' },
    { id: '3D Text tracked on the ground etc.', label: '3D Text tracked on the ground etc. (ADD $15)' },
    { id: '3D Graphics together with text', label: '3D Graphics together with text (ADD $20)' },
    { id: 'Captions while the agent is talking', label: 'Captions while the agent is talking (ADD $10)' },
    { id: 'No Captions', label: 'NO NEED TO ADD TEXT OR CAPTIONS' },
];

const props = defineProps<{
    open: boolean;
    basePrice: number;
    serviceId: number;
    project?: TalkingHeadsForm | null;
}>();
const { props: page } = usePage<AppPageProps>();
const userRole = page.auth.user.role;
const isAdmin = computed(() => userRole === 'admin');
const { clients } = usePage<AppPageProps<{ clients: User[] }>>().props;

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const rushOption = ref<'true' | 'false' | ''>('');

// Helper function to format effects from backend
function formatEffectsFromBackend(effects: any): Array<{ id: string; quantity: number }> {
    if (!Array.isArray(effects)) return [];
    return effects.map((effect: any) => {
        if (typeof effect === 'string') return { id: effect, quantity: 1 };
        return effect;
    });
}

// Form initialization
const form = useForm<TalkingHeadsForm>({
    style: props.project?.style ?? 'horsemen style',
    project_name: props.project?.project_name ?? '',
    format: props.project?.format ?? '',
    camera: props.project?.camera ?? '',
    quality: props.project?.quality ?? '',
    music: props.project?.music ?? '',
    music_link: props.project?.music_link ?? '',
    file_link: props.project?.file_link ?? '',
    notes: props.project?.notes ?? '',
    total_price: Number(props.project?.total_price ?? props.basePrice),
    service_id: props.serviceId,
    rush: props.project?.rush ?? false,
    extra_fields: {
        effects: props.project?.extra_fields?.effects ? formatEffectsFromBackend(props.project.extra_fields.effects) : [],
        captions: props.project?.extra_fields?.captions ? [...props.project.extra_fields.captions] : [],
    },
    ...(isAdmin.value ? { client_id: props.project?.client_id ?? null } : {}),
});

// Effect helpers
function isEffectSelected(id: string): boolean {
    return form.extra_fields?.effects.some((e) => e.id === id) ?? false;
}

function getEffectQuantity(id: string): number {
    const effect = form.extra_fields?.effects.find((e) => e.id === id);
    return effect?.quantity ?? 1;
}

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

function handleEffectChange(id: string, checked: boolean | 'indeterminate') {
    form.extra_fields ??= { effects: [], captions: [] };
    const isChecked = checked === true;
    const arr = [...form.extra_fields.effects];
    if (isChecked) {
        if (!arr.some((e) => e.id === id)) arr.push({ id, quantity: 1 });
    } else {
        const index = arr.findIndex((e) => e.id === id);
        if (index !== -1) arr.splice(index, 1);
    }
    form.extra_fields.effects = arr;
    form.extra_fields = { ...form.extra_fields };
}

function handleCaptionChange(captionId: string, value: boolean | 'indeterminate') {
    form.extra_fields ??= { effects: [], captions: [] };
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

// Computed total price based on selected options
const totalPrice = computed(() => {
    let extra = 0;

    // Price based on format
    if (form.format === 'horizontal') extra += 40;
    else if (form.format === 'vertical') extra += 30;

    // Rush extra price
    if (rushOption.value === 'true') extra += 5;

    // Captions cost
    if (form.extra_fields?.captions.includes('3D Text behind the Agent Talking')) extra += 10;
    if (form.extra_fields?.captions.includes('3D Text tracked on the ground etc.')) extra += 15;
    if (form.extra_fields?.captions.includes('3D Graphics together with text')) extra += 20;
    if (form.extra_fields?.captions.includes('Captions while the agent is talking')) extra += 10;

    // Effects with quantities
    form.extra_fields?.effects.forEach((effect) => {
        const quantity = effect.quantity || 1;
        if (effect.id === 'Virtual Staging AI') extra += 20 * quantity;
        if (effect.id === 'Day to Night AI') extra += 15 * quantity;
    });

    return extra;
});

const formatOptions = computed(() => {
    return [
        { value: 'horizontal', label: 'Horizontal ($40)' },
        { value: 'vertical', label: 'Vertical ($30)' },
    ];
});

// Computed to get the label of the selected format
const selectedFormatLabel = computed(() => {
    const option = formatOptions.value.find((o) => o.value === form.format);
    return option ? option.label : '';
});

// Watch totalPrice and update form.total_price
watch(
    totalPrice,
    (val) => {
        form.total_price = val;
    },
    { immediate: true },
);

// Watch project prop and initialize/reset form
watch(
    () => props.project,
    (project) => {
        if (project) {
            form.style = project.style ?? 'horsemen style';
            form.format = project.format ?? '';
            form.project_name = project.project_name ?? '';
            form.camera = project.camera ?? '';
            form.quality = project.quality ?? '';
            form.music = project.music ?? '';
            form.music_link = project.music_link ?? '';
            form.file_link = project.file_link ?? '';
            form.notes = project.notes ?? '';
            rushOption.value = project.rush ? 'true' : 'false';
            form.extra_fields = {
                effects: project.extra_fields?.effects ? formatEffectsFromBackend(project.extra_fields.effects) : [],
                captions: project.extra_fields?.captions ? [...project.extra_fields.captions] : [],
            };
        } else {
            // Reset form
            form.style = 'horsemen style';
            form.format = '';
            form.project_name = '';
            form.camera = '';
            form.quality = '';
            form.music = '';
            form.music_link = '';
            form.file_link = '';
            form.notes = '';
            rushOption.value = '';
            form.extra_fields = { effects: [], captions: [] };
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

// Sort clients alphabetically
const sortedClients = computed(() => [...clients].sort((a, b) => a.name.localeCompare(b.name)));
</script>

<template>
    <Dialog :open="open" @update:open="(v) => !v && emit('close')">
        <DialogContent class="max-h-[90vh] !w-full !max-w-6xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle>
                    {{ props.project ? `Edit Project - ${form.project_name}` : 'Order: Horsemen Style' }}
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

                    <!-- Project Name -->
                    <div class="space-y-2">
                        <Label>Project Name</Label>
                        <Input v-model="form.project_name" placeholder="Enter your project name" />
                        <span v-if="form.errors.project_name" class="text-sm text-red-500">{{ form.errors.project_name }}</span>
                    </div>

                    <!-- Format -->
                    <div class="space-y-2">
                        <Label>Video Format <span class="text-red-500">*</span></Label>
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
                                <SelectItem value="royalty free music with vocals">Royalty free music with vocals</SelectItem>
                                <SelectItem value="royalty free music without vocals">Royalty free music without vocals</SelectItem>
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
                                <SelectItem value="true">Yes ($5)</SelectItem>
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
                                        <span class="text-lg leading-none">−</span>
                                    </button>
                                    <span class="w-5 text-center text-sm">{{ getEffectQuantity(effect.id) }}</span>
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
                <!-- Total & Submit -->
                <div class="mt-8 text-xl font-semibold">Total: ${{ form.total_price.toFixed(2) }}</div>

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

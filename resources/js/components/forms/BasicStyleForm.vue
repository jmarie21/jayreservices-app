<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { AppPageProps, User } from '@/types';
import { BasicForm } from '@/types/app-page-prop';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    open: boolean;
    basePrice: number;
    serviceId: number;
    project?: BasicForm | null;
}>();
const { props: page } = usePage<AppPageProps>();
const userRole = page.auth.user.role;
const isAdmin = computed(() => userRole === 'admin');
const { clients } = usePage<AppPageProps<{ clients: User[] }>>().props;

const emit = defineEmits<{
    (e: 'close'): void;
}>();

// Agent selection
const agentOption = ref<'with-agent' | 'no-agent' | ''>('');
const perPropertyOption = ref<'add-per-property' | 'no' | ''>(props.project?.per_property ? 'add-per-property' : '');

// Form initialization
const form = useForm<BasicForm>({
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
    rush: props.project?.rush ?? false,
    per_property: props.project?.per_property,
    ...(isAdmin.value ? { client_id: props.project?.client_id ?? null } : {}),
});

// const calculateExtraPrice = () => {
//     let extra = 0;

//     // Price based on style and format
//     if (form.style === 'basic video') {
//         if (form.format === 'horizontal') extra += 40;
//         else if (form.format === 'vertical') extra += 25;
//         else if (form.format === 'horizontal and vertical package') extra += 40 + 25;
//     } else if (form.style === 'basic drone only') {
//         if (form.format === 'horizontal') extra += 25;
//         else if (form.format === 'vertical') extra += 20;
//         else if (form.format === 'horizontal and vertical package') extra += 25 + 20;
//     }

//     // Price based on agent
//     const agentExtra = agentOption.value === 'with-agent' ? 10 : 0;

//     // Price based on per property
//     const perPropertyExtra = perPropertyOption.value === 'add-per-property' ? 5 : 0;

//     return Number(props.basePrice) + extra + agentExtra + perPropertyExtra;
// };

// Computed total price based on selected options
const totalPrice = computed(() => {
    let extra = 0;

    // Price based on style and format
    if (form.style === 'basic video') {
        if (form.format === 'horizontal') extra += 40;
        else if (form.format === 'vertical') extra += 25;
        else if (form.format === 'horizontal and vertical package') extra += 65; // 40+25
    } else if (form.style === 'basic drone only') {
        if (form.format === 'horizontal') extra += 25;
        else if (form.format === 'vertical') extra += 20;
        else if (form.format === 'horizontal and vertical package') extra += 45; // 25+20
    }

    // Price based on agent
    if (agentOption.value === 'with-agent') extra += 10;

    // Price based on per property
    if (perPropertyOption.value === 'add-per-property') extra += 5;

    return extra; // basePrice is 0, so we just return extras
});

const formatOptions = computed(() => {
    if (form.style === 'basic video') {
        return [
            { value: 'horizontal', label: 'Horizontal ($40)' },
            { value: 'vertical', label: 'Vertical ($25)' },
            { value: 'horizontal and vertical package', label: 'Horizontal and Vertical Package ($65)' },
        ];
    } else if (form.style === 'basic drone only') {
        return [
            { value: 'horizontal', label: 'Horizontal ($25)' },
            { value: 'vertical', label: 'Vertical ($20)' },
            { value: 'horizontal and vertical package', label: 'Horizontal and Vertical Package ($45)' },
        ];
    } else {
        // Default if no style selected
        return [
            { value: 'horizontal', label: 'Horizontal' },
            { value: 'vertical', label: 'Vertical' },
            { value: 'horizontal and vertical package', label: 'Horizontal and Vertical Package' },
        ];
    }
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

// Update form options when selections change
watch(agentOption, () => {
    form.with_agent = agentOption.value === 'with-agent';
});
watch(perPropertyOption, () => {
    form.per_property = perPropertyOption.value === 'add-per-property';
});

// Watch project prop and initialize/reset form
watch(
    () => props.project,
    (project) => {
        if (project) {
            form.style = project.style ?? '';
            form.format = project.format ?? '';
            form.project_name = project.project_name ?? '';
            form.camera = project.camera ?? '';
            form.quality = project.quality ?? '';
            form.music = project.music ?? '';
            form.music_link = project.music_link ?? '';
            form.file_link = project.file_link ?? '';
            form.notes = project.notes ?? '';

            // Set agent and per-property options
            agentOption.value = project.with_agent ? 'with-agent' : 'no-agent';
            perPropertyOption.value = project.per_property ? 'add-per-property' : 'no';

            form.with_agent = project.with_agent ?? false;
            form.per_property = project.per_property ?? false;
        } else {
            // Reset form
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
            form.with_agent = false;
            form.per_property = false;
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
                    {{ props.project ? `Edit Project - ${form.project_name}` : 'Order: Basic Style' }}
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
                                <SelectItem value="basic video">Basic Video</SelectItem>
                                <SelectItem value="basic drone only">Basic Drone Only</SelectItem>
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

                    <!-- File Link -->
                    <div class="space-y-2">
                        <Label>File Link</Label>
                        <Input v-model="form.file_link" placeholder="Enter your file link" />
                        <span v-if="form.errors.file_link" class="text-sm text-red-500">{{ form.errors.file_link }}</span>
                    </div>

                    <!-- Rush Option -->
                    <div class="space-y-2">
                        <Label>Rush (with additional charges)</Label>
                        <Select :modelValue="form.rush ? 'true' : 'false'" @update:modelValue="(val) => (form.rush = val === 'true')">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select option" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="true">Yes</SelectItem>
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
                        <Input v-model="form.notes" placeholder="Enter more instructions" />
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

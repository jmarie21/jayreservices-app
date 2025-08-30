<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DeluxeForm } from '@/types/app-page-prop';
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    open: boolean;
    basePrice: number;
    serviceId: number;
    project?: DeluxeForm | null;
}>();
const emit = defineEmits<{
    (e: 'close'): void;
}>();

const agentOption = ref<'with-agent' | 'no-agent' | ''>('');
// const totalPrice = ref(props.basePrice);

const form = useForm<DeluxeForm>({
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
    total_price: Number(props.project?.total_price ?? props.basePrice),
    with_agent: props.project?.with_agent ?? false,
    service_id: props.serviceId,
});

watch(agentOption, (value) => {
    const extraCost = value === 'with-agent' ? 10 : 0;
    form.total_price = Number(props.basePrice) + extraCost;
    form.with_agent = value === 'with-agent';
});

watch(
    () => props.project,
    (project) => {
        if (project) {
            form.style = project.style;
            form.company_name = project.company_name;
            form.contact = project.contact;
            form.project_name = project.project_name;
            form.format = project.format ?? '';
            form.camera = project.camera ?? '';
            form.quality = project.quality ?? '';
            form.music = project.music ?? '';
            form.music_link = project.music_link ?? '';
            form.file_link = project.file_link ?? '';
            form.notes = project.notes ?? '';
            agentOption.value = project.with_agent ? 'with-agent' : 'no-agent';
            form.with_agent = project.with_agent ?? false;

            const extraCost = project.with_agent ? 10 : 0;
            form.total_price = Number(props.basePrice) + extraCost;
        } else {
            // If no project (i.e., creating new), reset the form
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
        }
    },
    { immediate: true },
);

const handleSubmit = () => {
    const isEditing = !!props.project;

    if (isEditing) {
        form.put(route('projects.client_update', props.project!.id), {
            onSuccess: () => {
                toast.success('Updated successfully!', {
                    description: 'Your order was updated successfully!',
                    position: 'top-right',
                });

                emit('close');
            },
            onError: (error) => {
                console.error('Validation errors:', form.errors);
            },
        });
    } else {
        form.post(route('projects.store'), {
            onSuccess: () => {
                toast.success('Order placed', {
                    description: 'Your order has been placed.',
                    position: 'top-right',
                });
            },
            onError: (error) => {
                console.error('Validation errors:', form.errors);
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
                    {{ props.project ? `Edit Project - ${form.project_name}` : 'Order: Deluxe Style' }}
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
                                <SelectItem value="deluxe video">Deluxe Video</SelectItem>
                                <SelectItem value="deluxe drone only">Deluxe Drone Only</SelectItem>
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
                            <span v-if="form.processing" class="mr-2 animate-spin">⏳</span>
                            {{ props.project ? 'Save Changes' : 'Place Order' }}
                        </Button>
                    </div>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>

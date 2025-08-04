<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    basePrice: number;
}>();
const emit = defineEmits<{
    (e: 'close'): void;
}>();

const agentOption = ref<'with-agent' | 'no-agent' | ''>('');
const totalPrice = ref(props.basePrice);

const formData = ref({
    style: '',
    companyName: '',
    contact: '',
    projectName: '',
    format: '',
    camera: '',
    quality: '',
    music: '',
    musicLink: '',
    fileLink: '',
    notes: '',
    price: totalPrice.value,
});

watch(agentOption, (value) => {
    const extraCost = value === 'with-agent' ? 10 : 0;
    totalPrice.value = props.basePrice + extraCost;
    formData.value.price = totalPrice.value;
});

const handleSubmit = () => {
    console.log('Basic form submitted:', formData.value);
    emit('close');
};
</script>

<template>
    <Dialog :open="open" @update:open="(v) => !v && emit('close')">
        <DialogContent class="!w-full !max-w-6xl">
            <DialogHeader>
                <DialogTitle>Order: Basic Style</DialogTitle>
            </DialogHeader>

            <form @submit.prevent="handleSubmit">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <!-- Style -->
                    <div class="space-y-2">
                        <Label>Select Style</Label>
                        <Select v-model="formData.style">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Style" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="basic video">Basic Video</SelectItem>
                                <SelectItem value="basic drone only">Basic Drone Only</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Company Name -->
                    <div class="space-y-2">
                        <Label>Company Name</Label>
                        <Input v-model="formData.companyName" placeholder="Enter your company name" />
                    </div>

                    <!-- Contact -->
                    <div class="space-y-2">
                        <Label>Email or Social Media</Label>
                        <Input v-model="formData.contact" placeholder="Enter your email or any social media" />
                    </div>

                    <!-- Project Name -->
                    <div class="space-y-2">
                        <Label>Project Name</Label>
                        <Input v-model="formData.projectName" placeholder="Enter your project name" />
                    </div>

                    <!-- Format -->
                    <div class="space-y-2">
                        <Label>Video Format</Label>
                        <Select v-model="formData.format">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Format" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="horizontal">Horizontal</SelectItem>
                                <SelectItem value="vertical">Vertical</SelectItem>
                                <SelectItem value="horizontal and vertical package"> Horizontal and Vertical Package </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Camera -->
                    <div class="space-y-2">
                        <Label>Camera</Label>
                        <Input v-model="formData.camera" placeholder="Enter your camera brand and model" />
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
                        <Select v-model="formData.quality">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Quality" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="4k quality">4K Quality</SelectItem>
                                <SelectItem value="1080p HD quality">1080P HD Quality</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Music -->
                    <div class="space-y-2">
                        <Label>Music Preference</Label>
                        <Select v-model="formData.music">
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
                    </div>

                    <!-- Music Link -->
                    <div class="space-y-2">
                        <Label>If providing music, link or title</Label>
                        <Input v-model="formData.musicLink" placeholder="Enter song link and title" />
                    </div>

                    <!-- File Link -->
                    <div class="space-y-2">
                        <Label>File Link</Label>
                        <Input v-model="formData.fileLink" placeholder="Enter your file link" />
                    </div>

                    <!-- Notes -->
                    <div class="space-y-2">
                        <Label>More Instructions</Label>
                        <Input v-model="formData.notes" placeholder="Enter more instructions" />
                    </div>

                    <!-- Total & Submit -->
                    <div class="mt-8 text-xl font-semibold">Total: ${{ totalPrice.toFixed(2) }}</div>
                    <div class="mt-8 flex justify-end">
                        <Button type="submit">Place Order</Button>
                    </div>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>

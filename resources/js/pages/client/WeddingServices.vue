<script setup lang="ts">
import WeddingBasicForm from '@/components/forms/WeddingBasicForm.vue';
import DeluxeStyleForm from '@/components/forms/DeluxeStyleForm.vue';
import LuxuryStyleForm from '@/components/forms/LuxuryStyleForm.vue';
import PremiumStyleForm from '@/components/forms/PremiumStyleForm.vue';
import WeddingPremiumForm from '@/components/forms/WeddingPremiumForm.vue';
import TalkingHeadsForm from '@/components/forms/TalkingHeadsForm.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AppPageProps, BreadcrumbItem, Services } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const { services } = usePage<AppPageProps<{ services: Services[] }>>().props;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Wedding Services',
        href: '/wedding-services',
    },
];

const selectedService = ref<Services | null>(null);

const isStyle = (style: string): style is 'Wedding Basic Style' | 'Wedding Deluxe Style' | 'Wedding Premium Style' | 'Wedding Luxury Style' | 'Wedding Talking Heads' => {
    return ['Wedding Basic Style', 'Wedding Deluxe Style', 'Wedding Premium Style', 'Wedding Luxury Style', 'Wedding Talking Heads'].includes(style);
};

function openModal(service: Services) {
    if (isStyle(service.name)) {
        selectedService.value = service;
    }
}

function closeModal() {
    selectedService.value = null;
}
</script>

<template>
    <Toaster />
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Wedding Services" />
        <div class="grid gap-4 p-4 md:grid-cols-3">
            <div v-for="service in services" :key="service.id" class="flex h-full min-h-[450px] flex-col rounded-xl border bg-white shadow">
                <!-- Video at top (no padding) -->
                <div v-if="service.video_link" class="aspect-video w-full">
                    <iframe
                        class="h-full w-full rounded-t-xl"
                        :src="service.video_link.replace('watch?v=', 'embed/')"
                        :title="service.name"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                    ></iframe>
                </div>

                <!-- Card content with padding -->
                <div class="flex flex-1 flex-col p-4">
                    <h3 class="mb-2 text-lg font-semibold">{{ service.name }}</h3>

                    <ul class="list-inside list-disc text-sm text-muted-foreground">
                        <li v-for="(feature, index) in service.features" :key="index">
                            {{ feature }}
                        </li>
                    </ul>

                    <div class="mt-auto">
                        <!-- <p class="mb-2 font-bold text-primary">${{ service.price }}</p> -->
                        <Button class="w-full" @click="openModal(service)">Select Style</Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <WeddingBasicForm
            v-if="selectedService?.name === 'Wedding Basic Style'"
            :open="true"
            :base-price="selectedService.price"
            @close="closeModal"
            :service-id="selectedService.id"
        />
        <!-- <DeluxeStyleForm
            v-if="selectedService?.name === 'Wedding Deluxe Style'"
            :open="true"
            :base-price="selectedService.price"
            :service-id="selectedService.id"
            @close="closeModal"
        /> -->

        <!-- <TalkingHeadsForm
            v-if="selectedService?.name === 'Wedding Talking Heads'"
            :open="true"
            :base-price="selectedService.price"
            :service-id="selectedService.id"
            @close="closeModal"
        /> -->

        <WeddingPremiumForm
            v-if="selectedService?.name === 'Wedding Premium Style'"
            :open="true"
            :base-price="selectedService.price"
            :service-id="selectedService.id"
            @close="closeModal"
        />

        <LuxuryStyleForm
            v-if="selectedService?.name === 'Wedding Luxury Style'"
            :open="true"
            :base-price="selectedService.price"
            :service-id="selectedService.id"
            @close="closeModal"
        />
    </AppLayout>
</template>

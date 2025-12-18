<script setup lang="ts">
import BasicStyleForm from '@/components/forms/BasicStyleForm.vue';
import DeluxeStyleForm from '@/components/forms/DeluxeStyleForm.vue';
import LuxuryStyleForm from '@/components/forms/LuxuryStyleForm.vue';
import PremiumStyleForm from '@/components/forms/PremiumStyleForm.vue';
import TalkingHeadsForm from '@/components/forms/TalkingHeadsForm.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AppPageProps, BreadcrumbItem, Services } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const { services } = usePage<AppPageProps<{ services: Services[] }>>().props;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Services',
        href: '/services',
    },
];

const selectedService = ref<Services | null>(null);

const isImageLink = (link?: string | null) => {
    if (!link) return false;
    return link.startsWith('/images/') || /\.(png|jpe?g|gif|webp|svg)(\?.*)?$/i.test(link);
};

const normalizeVideoSrc = (link: string) => link.replace('watch?v=', 'embed/');

const isStyle = (style: string): style is 'Real Estate Basic Style' | 'Real Estate Deluxe Style' | 'Real Estate Premium Style' | 'Real Estate Luxury Style' | 'Real Estate Talking Heads' => {
    return ['Real Estate Basic Style', 'Real Estate Deluxe Style', 'Real Estate Premium Style', 'Real Estate Luxury Style', 'Real Estate Talking Heads'].includes(style);
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
        <Head title="Services" />
        <div class="grid gap-4 p-4 md:grid-cols-4">
            <div v-for="service in services" :key="service.id" class="flex h-full min-h-[450px] flex-col rounded-xl border bg-white shadow">
                <!-- Video at top (no padding) -->
                <div v-if="service.video_link" class="aspect-video w-full">
                    <img
                        v-if="isImageLink(service.video_link)"
                        class="h-full w-full rounded-t-xl object-cover"
                        :src="service.video_link"
                        :alt="service.name"
                        loading="lazy"
                    />
                    <iframe
                        v-else
                        class="h-full w-full rounded-t-xl"
                        :src="normalizeVideoSrc(service.video_link)"
                        :title="service.name"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                    ></iframe>
                </div>

                <!-- Card content with padding -->
                <div class="flex flex-1 flex-col p-4">
                    <h3 class="mb-2 text-lg font-semibold">{{ service.name }}</h3>

                    <ul v-if="service.name !== 'Real Estate  Talking Heads'" class="list-inside list-disc text-sm text-muted-foreground">
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
        <BasicStyleForm
            v-if="selectedService?.name === 'Real Estate Basic Style'"
            :open="true"
            :base-price="selectedService.price"
            @close="closeModal"
            :service-id="selectedService.id"
        />
        <DeluxeStyleForm
            v-if="selectedService?.name === 'Real Estate Deluxe Style'"
            :open="true"
            :base-price="selectedService.price"
            :service-id="selectedService.id"
            @close="closeModal"
        />

        <TalkingHeadsForm
            v-if="selectedService?.name === 'Real Estate Talking Heads'"
            :open="true"
            :base-price="selectedService.price"
            :service-id="selectedService.id"
            @close="closeModal"
        />

        <PremiumStyleForm
            v-if="selectedService?.name === 'Real Estate Premium Style'"
            :open="true"
            :base-price="selectedService.price"
            :service-id="selectedService.id"
            @close="closeModal"
        />

        <LuxuryStyleForm
            v-if="selectedService?.name === 'Real Estate Luxury Style'"
            :open="true"
            :base-price="selectedService.price"
            :service-id="selectedService.id"
            @close="closeModal"
        />
    </AppLayout>
</template>

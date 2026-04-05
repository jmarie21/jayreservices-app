<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { getServiceIcon } from '@/lib/service-icons';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { ServiceCategory } from '@/types/services';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Services',
        href: '/services',
    },
];

const page = usePage<{ categories: ServiceCategory[] }>();
const categories = computed(() => page.props.categories ?? []);
</script>

<template>
    <Head title="Services" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4">
            <div class="max-w-3xl space-y-2">
                <h1 class="text-3xl font-bold">Services</h1>
                <p class="text-muted-foreground">Choose a category to browse the available styles, formats, and add-ons.</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Card v-for="category in categories" :key="category.id" class="border-0 shadow-sm ring-1 ring-black/5">
                    <CardHeader class="space-y-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-700">
                            <component :is="getServiceIcon(category.icon)" class="h-6 w-6" />
                        </div>
                        <div class="space-y-2">
                            <CardTitle>{{ category.name }}</CardTitle>
                            <CardDescription>{{ category.description || 'Browse the styles in this category.' }}</CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="text-sm text-muted-foreground">
                            {{ category.services.length }} {{ category.services.length === 1 ? 'service' : 'services' }}
                        </div>

                        <Button as-child class="w-full">
                            <Link :href="route('services.category', { category: category.slug })">View Services</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

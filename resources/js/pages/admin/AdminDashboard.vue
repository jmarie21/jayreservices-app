<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

// shadcn components
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// Breadcrumbs
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Admin Dashboard', href: '/admin-dashboard' }];

// Get dashboard data from the backend (using Inertia props)
const page = usePage<any>();
const projectsCount = computed(() => page.props.dashboard?.projectsCount ?? 0);
const clientsCount = computed(() => page.props.dashboard?.clientsCount ?? 0);
const activeEditors = computed(() => page.props.dashboard?.activeEditors ?? 0);
const monthlyProfit = computed(() => page.props.dashboard?.monthlyProfit ?? 0);
</script>

<template>
    <Head title="Admin Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <!-- Projects Card -->
                <Card class="p-4">
                    <CardHeader>
                        <CardTitle>Total Projects</CardTitle>
                    </CardHeader>
                    <CardContent class="flex h-full flex-col items-start justify-between">
                        <div class="text-3xl font-bold">{{ projectsCount }}</div>
                    </CardContent>
                </Card>

                <!-- Clients Card -->
                <Card class="p-4">
                    <CardHeader>
                        <CardTitle>Total Clients</CardTitle>
                    </CardHeader>
                    <CardContent class="flex h-full flex-col items-start justify-between">
                        <div class="text-3xl font-bold">{{ clientsCount }}</div>
                    </CardContent>
                </Card>

                <!-- Active Editors Card -->
                <Card class="p-4">
                    <CardHeader>
                        <CardTitle>Editors</CardTitle>
                    </CardHeader>
                    <CardContent class="flex h-full flex-col items-start justify-between">
                        <div class="text-3xl font-bold">{{ activeEditors }}</div>
                    </CardContent>
                </Card>
            </div>

            <!-- Recent Projects & Monthly Profit -->
            <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                <Card class="p-4">
                    <CardHeader>
                        <CardTitle>Recent Projects</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Project Name</TableHead>
                                        <TableHead>Client Name</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="project in page.props.dashboard?.recentProjects ?? []" :key="project.id">
                                        <TableCell>{{ project.project_name }}</TableCell>
                                        <TableCell>{{ project.client_name }}</TableCell>
                                    </TableRow>
                                    <TableRow v-if="!page.props.dashboard?.recentProjects?.length">
                                        <TableCell colspan="2" class="text-center text-gray-500"> No recent projects </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>

                <Card class="p-4">
                    <CardHeader>
                        <CardTitle>Monthly Profit</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col items-start justify-between">
                        <div class="text-3xl font-bold text-green-600">${{ monthlyProfit.toFixed(2) }}</div>
                        <p class="mt-2 text-sm text-gray-500">
                            Total profit for Month of {{ new Date().toLocaleString('default', { month: 'long' }) }}
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

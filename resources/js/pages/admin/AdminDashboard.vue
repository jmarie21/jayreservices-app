<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

import { Bar } from 'vue-chartjs';
import { BarElement, CategoryScale, Chart as ChartJS, Legend, LinearScale, Title, Tooltip } from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend);

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Admin Dashboard', href: '/admin-dashboard' }];

const page = usePage<any>();

const projectsCount = computed(() => page.props.dashboard?.projectsCount ?? 0);
const clientsCount = computed(() => page.props.dashboard?.clientsCount ?? 0);
const activeEditors = computed(() => page.props.dashboard?.activeEditors ?? 0);
const weeklyRevenue = computed(() => page.props.dashboard?.weeklyRevenue ?? 0);
const weeklyProjectsDelta = computed(() => page.props.dashboard?.weeklyProjectsDelta ?? 0);
const weeklyClientsDelta = computed(() => page.props.dashboard?.weeklyClientsDelta ?? 0);
const revenueChangePercent = computed<number | null>(() => page.props.dashboard?.revenueChangePercent ?? null);
const allPipelineData = computed(() => page.props.dashboard?.projectsByStatusByPeriod ?? {});
const editorWorkload = computed(() => page.props.dashboard?.editorWorkload ?? []);
const revenueTrend = computed(() => page.props.dashboard?.revenueTrend ?? []);
const recentProjects = computed(() => page.props.dashboard?.recentProjects ?? []);
const serviceBreakdown = computed(() => page.props.dashboard?.serviceBreakdown ?? []);
const topClients = computed(() => page.props.dashboard?.topClients ?? []);

// --- Revenue trend date range filter ---
const fromDate = ref<string>(page.props.dashboard?.trendFrom ?? '');
const toDate = ref<string>(page.props.dashboard?.trendTo ?? '');
const trendTotal = computed(() => revenueTrend.value.reduce((sum: number, w: any) => sum + (w.revenue ?? 0), 0));

function applyTrendFilter(): void {
    if (!fromDate.value || !toDate.value) return;
    router.get(
        route('dashboard'),
        { trend_from: fromDate.value, trend_to: toDate.value },
        { preserveState: true, preserveScroll: true, only: ['dashboard'] },
    );
}

// --- Pipeline filter (client-side switching between preloaded periods) ---
type PipelinePeriod = 'all' | '7d' | '30d' | 'month';
const pipelinePeriod = ref<PipelinePeriod>('all');
const pipelineOptions: { label: string; value: PipelinePeriod }[] = [
    { label: 'All', value: 'all' },
    { label: '7D', value: '7d' },
    { label: '30D', value: '30d' },
    { label: 'Month', value: 'month' },
];
const projectsByStatus = computed(() => allPipelineData.value[pipelinePeriod.value] ?? {});
const pipelineTotal = computed(() =>
    Object.values(projectsByStatus.value as Record<string, number>).reduce((sum, n) => sum + n, 0),
);

// --- Status config ---
const statusConfig: Record<string, { label: string; dot: string; pill: string }> = {
    pending: { label: 'Pending', dot: 'bg-gray-400', pill: 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300' },
    todo: { label: 'To Do', dot: 'bg-slate-500', pill: 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300' },
    backlog: { label: 'Backlog', dot: 'bg-zinc-400', pill: 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300' },
    in_progress: { label: 'In Progress', dot: 'bg-amber-400', pill: 'bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400' },
    for_qa: { label: 'For QA', dot: 'bg-orange-400', pill: 'bg-orange-50 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400' },
    done_qa: { label: 'Done QA', dot: 'bg-blue-400', pill: 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400' },
    sent_to_client: { label: 'Sent to Client', dot: 'bg-purple-400', pill: 'bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400' },
    revision: { label: 'Revision', dot: 'bg-red-400', pill: 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400' },
    revision_completed: { label: 'Rev. Completed', dot: 'bg-green-400', pill: 'bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-400' },
    completed: { label: 'Completed', dot: 'bg-emerald-500', pill: 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400' },
    cancelled: { label: 'Cancelled', dot: 'bg-rose-500', pill: 'bg-rose-50 dark:bg-rose-900/30 text-rose-700 dark:text-rose-400' },
};

const pipelineStatuses = Object.keys(statusConfig);
const getStatusPill = (status: string): string => statusConfig[status]?.pill ?? 'bg-gray-100 text-gray-600';
const getStatusLabel = (status: string): string => statusConfig[status]?.label ?? status;

const priorityClass = (priority: string): string => {
    const map: Record<string, string> = {
        urgent: 'font-semibold text-red-600',
        high: 'text-orange-500',
        normal: 'text-blue-500',
        low: 'text-gray-400',
    };
    return map[priority] ?? '';
};

const formatRevenue = (value: number): string =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value);

const deltaClass = (delta: number): string => {
    if (delta > 0) return 'text-green-600 dark:text-green-400';
    if (delta < 0) return 'text-red-500 dark:text-red-400';
    return 'text-muted-foreground';
};

const formatDelta = (delta: number, label: string): string => {
    if (delta > 0) return `+${delta} ${label}`;
    if (delta < 0) return `${delta} ${label}`;
    return `Same as last week`;
};

// Dark mode detection
const isDark = ref(false);
onMounted(() => {
    isDark.value = document.documentElement.classList.contains('dark');
    const observer = new MutationObserver(() => {
        isDark.value = document.documentElement.classList.contains('dark');
    });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});

const chartData = computed(() => ({
    labels: revenueTrend.value.map((w: any) => w.day),
    datasets: [
        {
            label: 'Revenue',
            data: revenueTrend.value.map((w: any) => w.revenue),
            backgroundColor: isDark.value ? 'rgba(99, 102, 241, 0.65)' : 'rgba(99, 102, 241, 0.55)',
            borderColor: isDark.value ? 'rgba(129, 140, 248, 1)' : 'rgba(79, 70, 229, 1)',
            borderWidth: 1,
            borderRadius: 4,
        },
    ],
}));

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx: any) => {
                    const trend = revenueTrend.value[ctx.dataIndex];
                    return [`${formatRevenue(ctx.parsed.y)}`, `${trend?.count ?? 0} project(s)`];
                },
            },
        },
    },
    scales: {
        x: {
            ticks: { color: isDark.value ? '#9ca3af' : '#6b7280', font: { size: 10 }, maxRotation: 45 },
            grid: { color: isDark.value ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.04)' },
        },
        y: {
            beginAtZero: true,
            ticks: {
                color: isDark.value ? '#9ca3af' : '#6b7280',
                font: { size: 11 },
                callback: (value: number) => `$${value}`,
            },
            grid: { color: isDark.value ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.04)' },
        },
    },
}));
</script>

<template>
    <Head title="Admin Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Row 1: Stat cards — 2 cols on mobile, 4 on md+ -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <!-- Total Projects -->
                <Card>
                    <CardHeader class="px-4 pb-1 pt-4">
                        <CardTitle class="text-sm font-medium text-muted-foreground">Total Projects</CardTitle>
                    </CardHeader>
                    <CardContent class="px-4 pb-4">
                        <div class="text-2xl font-bold md:text-3xl">{{ projectsCount }}</div>
                        <p class="mt-1 text-xs" :class="deltaClass(weeklyProjectsDelta)">
                            {{ formatDelta(weeklyProjectsDelta, 'this week') }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Total Clients -->
                <Card>
                    <CardHeader class="px-4 pb-1 pt-4">
                        <CardTitle class="text-sm font-medium text-muted-foreground">Total Clients</CardTitle>
                    </CardHeader>
                    <CardContent class="px-4 pb-4">
                        <div class="text-2xl font-bold md:text-3xl">{{ clientsCount }}</div>
                        <p class="mt-1 text-xs" :class="deltaClass(weeklyClientsDelta)">
                            {{ formatDelta(weeklyClientsDelta, 'this week') }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Editors -->
                <Card>
                    <CardHeader class="px-4 pb-1 pt-4">
                        <CardTitle class="text-sm font-medium text-muted-foreground">Editors</CardTitle>
                    </CardHeader>
                    <CardContent class="px-4 pb-4">
                        <div class="text-2xl font-bold md:text-3xl">{{ activeEditors }}</div>
                        <p class="mt-1 text-xs text-muted-foreground">Active editors</p>
                    </CardContent>
                </Card>

                <!-- Weekly Revenue -->
                <Card>
                    <CardHeader class="px-4 pb-1 pt-4">
                        <CardTitle class="text-sm font-medium text-muted-foreground">Weekly Revenue</CardTitle>
                    </CardHeader>
                    <CardContent class="px-4 pb-4">
                        <div class="text-2xl font-bold text-indigo-600 md:text-3xl dark:text-indigo-400">
                            {{ formatRevenue(weeklyRevenue) }}
                        </div>
                        <p class="mt-1 text-xs" v-if="revenueChangePercent !== null">
                            <span :class="revenueChangePercent >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400'">
                                {{ revenueChangePercent >= 0 ? '↑' : '↓' }} {{ Math.abs(revenueChangePercent) }}%
                            </span>
                            <span class="text-muted-foreground"> vs last week</span>
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground" v-else>This week's project total</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Row 2: Pipeline + Chart -->
            <div class="grid gap-4 md:grid-cols-2">
                <!-- Project Pipeline -->
                <Card>
                    <CardHeader class="px-4 pb-3 pt-4">
                        <div class="flex flex-col gap-2">
                            <div class="flex items-start justify-between gap-2">
                                <CardTitle>Project Pipeline</CardTitle>
                                <!-- Pipeline period filter -->
                                <div class="flex overflow-hidden rounded-md border text-xs">
                                    <button
                                        v-for="opt in pipelineOptions"
                                        :key="opt.value"
                                        @click="pipelinePeriod = opt.value"
                                        :class="[
                                            'border-l px-2.5 py-1 font-medium transition-colors first:border-l-0',
                                            pipelinePeriod === opt.value
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-background text-muted-foreground hover:bg-muted',
                                        ]"
                                    >
                                        {{ opt.label }}
                                    </button>
                                </div>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                <span class="font-semibold text-foreground">{{ pipelineTotal }}</span> project(s)
                            </p>
                        </div>
                    </CardHeader>
                    <CardContent class="px-4 pb-4">
                        <div class="space-y-1.5">
                            <div
                                v-for="status in pipelineStatuses"
                                :key="status"
                                :class="['flex items-center justify-between rounded-lg px-3 py-2', statusConfig[status].pill]"
                            >
                                <div class="flex items-center gap-2">
                                    <span :class="['h-2 w-2 shrink-0 rounded-full', statusConfig[status].dot]" />
                                    <span class="text-xs font-medium">{{ statusConfig[status].label }}</span>
                                </div>
                                <span class="text-sm font-bold">{{ projectsByStatus[status] ?? 0 }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Revenue Trend -->
                <Card>
                    <CardHeader class="px-4 pb-3 pt-4">
                        <div class="flex flex-col gap-2">
                            <div class="flex items-start justify-between gap-2">
                                <CardTitle>Revenue Trend</CardTitle>
                                <p class="shrink-0 text-sm text-muted-foreground">
                                    Total: <span class="font-semibold text-foreground">{{ formatRevenue(trendTotal) }}</span>
                                </p>
                            </div>
                            <!-- Date range filter -->
                            <div class="flex flex-wrap items-center gap-1.5">
                                <input
                                    type="date"
                                    v-model="fromDate"
                                    @change="applyTrendFilter"
                                    class="rounded border bg-background px-2 py-1 text-xs text-foreground"
                                />
                                <span class="text-xs text-muted-foreground">to</span>
                                <input
                                    type="date"
                                    v-model="toDate"
                                    @change="applyTrendFilter"
                                    class="rounded border bg-background px-2 py-1 text-xs text-foreground"
                                />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-1 flex-col px-4 pb-4">
                        <div class="min-h-48 flex-1 overflow-hidden">
                            <Bar :data="chartData" :options="chartOptions" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Row 3: Editor Workload + Recent Projects -->
            <div class="grid gap-4 md:grid-cols-2">
                <!-- Editor Workload -->
                <Card>
                    <CardHeader class="px-4 pb-3 pt-4">
                        <CardTitle>Editor Workload</CardTitle>
                    </CardHeader>
                    <CardContent class="px-4 pb-4">
                        <div v-if="editorWorkload.length === 0" class="py-6 text-center text-sm text-muted-foreground">No editors found.</div>
                        <div v-else class="overflow-x-auto">
                            <Table class="min-w-[360px]">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Editor</TableHead>
                                        <TableHead class="text-center">Active</TableHead>
                                        <TableHead class="text-center">Revision</TableHead>
                                        <TableHead class="text-center">Total</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="editor in editorWorkload" :key="editor.name">
                                        <TableCell class="font-medium">{{ editor.name }}</TableCell>
                                        <TableCell class="text-center">
                                            <span class="font-semibold text-amber-600 dark:text-amber-400">{{ editor.active }}</span>
                                        </TableCell>
                                        <TableCell class="text-center">
                                            <span class="font-semibold text-red-500 dark:text-red-400">{{ editor.revision }}</span>
                                        </TableCell>
                                        <TableCell class="text-center font-bold">{{ editor.total }}</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>

                <!-- Recent Projects -->
                <Card>
                    <CardHeader class="px-4 pb-3 pt-4">
                        <CardTitle>Recent Projects</CardTitle>
                    </CardHeader>
                    <CardContent class="px-4 pb-4">
                        <div class="overflow-x-auto">
                            <Table class="min-w-[560px]">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Project</TableHead>
                                        <TableHead>Client</TableHead>
                                        <TableHead>Service</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Priority</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="project in recentProjects" :key="project.id">
                                        <TableCell class="font-medium">
                                            <div class="flex items-center gap-1.5">
                                                {{ project.project_name }}
                                                <span
                                                    v-if="project.rush"
                                                    class="rounded bg-red-100 px-1 py-0.5 text-xs font-semibold text-red-600 dark:bg-red-900/30 dark:text-red-400"
                                                >
                                                    Rush
                                                </span>
                                            </div>
                                        </TableCell>
                                        <TableCell>{{ project.client_name }}</TableCell>
                                        <TableCell class="text-xs text-muted-foreground">{{ project.service_name }}</TableCell>
                                        <TableCell>
                                            <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', getStatusPill(project.status)]">
                                                {{ getStatusLabel(project.status) }}
                                            </span>
                                        </TableCell>
                                        <TableCell :class="['text-sm capitalize', priorityClass(project.priority)]">
                                            {{ project.priority }}
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="recentProjects.length === 0">
                                        <TableCell colspan="5" class="text-center text-muted-foreground">No recent projects.</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Row 4: Service Breakdown + Top Clients -->
            <div class="grid gap-4 md:grid-cols-2">
                <!-- Service Breakdown -->
                <Card>
                    <CardHeader class="px-4 pb-3 pt-4">
                        <CardTitle>Service Breakdown</CardTitle>
                    </CardHeader>
                    <CardContent class="px-4 pb-4">
                        <div v-if="serviceBreakdown.length === 0" class="py-6 text-center text-sm text-muted-foreground">No services found.</div>
                        <div v-else class="overflow-x-auto">
                            <Table class="min-w-[320px]">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Service</TableHead>
                                        <TableHead class="text-center">Projects</TableHead>
                                        <TableHead class="text-right">Revenue</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="service in serviceBreakdown" :key="service.name">
                                        <TableCell class="font-medium">{{ service.name }}</TableCell>
                                        <TableCell class="text-center font-semibold">{{ service.count }}</TableCell>
                                        <TableCell class="text-right text-sm text-indigo-600 dark:text-indigo-400">
                                            {{ formatRevenue(service.revenue) }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>

                <!-- Top Clients -->
                <Card>
                    <CardHeader class="px-4 pb-3 pt-4">
                        <CardTitle>Top Clients</CardTitle>
                    </CardHeader>
                    <CardContent class="px-4 pb-4">
                        <div v-if="topClients.length === 0" class="py-6 text-center text-sm text-muted-foreground">No clients found.</div>
                        <div v-else class="overflow-x-auto">
                            <Table class="min-w-[320px]">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Client</TableHead>
                                        <TableHead class="text-center">Projects</TableHead>
                                        <TableHead class="text-right">Total Spent</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="client in topClients" :key="client.name">
                                        <TableCell class="font-medium">{{ client.name }}</TableCell>
                                        <TableCell class="text-center font-semibold">{{ client.count }}</TableCell>
                                        <TableCell class="text-right text-sm text-indigo-600 dark:text-indigo-400">
                                            {{ formatRevenue(client.revenue) }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

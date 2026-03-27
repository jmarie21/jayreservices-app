<script setup lang="ts">
import ProjectViewModal from '@/components/modals/ProjectViewModal.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import ProjectFilters from '@/components/ProjectFilters.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination, PaginationContent, PaginationItem, PaginationNext, PaginationPrevious } from '@/components/ui/pagination';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Toaster } from '@/components/ui/sonner';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { AppPageProps, Projects, type BreadcrumbItem } from '@/types';
import { Paginated } from '@/types/app-page-prop';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ChevronDown, Clock, Download, Eye } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

type Status = 'todo' | 'in_progress' | 'for_qa' | 'done_qa' | 'sent_to_client' | 'revision' | 'revision_completed' | 'backlog' | 'cancelled';
type Priority = 'urgent' | 'high' | 'normal' | 'low';
interface Filters {
    status?: string;
    date_from?: string;
    date_to?: string;
    search?: string;
    editor_id?: string;
    [key: string]: string | undefined; // Add index signature
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Project Management', href: '/project-mgmt' }];

const pageProps = usePage<
    AppPageProps<{
        client: { id: number; name: string; email: string };
        projects: Paginated<Projects>;
        editors: { id: number; name: string }[];
        filters?: { status?: string; date_from?: string; date_to?: string; search?: string; editor_id: string };
        viewProjectId?: number;
    }>
>().props;

// const page = usePage<
//     AppPageProps<{
//         client: { id: number; name: string; email: string };
//         projects: Paginated<Projects>;
//         editors: { id: number; name: string }[];
//         filters?: { status?: string; date_from?: string; date_to?: string; search?: string };
//         viewProjectId?: number;
//     }>
// >();

const { client, editors } = pageProps;
const projects = computed(() => pageProps.projects);

const showModal = ref(false);
const selectedProject = ref<Projects | null>(null);

const showDeleteModal = ref(false);
const projectToDelete = ref<Projects | null>(null);

const editorPrices = ref<Record<number, number | undefined>>({});
watch(
    projects,
    (newProjects) => {
        newProjects.data.forEach((p) => {
            if (!(p.id in editorPrices.value)) {
                editorPrices.value[p.id] = p.editor_price ?? undefined;
            }
        });
    },
    { immediate: true },
);

const form = useForm<{ editor_id: number | null; status: Status; editor_price: number | null; total_price: number | null; priority: Priority }>({
    editor_id: null,
    status: 'todo',
    editor_price: null,
    total_price: null,
    priority: 'normal',
});

// Status labels for badges
const statusLabels: Record<Status, string> = {
    todo: 'To Do',
    in_progress: 'In Progress',
    for_qa: 'For QA',
    done_qa: 'Done QA',
    revision: 'Revision',
    revision_completed: 'Revision Completed',
    backlog: 'Backlog',
    sent_to_client: 'Sent to Client',
    cancelled: 'Cancelled',
};

// Countdown timer
const now = ref(Date.now());
let countdownTimer: ReturnType<typeof setInterval>;

onMounted(() => {
    countdownTimer = setInterval(() => {
        now.value = Date.now();
    }, 60_000);
});
onUnmounted(() => clearInterval(countdownTimer));

const getDeadlineHours = (project: Projects): number => {
    const name = project.service?.name ?? '';
    const isRush = !!project.rush;
    if (name.includes('Luxury')) return isRush ? 18 : 36;
    if (name.includes('Premium')) return isRush ? 12 : 24;
    return isRush ? 6 : 12;
};

const getRevisionDeadlineHours = (): number => 3;

const getCountdown = (project: Projects): string | null => {
    let since: string | null = null;
    if (project.status === 'in_progress' && project.in_progress_since) {
        since = project.in_progress_since;
    } else if (project.status === 'revision' && project.revision_since) {
        since = project.revision_since;
    }
    if (!since) return null;
    const deadlineHours = project.status === 'revision' ? getRevisionDeadlineHours() : getDeadlineHours(project);
    const deadlineMs = deadlineHours * 60 * 60 * 1000;
    const deadline = new Date(since).getTime() + deadlineMs;
    const remaining = deadline - now.value;
    if (remaining <= 0) return 'overdue';
    const hours = Math.floor(remaining / 3_600_000);
    const minutes = Math.floor((remaining % 3_600_000) / 60_000);
    return `${hours}h ${minutes}m`;
};

const getTimerSince = (project: Projects): string | null => {
    if (project.status === 'in_progress') return project.in_progress_since;
    if (project.status === 'revision') return project.revision_since;
    return null;
};

const getCountdownColor = (project: Projects): string => {
    const countdown = getCountdown(project);
    if (!countdown || countdown === 'overdue') return 'text-red-500';
    const since = getTimerSince(project);
    if (!since) return 'text-green-600';
    const deadlineHours = project.status === 'revision' ? getRevisionDeadlineHours() : getDeadlineHours(project);
    const deadlineMs = deadlineHours * 60 * 60 * 1000;
    const deadline = new Date(since).getTime() + deadlineMs;
    const remaining = deadline - now.value;
    const hours = remaining / 3_600_000;
    if (hours < 4) return 'text-red-500';
    if (hours < 12) return 'text-yellow-500';
    return 'text-green-600';
};

const openViewModal = (project: Projects) => {
    selectedProject.value = project;
    showModal.value = true;
};

const closeViewModal = () => {
    showModal.value = false;
    selectedProject.value = null;

    // If we came from a notification (has viewProjectId), reset the search filter
    if (pageProps.viewProjectId) {
        filters.value.search = ''; // Clear the search filter

        // Apply filters without the search term
        router.visit(route('projects.all', filters.value), {
            preserveState: false, // 👈 Changed to false to reload projects
            preserveScroll: true,
            replace: true,
        });
    }
};

const totalPrices = ref<Record<number, number | undefined>>({});
watch(
    projects,
    (newProjects) => {
        newProjects.data.forEach((p) => {
            if (!(p.id in totalPrices.value)) {
                totalPrices.value[p.id] = p.total_price ?? undefined;
            }
        });
    },
    { immediate: true },
);

// Optimistic update for editor/status
const updateProject = <K extends keyof typeof form>(projectId: number, field: K, value: any) => {
    router.patch(
        route('projects.admin_update', projectId),
        { [field]: value },
        {
            preserveScroll: true,
            preserveState: false,
        },
    );
};

// Update project price
const updatePrice = (projectId: number, value: number | undefined) => {
    if (value !== undefined && value !== null) {
        router.patch(
            route('projects.update-price', projectId), // 👈 this hits your new backend method
            { total_price: value },
            {
                preserveScroll: true,
                onSuccess: () => {
                    const project = projects.value.data.find((p) => p.id === projectId);
                    if (project) project.total_price = value;
                    console.log('Total price updated!');
                },
            },
        );
    }
};

// Format date
// function formatLocalDate(d: Date) {
//     const year = d.getFullYear();
//     const month = String(d.getMonth() + 1).padStart(2, '0'); // Months are 0-indexed
//     const day = String(d.getDate()).padStart(2, '0');
//     return `${year}-${month}-${day}`;
// }

// // Get current week
// function getCurrentWeekRange() {
//     const now = new Date();
//     const day = now.getDay(); // 0 (Sun) - 6 (Sat)

//     // Start of week = Sunday
//     const sunday = new Date(now);
//     sunday.setDate(now.getDate() - day);
//     sunday.setHours(0, 0, 0, 0);

//     const saturday = new Date(sunday);
//     saturday.setDate(sunday.getDate() + 6);
//     saturday.setHours(23, 59, 59, 999);

//     return { date_from: formatLocalDate(sunday), date_to: formatLocalDate(saturday) };
// }

// const weekRange = getCurrentWeekRange();

// Filters state
// const today = formatLocalDate(new Date());

const filters = ref({
    status: pageProps.filters?.status || '',
    date_from: pageProps.filters?.date_from || '',
    date_to: pageProps.filters?.date_to || '',
    search: pageProps.filters?.search || '',
    editor_id: pageProps.filters?.editor_id || '', // Add this line
});

// Apply filters
const applyFilters = (newFilters: Filters) => {
    // Update local filters
    filters.value = { ...filters.value, ...newFilters };

    // Clean up empty values before sending
    const cleanFilters = Object.entries(filters.value).reduce((acc, [key, value]) => {
        if (value !== '' && value !== null && value !== undefined) {
            acc[key as keyof Filters] = value;
        }
        return acc;
    }, {} as Filters);

    // Debug log (remove after fixing)
    console.log('Applying filters to backend:', cleanFilters);

    router.get(route('projects.all'), cleanFilters, {
        preserveScroll: true,
        replace: true,
    });
};

// Pagination with filters
const goToPage = (pageNumber: number) => {
    router.get(
        route('projects.all'),
        { ...filters.value, page: pageNumber },
        {
            preserveScroll: true,
            replace: true,
        },
    );
};

// onMounted(() => {
//     if (!pageProps.filters?.date_from && !pageProps.filters?.date_to) {
//         applyFilters(filters.value);
//     }
// });

const confirmDelete = (project: Projects) => {
    projectToDelete.value = project;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    projectToDelete.value = null;
    showDeleteModal.value = false;
};

const deleteProject = () => {
    if (!projectToDelete.value) return;

    router.delete(route('projects.destroy', projectToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            // Optimistic update
            const index = projects.value.data.findIndex((p) => p.id === projectToDelete.value?.id);
            if (index !== -1) {
                projects.value.data.splice(index, 1);
            }
            closeDeleteModal();
            toast.success('Project deleted successfully.', { position: 'top-right' });
        },
    });
};

const filterQueryString = computed(() => {
    const params = new URLSearchParams();
    Object.entries(filters.value).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) {
            params.append(key, value);
        }
    });
    return params.toString();
});

const exportUrl = computed(() => {
    const qs = filterQueryString.value;
    return route('projects.all.export') + (qs ? `?${qs}` : '');
});

// Export preview
interface ExportRow {
    project_name: string;
    service: string;
    client: string;
    editor: string;
    priority: string;
    total_price: number | null;
    editor_price: number | null;
    add_ons: string;
    created_at: string;
}

const showPreviewModal = ref(false);
const previewData = ref<ExportRow[]>([]);
const previewLoading = ref(false);

const previewExport = async () => {
    previewLoading.value = true;
    try {
        const qs = filterQueryString.value;
        const url = route('projects.all.preview-export') + (qs ? `?${qs}` : '');
        const { data } = await axios.get<ExportRow[]>(url);
        previewData.value = data;
        showPreviewModal.value = true;
    } catch {
        toast.error('Failed to load export preview.', { position: 'top-right' });
    } finally {
        previewLoading.value = false;
    }
};

// 👇 Add this: Handle opening modal from notification
onMounted(() => {
    console.log('Page mounted'); // 👈 Debug
    console.log('viewProjectId:', pageProps.viewProjectId); // 👈 Debug
    console.log('Projects data:', projects.value.data); // 👈 Debug
    if (pageProps.viewProjectId) {
        const project = projects.value.data.find((p) => p.id === pageProps.viewProjectId);

        if (project) {
            // Open the modal automatically
            openViewModal(project);
        } else {
            // Project not on current page, try to fetch or show message
            toast.error('Project not found. Please search for it.', { position: 'top-right' });
        }
    }
});
</script>

<template>
    <Head :title="`All Projects`" />
    <Toaster />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="mb-2 flex items-center justify-between">
                <h1 class="text-3xl font-bold">All Projects</h1>

                <div class="flex items-center gap-3">
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline">
                                Export to Excel
                                <ChevronDown class="ml-1 size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="previewExport" :disabled="previewLoading">
                                <Eye class="mr-2 size-4" />
                                {{ previewLoading ? 'Loading...' : 'View Data' }}
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <a :href="exportUrl" target="_blank">
                                    <Download class="mr-2 size-4" />
                                    Export
                                </a>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <NotificationBell />
                </div>
            </div>

            <!-- Filters section -->
            <div class="flex justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <!-- Status & Date filters -->
                <div class="space-y-2">
                    <Label class="text-xl font-bold">Filter by:</Label>
                    <ProjectFilters :filters="filters" :role="pageProps.auth.user.role" :editors="editors" @update:filters="applyFilters" />
                </div>

                <!-- Search input -->
                <div class="w-[320px] space-y-2">
                    <Label class="text-xl font-bold">Search project:</Label>
                    <Input v-model="filters.search" type="text" placeholder="Search..." @keyup.enter="applyFilters" />
                </div>
            </div>

            <!-- Table section -->
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Project Name</TableHead>
                        <TableHead>Service</TableHead>
                        <TableHead>Client Name</TableHead>
                        <TableHead>Editor</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Priority Level</TableHead>
                        <TableHead>Total Price</TableHead>
                        <TableHead>Editor Price</TableHead>
                        <TableHead>Created At</TableHead>
                        <TableHead>Action</TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody v-for="project in projects.data" :key="project.id">
                    <TableRow>
                        <TableCell>
                            <div class="max-w-[200px] truncate" :title="project.project_name">
                                {{ project.project_name }}
                            </div>
                        </TableCell>
                        <TableCell>{{ project.service?.name || 'N/A' }}</TableCell>

                        <TableCell>{{ project.client?.name || 'N/A' }}</TableCell>

                        <!-- Editor Select -->
                        <TableCell>
                            <Select :modelValue="project.editor_id" @update:modelValue="(value) => updateProject(project.id, 'editor_id', value)">
                                <SelectTrigger class="w-[180px]">
                                    <SelectValue placeholder="Assign an editor" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="null">Unassigned</SelectItem>
                                    <SelectItem v-for="editor in editors" :key="editor.id" :value="editor.id">
                                        {{ editor.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </TableCell>

                        <!-- Status Select -->
                        <TableCell>
                            <Select :modelValue="project.status" @update:modelValue="(value) => updateProject(project.id, 'status', value)">
                                <SelectTrigger
                                    class="w-[180px]"
                                    :class="{
                                        'text-gray-500': project.status === 'todo' || project.status === 'backlog',
                                        'text-yellow-500': project.status === 'in_progress',
                                        'text-orange-500': project.status === 'for_qa',
                                        'text-blue-500': project.status === 'done_qa',
                                        'text-purple-500': project.status === 'sent_to_client',
                                        'text-red-500': project.status === 'revision',
                                        'text-green-500': project.status === 'revision_completed',
                                        'text-rose-700': project.status === 'cancelled',
                                    }"
                                >
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>

                                <SelectContent>
                                    <SelectItem
                                        v-for="(label, key) in statusLabels"
                                        :key="key"
                                        :value="key"
                                        :class="{
                                            'text-gray-500': key === 'todo' || key === 'backlog',
                                            'text-yellow-500': key === 'in_progress',
                                            'text-orange-500': key === 'for_qa',
                                            'text-blue-500': key === 'done_qa',
                                            'text-purple-500': key === 'sent_to_client',
                                            'text-red-500': key === 'revision',
                                            'text-green-500': key === 'revision_completed',
                                            'text-rose-700': key === 'cancelled',
                                        }"
                                    >
                                        {{ label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>

                            <div
                                v-if="getCountdown(project)"
                                class="mt-1 flex items-center gap-1 text-xs font-medium"
                                :class="getCountdownColor(project)"
                            >
                                <Clock class="size-3" />
                                {{ getCountdown(project) === 'overdue' ? 'Overdue' : `${getCountdown(project)} left` }}
                            </div>
                        </TableCell>

                        <!-- Priority Select -->
                        <TableCell>
                            <Select :modelValue="project.priority" @update:modelValue="(value) => updateProject(project.id, 'priority', value)">
                                <SelectTrigger
                                    class="w-[180px]"
                                    :class="{
                                        'font-semibold text-red-600': project.priority === 'urgent',
                                        'text-orange-500': project.priority === 'high',
                                        'text-blue-500': project.priority === 'normal',
                                        'text-gray-400': project.priority === 'low',
                                    }"
                                >
                                    <SelectValue placeholder="Select a priority" />
                                </SelectTrigger>

                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="urgent">Urgent</SelectItem>
                                        <SelectItem value="high">High</SelectItem>
                                        <SelectItem value="normal">Normal</SelectItem>
                                        <SelectItem value="low">Low</SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </TableCell>

                        <!-- Total Price -->
                        <TableCell>
                            <div class="relative">
                                <span class="absolute top-1/2 left-2 -translate-y-1/2 text-gray-500">$</span>
                                <Input
                                    type="number"
                                    step="0.01"
                                    class="w-28 pl-6"
                                    v-model.number="totalPrices[project.id]"
                                    @blur="updatePrice(project.id, totalPrices[project.id])"
                                    @keyup.enter="updatePrice(project.id, totalPrices[project.id])"
                                    :placeholder="String(project.total_price)"
                                />
                            </div>
                        </TableCell>

                        <TableCell>
                            <div class="relative">
                                <span class="absolute top-1/2 left-2 -translate-y-1/2 text-gray-500">₱</span>
                                <Input
                                    type="number"
                                    step="0.01"
                                    class="w-28 pl-6"
                                    v-model.number="editorPrices[project.id]"
                                    @keyup.enter="
                                        () => {
                                            updateProject(project.id, 'editor_price', editorPrices[project.id]);
                                        }
                                    "
                                />
                            </div>
                        </TableCell>

                        <TableCell>
                            {{
                                new Date(project.created_at).toLocaleString('en-US', {
                                    year: 'numeric',
                                    month: 'short',
                                    day: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit',
                                })
                            }}
                        </TableCell>

                        <TableCell>
                            <Button @click="openViewModal(project)">View Details</Button>
                            <Button variant="destructive" class="ml-2" @click="confirmDelete(project)"> Delete </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <!-- Pagination -->
            <div class="mt-4 flex items-center justify-center">
                <Pagination
                    v-slot="{ page }"
                    :items-per-page="projects.per_page"
                    :total="projects.total"
                    :default-page="projects.current_page"
                    @update:page="goToPage"
                >
                    <PaginationContent v-slot="{ items }">
                        <PaginationPrevious />
                        <template v-for="(item, index) in items" :key="index">
                            <PaginationItem v-if="item.type === 'page'" :value="item.value" :is-active="item.value === page">
                                {{ item.value }}
                            </PaginationItem>
                        </template>
                        <PaginationNext />
                    </PaginationContent>
                </Pagination>
            </div>
        </div>

        <ProjectViewModal
            v-if="selectedProject"
            :isOpen="showModal"
            :project="selectedProject"
            :role="pageProps.auth.user.role"
            @close="closeViewModal"
        />

        <!-- Export Preview Modal -->
        <Dialog :open="showPreviewModal" @update:open="showPreviewModal = $event">
            <DialogContent class="flex max-h-[80vh] flex-col sm:max-w-7xl">
                <DialogHeader>
                    <DialogTitle>Export Preview ({{ previewData.length }} records)</DialogTitle>
                </DialogHeader>
                <div class="min-h-0 flex-1 overflow-auto">
                    <table class="w-full table-fixed border-collapse text-sm">
                        <colgroup>
                            <col class="w-[160px]" />
                            <col class="w-[150px]" />
                            <col class="w-[160px]" />
                            <col class="w-[250px]" />
                            <col class="w-[100px]" />
                            <col class="w-[70px]" />
                            <col class="w-[90px]" />
                            <col class="w-[90px]" />
                            <col class="w-[100px]" />
                        </colgroup>
                        <thead>
                            <tr class="border-b">
                                <th class="p-2 text-left font-medium text-muted-foreground">Client</th>
                                <th class="p-2 text-left font-medium text-muted-foreground">Project Name</th>
                                <th class="p-2 text-left font-medium text-muted-foreground">Service</th>
                                <th class="p-2 text-left font-medium text-muted-foreground">Add Ons</th>
                                <th class="p-2 text-left font-medium text-muted-foreground">Editor</th>
                                <th class="p-2 text-left font-medium text-muted-foreground">Priority</th>
                                <th class="p-2 text-left font-medium text-muted-foreground">Total Price</th>
                                <th class="p-2 text-left font-medium text-muted-foreground">Editor Price</th>
                                <th class="p-2 text-left font-medium text-muted-foreground">Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in previewData" :key="index" class="border-b">
                                <td class="p-2">{{ row.client }}</td>
                                <td class="truncate p-2" :title="row.project_name">{{ row.project_name }}</td>
                                <td class="p-2">{{ row.service }}</td>
                                <td class="break-words p-2">{{ row.add_ons || '—' }}</td>
                                <td class="p-2">{{ row.editor }}</td>
                                <td class="p-2">{{ row.priority }}</td>
                                <td class="p-2">{{ row.total_price != null ? `$${row.total_price}` : '—' }}</td>
                                <td class="p-2">{{ row.editor_price != null ? row.editor_price : '—' }}</td>
                                <td class="p-2">{{ row.created_at.split(' ')[0] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex shrink-0 justify-end gap-2 pt-2">
                    <Button variant="outline" @click="showPreviewModal = false">Close</Button>
                    <a :href="exportUrl" target="_blank">
                        <Button>
                            <Download class="mr-2 size-4" />
                            Export to Excel
                        </Button>
                    </a>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Confirmation Delete Modal -->
        <Dialog :open="showDeleteModal" @update:open="showDeleteModal = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Delete Project</DialogTitle>
                    <p class="text-sm text-muted-foreground">
                        Are you sure you want to delete
                        <span class="font-semibold">{{ projectToDelete?.project_name }}</span
                        >? This action cannot be undone.
                    </p>
                </DialogHeader>
                <div class="mt-4 flex justify-end gap-2">
                    <Button variant="outline" @click="closeDeleteModal">Cancel</Button>
                    <Button variant="destructive" @click="deleteProject">Delete</Button>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

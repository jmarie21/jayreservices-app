<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { ServiceManagementCategoryRow, ServiceManagementServiceRow } from '@/types/services';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { FolderTree, Pencil, Plus, Trash2, Wrench } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import { toast, Toaster } from 'vue-sonner';

type TabType = 'categories' | 'services';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Services Management', href: '/admin-services' }];
const page = usePage<{ categories: ServiceManagementCategoryRow[]; services: ServiceManagementServiceRow[] }>();

const categories = computed(() => page.props.categories ?? []);
const services = computed(() => page.props.services ?? []);
const activeTab = ref<TabType>('categories');

const categoryDialogOpen = ref(false);
const editingCategorySlug = ref<string | null>(null);
const categoryForm = reactive({
    name: '',
    video_link: '',
    thumbnail_url: '',
    sort_order: 0,
    is_active: true,
});

const serviceDialogOpen = ref(false);
const editingServiceId = ref<number | null>(null);
const serviceForm = reactive({
    service_category_id: null as number | null,
    name: '',
    video_link: '',
    thumbnail_url: '',
    sort_order: 0,
    is_active: true,
});

const deleteDialogOpen = ref(false);
const deleteTarget = ref<{
    label: 'category' | 'service';
    name: string;
    routeName: string;
    routeParam: number | string;
} | null>(null);

function resetCategoryForm() {
    Object.assign(categoryForm, {
        name: '',
        video_link: '',
        thumbnail_url: '',
        sort_order: categories.value.length,
        is_active: true,
    });
}

function resetServiceForm() {
    Object.assign(serviceForm, {
        service_category_id: categories.value[0]?.id ?? null,
        name: '',
        video_link: '',
        thumbnail_url: '',
        sort_order: services.value.length,
        is_active: true,
    });
}

function openCategoryDialog(category?: ServiceManagementCategoryRow) {
    editingCategorySlug.value = category?.slug ?? null;
    Object.assign(categoryForm, {
        name: category?.name ?? '',
        video_link: category?.video_link ?? '',
        thumbnail_url: category?.thumbnail_url ?? '',
        sort_order: category?.sort_order ?? categories.value.length,
        is_active: category?.is_active ?? true,
    });
    categoryDialogOpen.value = true;
}

function openServiceDialog(service?: ServiceManagementServiceRow) {
    editingServiceId.value = service?.id ?? null;
    Object.assign(serviceForm, {
        service_category_id: service?.category?.id ?? categories.value[0]?.id ?? null,
        name: service?.name ?? '',
        video_link: service?.video_link ?? '',
        thumbnail_url: service?.thumbnail_url ?? '',
        sort_order: service?.sort_order ?? services.value.length,
        is_active: service?.is_active ?? true,
    });
    serviceDialogOpen.value = true;
}

function closeCategoryDialog() {
    categoryDialogOpen.value = false;
    editingCategorySlug.value = null;
    resetCategoryForm();
}

function closeServiceDialog() {
    serviceDialogOpen.value = false;
    editingServiceId.value = null;
    resetServiceForm();
}

function submitCategory() {
    const url = editingCategorySlug.value
        ? route('admin.services.categories.update', editingCategorySlug.value)
        : route('admin.services.categories.store');

    const payload = {
        name: categoryForm.name,
        video_link: categoryForm.video_link || null,
        thumbnail_url: categoryForm.thumbnail_url || null,
        sort_order: categoryForm.sort_order,
        is_active: categoryForm.is_active,
    };

    const onSuccess = () => {
        toast.success(`Category ${editingCategorySlug.value ? 'updated' : 'created'}.`);
        closeCategoryDialog();
    };

    if (editingCategorySlug.value) {
        router.put(url, payload, { preserveScroll: true, onSuccess });
        return;
    }

    router.post(url, payload, { preserveScroll: true, onSuccess });
}

function submitService() {
    const url = editingServiceId.value
        ? route('admin.services.update', editingServiceId.value)
        : route('admin.services.store');

    const payload = {
        service_category_id: serviceForm.service_category_id,
        name: serviceForm.name,
        video_link: serviceForm.video_link || null,
        thumbnail_url: serviceForm.thumbnail_url || null,
        sort_order: serviceForm.sort_order,
        is_active: serviceForm.is_active,
    };

    const onSuccess = () => {
        toast.success(`Service ${editingServiceId.value ? 'updated' : 'created'}.`);
        closeServiceDialog();
    };

    if (editingServiceId.value) {
        router.put(url, payload, { preserveScroll: true, onSuccess });
        return;
    }

    router.post(url, payload, { preserveScroll: true, onSuccess });
}

function openDeleteDialog(target: { label: 'category' | 'service'; name: string; routeName: string; routeParam: number | string }) {
    deleteTarget.value = target;
    deleteDialogOpen.value = true;
}

function closeDeleteDialog() {
    deleteDialogOpen.value = false;
    deleteTarget.value = null;
}

function confirmDelete() {
    if (!deleteTarget.value) return;

    const currentTarget = deleteTarget.value;

    router.delete(route(currentTarget.routeName, currentTarget.routeParam), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            toast.success(`${currentTarget.label === 'category' ? 'Category' : 'Service'} deleted.`);
            closeDeleteDialog();
        },
        onError: () => {
            toast.error(`Unable to delete this ${currentTarget.label}.`);
            closeDeleteDialog();
        },
    });
}

function tabButtonClass(tab: TabType) {
    return activeTab.value === tab
        ? 'border-b-2 border-indigo-600 text-indigo-600'
        : 'border-b-2 border-transparent text-slate-500 hover:text-slate-900';
}

resetCategoryForm();
resetServiceForm();
</script>

<template>
    <Head title="Services Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <Toaster />
        <div class="space-y-8 p-4">
            <div class="space-y-2">
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">Services Management</h1>
                <p class="max-w-4xl text-sm text-slate-500">
                    Start by creating categories, then create the orderable services inside them. Each service opens into a setup page
                    where you can manage bullet points, modal styles and video formats, plus add-on groups.
                </p>
            </div>

            <div class="border-b border-slate-200">
                <div class="flex flex-wrap gap-6">
                    <button type="button" class="flex items-center gap-2 pb-4 text-sm font-semibold uppercase tracking-[0.18em]" :class="tabButtonClass('categories')" @click="activeTab = 'categories'">
                        <FolderTree class="h-4 w-4" />
                        Categories
                    </button>
                    <button type="button" class="flex items-center gap-2 pb-4 text-sm font-semibold uppercase tracking-[0.18em]" :class="tabButtonClass('services')" @click="activeTab = 'services'">
                        <Wrench class="h-4 w-4" />
                        Orderable Services
                    </button>
                </div>
            </div>

            <div v-if="activeTab === 'categories'" class="space-y-6">
                <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div class="space-y-1">
                        <h2 class="text-2xl font-semibold text-slate-900">Service Categories</h2>
                        <p class="text-sm text-slate-500">Top-level groups like Real Estate Services or Wedding Services.</p>
                    </div>

                    <Button class="bg-indigo-600 hover:bg-indigo-700" @click="openCategoryDialog()">
                        <Plus class="mr-2 h-4 w-4" />
                        Add New Category
                    </Button>
                </div>

                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Category Name</TableHead>
                                <TableHead class="w-28">Sort Order</TableHead>
                                <TableHead class="w-28">Services</TableHead>
                                <TableHead class="w-32">Bullet Points</TableHead>
                                <TableHead class="w-28">Status</TableHead>
                                <TableHead class="w-32 text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="categories.length === 0">
                                <TableCell colspan="6" class="py-10 text-center text-sm text-slate-500">
                                    No categories yet. Create your first category to start building the services catalog.
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="category in categories" :key="category.id">
                                <TableCell class="font-medium text-slate-900">{{ category.name }}</TableCell>
                                <TableCell>{{ category.sort_order }}</TableCell>
                                <TableCell>{{ category.services_count }}</TableCell>
                                <TableCell>{{ category.bullet_points_count }}</TableCell>
                                <TableCell>
                                    <Badge :class="category.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'">
                                        {{ category.is_active ? 'Active' : 'Inactive' }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button variant="outline" size="icon" @click="openCategoryDialog(category)">
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            size="icon"
                                            @click="
                                                openDeleteDialog({
                                                    label: 'category',
                                                    name: category.name,
                                                    routeName: 'admin.services.categories.destroy',
                                                    routeParam: category.slug,
                                                })
                                            "
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>

            <div v-else class="space-y-6">
                <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div class="space-y-1">
                        <h2 class="text-2xl font-semibold text-slate-900">Orderable Services</h2>
                        <p class="text-sm text-slate-500">Services like Real Estate Basic Style that appear inside a category.</p>
                    </div>

                    <Button class="bg-indigo-600 hover:bg-indigo-700" :disabled="categories.length === 0" @click="openServiceDialog()">
                        <Plus class="mr-2 h-4 w-4" />
                        Add New Service
                    </Button>
                </div>

                <div v-if="categories.length === 0" class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-sm text-slate-500">
                    Create at least one category first. Services must belong to a category before they can be configured.
                </div>

                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Service Name</TableHead>
                                <TableHead>Category</TableHead>
                                <TableHead class="w-28">Sort Order</TableHead>
                                <TableHead class="w-24">Styles</TableHead>
                                <TableHead class="w-32">Add-On Groups</TableHead>
                                <TableHead class="w-28">Status</TableHead>
                                <TableHead class="w-32 text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="services.length === 0">
                                <TableCell colspan="7" class="py-10 text-center text-sm text-slate-500">
                                    No services yet. Create one, then open it to configure bullet points, styles, formats, and add-on groups.
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="service in services" :key="service.id">
                                <TableCell class="font-medium text-slate-900">{{ service.name }}</TableCell>
                                <TableCell>{{ service.category?.name || 'Uncategorized' }}</TableCell>
                                <TableCell>{{ service.sort_order }}</TableCell>
                                <TableCell>{{ service.styles_count }}</TableCell>
                                <TableCell>{{ service.addon_groups_count }}</TableCell>
                                <TableCell>
                                    <Badge :class="service.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'">
                                        {{ service.is_active ? 'Active' : 'Inactive' }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button as-child variant="outline" size="icon">
                                            <Link :href="route('admin.services.editor', service.id)">
                                                <Pencil class="h-4 w-4" />
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            size="icon"
                                            @click="
                                                openDeleteDialog({
                                                    label: 'service',
                                                    name: service.name,
                                                    routeName: 'admin.services.destroy',
                                                    routeParam: service.id,
                                                })
                                            "
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </div>

        <Dialog :open="categoryDialogOpen" @update:open="(open) => !open && closeCategoryDialog()">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{ editingCategorySlug ? 'Edit Category' : 'New Category' }}</DialogTitle>
                </DialogHeader>

                <form class="space-y-5" @submit.prevent="submitCategory">
                    <div class="space-y-2">
                        <Label>Category Name</Label>
                        <Input v-model="categoryForm.name" placeholder="e.g. Real Estate Services" />
                    </div>

                    <div class="space-y-2">
                        <Label>Video URL (optional)</Label>
                        <Input v-model="categoryForm.video_link" placeholder="https://..." />
                    </div>

                    <div class="space-y-2">
                        <Label>Thumbnail URL (optional)</Label>
                        <Input v-model="categoryForm.thumbnail_url" placeholder="https://..." />
                    </div>

                    <div class="space-y-2">
                        <Label>Sort Order</Label>
                        <Input v-model.number="categoryForm.sort_order" type="number" min="0" />
                    </div>

                    <div class="flex items-center gap-3">
                        <Checkbox :model-value="categoryForm.is_active" @update:model-value="(value) => (categoryForm.is_active = value === true)" />
                        <Label>Active</Label>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Button type="button" variant="outline" @click="closeCategoryDialog">Cancel</Button>
                        <Button type="submit" class="bg-indigo-600 hover:bg-indigo-700">
                            {{ editingCategorySlug ? 'Save Info' : 'Save Info' }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="serviceDialogOpen" @update:open="(open) => !open && closeServiceDialog()">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{ editingServiceId ? 'Edit Service' : 'Add New Service' }}</DialogTitle>
                </DialogHeader>

                <form class="space-y-5" @submit.prevent="submitService">
                    <div class="space-y-2">
                        <Label>Category</Label>
                        <Select v-model="serviceForm.service_category_id">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select a category" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="category in categories" :key="category.id" :value="category.id">
                                    {{ category.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-slate-500">Active services must belong to a category before they appear on the Services page.</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Service Name</Label>
                        <Input v-model="serviceForm.name" placeholder="e.g. Real Estate Basic Style" />
                    </div>

                    <div class="space-y-2">
                        <Label>Video URL (optional)</Label>
                        <Input v-model="serviceForm.video_link" placeholder="https://..." />
                    </div>

                    <div class="space-y-2">
                        <Label>Thumbnail URL (optional)</Label>
                        <Input v-model="serviceForm.thumbnail_url" placeholder="https://..." />
                    </div>

                    <div class="space-y-2">
                        <Label>Sort Order</Label>
                        <Input v-model.number="serviceForm.sort_order" type="number" min="0" />
                    </div>

                    <div class="flex items-center gap-3">
                        <Checkbox :model-value="serviceForm.is_active" @update:model-value="(value) => (serviceForm.is_active = value === true)" />
                        <Label>Active</Label>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Button type="button" variant="outline" @click="closeServiceDialog">Cancel</Button>
                        <Button type="submit" class="bg-indigo-600 hover:bg-indigo-700">
                            {{ editingServiceId ? 'Save Service' : 'Create Service' }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <AlertDialog v-model:open="deleteDialogOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete {{ deleteTarget?.label === 'category' ? 'Category' : 'Service' }}</AlertDialogTitle>
                    <AlertDialogDescription>
                        Delete <strong>{{ deleteTarget?.name }}</strong
                        >? This action cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="closeDeleteDialog">Cancel</AlertDialogCancel>
                    <AlertDialogAction class="bg-red-600 text-white hover:bg-red-700" @click="confirmDelete">
                        Delete
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>

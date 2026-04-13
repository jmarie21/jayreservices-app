<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type {
    ServiceAddonGroup,
    ServiceAddonGroupOption,
    ServiceEditorCategoryOption,
    ServiceEditorData,
    ServiceFormatPrice,
    ServiceSubStyle,
} from '@/types/services';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, CircleHelp, List, MonitorPlay, Pencil, Plus, SlidersHorizontal, Trash2 } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import { toast, Toaster } from 'vue-sonner';

type TabType = 'general' | 'bullets' | 'styles' | 'addons';
type AddonInputType = 'dropdown' | 'checkbox_group';

const page = usePage<{ service: ServiceEditorData; categories: ServiceEditorCategoryOption[] }>();

const service = computed(() => page.props.service);
const categories = computed(() => page.props.categories ?? []);
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Services Management', href: '/admin-services' },
    { title: service.value.name, href: route('admin.services.editor', service.value.id) },
]);

const activeTab = ref<TabType>('general');

const generalForm = reactive({
    service_category_id: '',
    name: '',
    video_link: '',
    thumbnail_url: '',
    sort_order: 0,
    is_active: true,
    features: [] as string[],
});

function syncGeneralForm() {
    Object.assign(generalForm, {
        service_category_id: service.value.category?.id ? String(service.value.category.id) : categories.value[0]?.id ? String(categories.value[0].id) : '',
        name: service.value.name ?? '',
        video_link: service.value.video_link ?? '',
        thumbnail_url: service.value.thumbnail_url ?? '',
        sort_order: service.value.sort_order ?? 0,
        is_active: service.value.is_active ?? true,
        features: [...(service.value.features ?? [])],
    });
}

syncGeneralForm();

const bulletDialogOpen = ref(false);
const editingBulletIndex = ref<number | null>(null);
const bulletForm = reactive({
    text: '',
    sort_order: 0,
});

const styleDialogOpen = ref(false);
const editingStyleId = ref<number | null>(null);
const styleForm = reactive({
    name: '',
    sort_order: 0,
    is_active: true,
});

const formatDialogOpen = ref(false);
const editingFormatId = ref<number | null>(null);
const currentSubStyleId = ref<number | null>(null);
const formatForm = reactive({
    format_name: '',
    client_price: 0,
    editor_price: 0,
    sort_order: 0,
});

const addonGroupDialogOpen = ref(false);
const editingAddonGroupId = ref<number | null>(null);
const addonGroupForm = reactive({
    label: '',
    input_type: 'dropdown' as AddonInputType,
    helper_text: '',
    sort_order: 0,
    is_required: false,
    is_active: true,
});

const addonOptionDialogOpen = ref(false);
const editingAddonOptionId = ref<number | null>(null);
const currentAddonGroupId = ref<number | null>(null);
const addonOptionForm = reactive({
    name: '',
    client_price: 0,
    editor_price: 0,
    sample_link: '',
    sort_order: 0,
    has_quantity: false,
    is_rush_option: false,
    is_active: true,
});

const featureRows = computed(() =>
    generalForm.features.map((feature, index) => ({
        label: feature,
        sort_order: index * 10,
        index,
    })),
);

const customerMoneyFormatter = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
});

const editorMoneyFormatter = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
});

function tabButtonClass(tab: TabType) {
    return activeTab.value === tab
        ? 'border-b-2 border-indigo-600 text-indigo-600'
        : 'border-b-2 border-transparent text-slate-500 hover:text-slate-900';
}

function formatCustomerMoney(value?: number) {
    return customerMoneyFormatter.format(Number(value ?? 0));
}

function formatEditorMoney(value?: number) {
    return editorMoneyFormatter.format(Number(value ?? 0));
}

function formatInputTypeLabel(value: AddonInputType) {
    return value === 'dropdown' ? 'Dropdown' : 'Checkbox Group';
}

function serviceUpdatePayload(overrides: Record<string, unknown> = {}) {
    return {
        service_category_id: generalForm.service_category_id ? Number(generalForm.service_category_id) : null,
        name: generalForm.name,
        video_link: generalForm.video_link || null,
        thumbnail_url: generalForm.thumbnail_url || null,
        sort_order: Number(generalForm.sort_order ?? 0),
        is_active: generalForm.is_active,
        ...overrides,
    };
}

function submitGeneralInfo() {
    if (!generalForm.service_category_id) {
        toast.error('Create or select a category before saving this service.');
        return;
    }

    router.put(route('admin.services.update', service.value.id), serviceUpdatePayload(), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => toast.success('Service info saved.'),
        onError: () => toast.error('Unable to save the service info.'),
    });
}

function saveFeatures(nextFeatures: string[], successMessage: string, afterSave?: () => void) {
    router.put(
        route('admin.services.update', service.value.id),
        serviceUpdatePayload({ features: nextFeatures }),
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                generalForm.features = [...nextFeatures];
                toast.success(successMessage);
                afterSave?.();
            },
            onError: () => toast.error('Unable to update bullet points.'),
        },
    );
}

function openBulletDialog(index?: number) {
    editingBulletIndex.value = typeof index === 'number' ? index : null;
    bulletForm.text = typeof index === 'number' ? generalForm.features[index] ?? '' : '';
    bulletForm.sort_order = typeof index === 'number' ? index * 10 : generalForm.features.length * 10;
    bulletDialogOpen.value = true;
}

function closeBulletDialog() {
    bulletDialogOpen.value = false;
    editingBulletIndex.value = null;
    bulletForm.text = '';
    bulletForm.sort_order = generalForm.features.length * 10;
}

function submitBullet() {
    const trimmedText = bulletForm.text.trim();

    if (!trimmedText) {
        toast.error('Enter a bullet point first.');
        return;
    }

    const remainingEntries = generalForm.features
        .map((feature, index) => ({
            text: feature,
            order: index * 10,
            index,
        }))
        .filter((entry) => entry.index !== editingBulletIndex.value);

    const nextFeatures = [...remainingEntries, { text: trimmedText, order: Number(bulletForm.sort_order ?? 0), index: 999999 }]
        .sort((left, right) => left.order - right.order || left.index - right.index)
        .map((entry) => entry.text);

    saveFeatures(nextFeatures, editingBulletIndex.value === null ? 'Bullet point added.' : 'Bullet point updated.', closeBulletDialog);
}

function deleteBullet(index: number) {
    if (!window.confirm('Delete this bullet point?')) {
        return;
    }

    saveFeatures(
        generalForm.features.filter((_, featureIndex) => featureIndex !== index),
        'Bullet point removed.',
    );
}

function openStyleDialog(subStyle?: ServiceSubStyle) {
    editingStyleId.value = subStyle?.id ?? null;
    styleForm.name = subStyle?.name ?? '';
    styleForm.sort_order = subStyle?.sort_order ?? service.value.sub_styles.length * 10;
    styleForm.is_active = subStyle?.is_active ?? true;
    styleDialogOpen.value = true;
}

function closeStyleDialog() {
    styleDialogOpen.value = false;
    editingStyleId.value = null;
    styleForm.name = '';
    styleForm.sort_order = service.value.sub_styles.length * 10;
    styleForm.is_active = true;
}

function submitStyle() {
    const payload = {
        service_id: service.value.id,
        name: styleForm.name,
        sort_order: Number(styleForm.sort_order ?? 0),
        is_active: styleForm.is_active,
    };

    const onSuccess = () => {
        toast.success(editingStyleId.value ? 'Style updated.' : 'Style added.');
        closeStyleDialog();
    };

    const options = {
        preserveScroll: true,
        preserveState: true,
        onSuccess,
        onError: () => toast.error('Unable to save this style.'),
    };

    if (editingStyleId.value) {
        router.put(route('admin.services.sub-styles.update', editingStyleId.value), payload, options);
        return;
    }

    router.post(route('admin.services.sub-styles.store'), payload, options);
}

function deleteStyle(subStyle: ServiceSubStyle) {
    if (!window.confirm(`Delete "${subStyle.name}"?`)) {
        return;
    }

    router.delete(route('admin.services.sub-styles.destroy', subStyle.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => toast.success('Style removed.'),
        onError: () => toast.error('Unable to delete this style.'),
    });
}

function openFormatDialog(subStyle: ServiceSubStyle, pricing?: ServiceFormatPrice) {
    currentSubStyleId.value = subStyle.id;
    editingFormatId.value = pricing?.id ?? null;
    formatForm.format_name = pricing?.format_label ?? pricing?.format_name ?? '';
    formatForm.client_price = pricing?.client_price ?? 0;
    formatForm.editor_price = pricing?.editor_price ?? 0;
    formatForm.sort_order = pricing?.sort_order ?? subStyle.format_pricing.length * 10;
    formatDialogOpen.value = true;
}

function closeFormatDialog() {
    formatDialogOpen.value = false;
    editingFormatId.value = null;
    currentSubStyleId.value = null;
    formatForm.format_name = '';
    formatForm.client_price = 0;
    formatForm.editor_price = 0;
    formatForm.sort_order = 0;
}

function submitFormat() {
    if (!currentSubStyleId.value) {
        toast.error('Select a style before adding a format.');
        return;
    }

    const trimmedName = formatForm.format_name.trim();

    if (!trimmedName) {
        toast.error('Enter a format name first.');
        return;
    }

    const payload = {
        service_sub_style_id: currentSubStyleId.value,
        format_name: trimmedName,
        format_label: trimmedName,
        client_price: Number(formatForm.client_price ?? 0),
        editor_price: Number(formatForm.editor_price ?? 0),
        sort_order: Number(formatForm.sort_order ?? 0),
    };

    const onSuccess = () => {
        toast.success(editingFormatId.value ? 'Format updated.' : 'Format added.');
        closeFormatDialog();
    };

    const options = {
        preserveScroll: true,
        preserveState: true,
        onSuccess,
        onError: () => toast.error('Unable to save this video format.'),
    };

    if (editingFormatId.value) {
        router.put(route('admin.services.format-pricing.update', editingFormatId.value), payload, options);
        return;
    }

    router.post(route('admin.services.format-pricing.store'), payload, options);
}

function deleteFormat(pricing: ServiceFormatPrice) {
    if (!window.confirm(`Delete "${pricing.format_label}"?`)) {
        return;
    }

    router.delete(route('admin.services.format-pricing.destroy', pricing.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => toast.success('Format removed.'),
        onError: () => toast.error('Unable to delete this video format.'),
    });
}

function openAddonGroupDialog(group?: ServiceAddonGroup) {
    editingAddonGroupId.value = group?.id ?? null;
    addonGroupForm.label = group?.label ?? '';
    addonGroupForm.input_type = group?.input_type ?? 'dropdown';
    addonGroupForm.helper_text = group?.helper_text ?? '';
    addonGroupForm.sort_order = group?.sort_order ?? service.value.addon_groups.length * 10;
    addonGroupForm.is_required = group?.is_required ?? false;
    addonGroupForm.is_active = group?.is_active ?? true;
    addonGroupDialogOpen.value = true;
}

function closeAddonGroupDialog() {
    addonGroupDialogOpen.value = false;
    editingAddonGroupId.value = null;
    addonGroupForm.label = '';
    addonGroupForm.input_type = 'dropdown';
    addonGroupForm.helper_text = '';
    addonGroupForm.sort_order = service.value.addon_groups.length * 10;
    addonGroupForm.is_required = false;
    addonGroupForm.is_active = true;
}

function submitAddonGroup() {
    const payload = {
        service_id: service.value.id,
        label: addonGroupForm.label,
        input_type: addonGroupForm.input_type,
        helper_text: addonGroupForm.helper_text || null,
        sort_order: Number(addonGroupForm.sort_order ?? 0),
        is_required: addonGroupForm.is_required,
        is_active: addonGroupForm.is_active,
    };

    const onSuccess = () => {
        toast.success(editingAddonGroupId.value ? 'Add-on group updated.' : 'Add-on group added.');
        closeAddonGroupDialog();
    };

    const options = {
        preserveScroll: true,
        preserveState: true,
        onSuccess,
        onError: () => toast.error('Unable to save this add-on group.'),
    };

    if (editingAddonGroupId.value) {
        router.put(route('admin.services.addon-groups.update', editingAddonGroupId.value), payload, options);
        return;
    }

    router.post(route('admin.services.addon-groups.store'), payload, options);
}

function deleteAddonGroup(group: ServiceAddonGroup) {
    if (!window.confirm(`Delete "${group.label}" and all of its options?`)) {
        return;
    }

    router.delete(route('admin.services.addon-groups.destroy', group.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => toast.success('Add-on group removed.'),
        onError: () => toast.error('Unable to delete this add-on group.'),
    });
}

function openAddonOptionDialog(group: ServiceAddonGroup, option?: ServiceAddonGroupOption) {
    currentAddonGroupId.value = group.id;
    editingAddonOptionId.value = option?.id ?? null;
    addonOptionForm.name = option?.name ?? '';
    addonOptionForm.client_price = option?.client_price ?? 0;
    addonOptionForm.editor_price = option?.editor_price ?? 0;
    addonOptionForm.sample_link = option?.sample_link ?? '';
    addonOptionForm.sort_order = option?.sort_order ?? group.options.length * 10;
    addonOptionForm.has_quantity = option?.has_quantity ?? false;
    addonOptionForm.is_rush_option = option?.is_rush_option ?? false;
    addonOptionForm.is_active = option?.is_active ?? true;
    addonOptionDialogOpen.value = true;
}

function closeAddonOptionDialog() {
    addonOptionDialogOpen.value = false;
    editingAddonOptionId.value = null;
    currentAddonGroupId.value = null;
    addonOptionForm.name = '';
    addonOptionForm.client_price = 0;
    addonOptionForm.editor_price = 0;
    addonOptionForm.sample_link = '';
    addonOptionForm.sort_order = 0;
    addonOptionForm.has_quantity = false;
    addonOptionForm.is_rush_option = false;
    addonOptionForm.is_active = true;
}

function submitAddonOption() {
    if (!currentAddonGroupId.value) {
        toast.error('Select an add-on group before adding an option.');
        return;
    }

    const payload = {
        service_addon_group_id: currentAddonGroupId.value,
        name: addonOptionForm.name,
        addon_type: addonOptionForm.has_quantity ? 'quantity' : 'boolean',
        client_price: Number(addonOptionForm.client_price ?? 0),
        editor_price: Number(addonOptionForm.editor_price ?? 0),
        has_quantity: addonOptionForm.has_quantity,
        is_rush_option: addonOptionForm.is_rush_option,
        sample_link: addonOptionForm.sample_link || null,
        sort_order: Number(addonOptionForm.sort_order ?? 0),
        is_active: addonOptionForm.is_active,
    };

    const onSuccess = () => {
        toast.success(editingAddonOptionId.value ? 'Add-on option updated.' : 'Add-on option added.');
        closeAddonOptionDialog();
    };

    const options = {
        preserveScroll: true,
        preserveState: true,
        onSuccess,
        onError: () => toast.error('Unable to save this add-on option.'),
    };

    if (editingAddonOptionId.value) {
        router.put(route('admin.services.addons.update', editingAddonOptionId.value), payload, options);
        return;
    }

    router.post(route('admin.services.addons.store'), payload, options);
}

function deleteAddonOption(option: ServiceAddonGroupOption) {
    if (!window.confirm(`Delete "${option.name}"?`)) {
        return;
    }

    router.delete(route('admin.services.addons.destroy', option.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => toast.success('Add-on option removed.'),
        onError: () => toast.error('Unable to delete this add-on option.'),
    });
}
</script>

<template>
    <Head :title="`${service.name} | Services Management`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <Toaster />

        <div class="space-y-8 p-4">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-3">
                    <Button as-child variant="outline" class="w-fit">
                        <Link :href="route('admin.services.management')">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back to Services Management
                        </Link>
                    </Button>

                    <div class="space-y-2">
                        <h1 class="text-3xl font-bold tracking-tight text-slate-900">{{ service.name }}</h1>
                        <p class="text-sm text-slate-500">
                            Configure this service in the same flow as your screenshots: general info first, then bullet points, modal
                            styles and video formats, and finally the add-on groups.
                        </p>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm">
                    <div class="font-medium text-slate-900">{{ service.category?.name || 'No category assigned yet' }}</div>
                    <div class="mt-1 text-slate-500">Slug: {{ service.slug }}</div>
                </div>
            </div>

            <div class="border-b border-slate-200">
                <div class="flex flex-wrap gap-6">
                    <button type="button" class="flex items-center gap-2 pb-4 text-sm font-semibold uppercase tracking-[0.18em]" :class="tabButtonClass('general')" @click="activeTab = 'general'">
                        <CircleHelp class="h-4 w-4" />
                        General Info
                    </button>
                    <button type="button" class="flex items-center gap-2 pb-4 text-sm font-semibold uppercase tracking-[0.18em]" :class="tabButtonClass('bullets')" @click="activeTab = 'bullets'">
                        <List class="h-4 w-4" />
                        Bullet Points
                    </button>
                    <button type="button" class="flex items-center gap-2 pb-4 text-sm font-semibold uppercase tracking-[0.18em]" :class="tabButtonClass('styles')" @click="activeTab = 'styles'">
                        <MonitorPlay class="h-4 w-4" />
                        Modal Styles &amp; Video Formats
                    </button>
                    <button type="button" class="flex items-center gap-2 pb-4 text-sm font-semibold uppercase tracking-[0.18em]" :class="tabButtonClass('addons')" @click="activeTab = 'addons'">
                        <SlidersHorizontal class="h-4 w-4" />
                        Add-On Groups
                    </button>
                </div>
            </div>

            <div v-if="activeTab === 'general'" class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(280px,360px)]">
                <div class="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="space-y-1">
                        <h2 class="text-2xl font-semibold text-slate-900">General Info</h2>
                        <p class="text-sm text-slate-500">These fields control where the service appears and the media shown on the services page.</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Category</Label>
                        <Select v-model="generalForm.service_category_id">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select a category" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="category in categories" :key="category.id" :value="String(category.id)">
                                    {{ category.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>Service Name</Label>
                        <Input v-model="generalForm.name" placeholder="e.g. Real Estate Basic Style" />
                    </div>

                    <div class="space-y-2">
                        <Label>Video URL (optional)</Label>
                        <Input v-model="generalForm.video_link" placeholder="https://..." />
                    </div>

                    <div class="space-y-2">
                        <Label>Thumbnail URL (optional)</Label>
                        <Input v-model="generalForm.thumbnail_url" placeholder="https://..." />
                    </div>

                    <div class="space-y-2">
                        <Label>Sort Order</Label>
                        <Input v-model.number="generalForm.sort_order" type="number" min="0" />
                    </div>

                    <div class="flex items-center gap-3">
                        <Checkbox :model-value="generalForm.is_active" @update:model-value="(value) => (generalForm.is_active = value === true)" />
                        <Label>Active</Label>
                    </div>

                    <div class="flex justify-end">
                        <Button class="bg-indigo-600 hover:bg-indigo-700" :disabled="categories.length === 0" @click="submitGeneralInfo">
                            Save Info
                        </Button>
                    </div>
                </div>

                <div class="rounded-2xl border border-indigo-100 bg-indigo-50/70 p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-900">Setup Flow</h3>
                    <div class="mt-4 space-y-3 text-sm text-slate-600">
                        <p>1. Save the core service info here first so the service is attached to the correct category.</p>
                        <p>2. Add feature bullet points to shape how the service is presented on the catalog pages.</p>
                        <p>3. Create modal styles and video format pricing so the order form has the correct dropdown flow.</p>
                        <p>4. Finish by creating add-on groups and options, including editor pricing and rush flags if needed.</p>
                    </div>

                    <div v-if="categories.length === 0" class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
                        No categories exist yet. Create one on the main Services Management page, then come back here to finish setup.
                    </div>
                </div>
            </div>

            <div v-else-if="activeTab === 'bullets'" class="space-y-6">
                <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div class="space-y-1">
                        <h2 class="text-2xl font-semibold text-slate-900">Feature Bullet Points</h2>
                        <p class="text-sm text-slate-500">These are the short feature lines shown alongside the service details.</p>
                    </div>

                    <Button class="bg-indigo-600 hover:bg-indigo-700" @click="openBulletDialog()">
                        <Plus class="mr-2 h-4 w-4" />
                        Add Bullet Point
                    </Button>
                </div>

                <div class="space-y-3">
                    <div v-if="featureRows.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500">
                        No bullet points yet. Add the selling points for this service here.
                    </div>

                    <div v-for="feature in featureRows" :key="`${feature.index}-${feature.label}`" class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:flex-row md:items-center md:justify-between">
                        <div class="space-y-1">
                            <div class="text-base font-medium text-slate-900">{{ feature.label }}</div>
                            <div class="text-xs uppercase tracking-[0.18em] text-slate-400">Order: {{ feature.sort_order }}</div>
                        </div>

                        <div class="flex gap-2 md:justify-end">
                            <Button variant="outline" size="icon" @click="openBulletDialog(feature.index)">
                                <Pencil class="h-4 w-4" />
                            </Button>
                            <Button variant="destructive" size="icon" @click="deleteBullet(feature.index)">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="activeTab === 'styles'" class="space-y-6">
                <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div class="space-y-1">
                        <h2 class="text-2xl font-semibold text-slate-900">Order Modal Styles</h2>
                        <p class="text-sm text-slate-500">These drive the style dropdown first, then the video format pricing inside each style.</p>
                    </div>

                    <Button class="bg-indigo-600 hover:bg-indigo-700" @click="openStyleDialog()">
                        <Plus class="mr-2 h-4 w-4" />
                        Add Style
                    </Button>
                </div>

                <div v-if="service.sub_styles.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500">
                    No styles yet. Create the first order modal style for this service.
                </div>

                <div v-for="subStyle in service.sub_styles" :key="subStyle.id" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-3">
                                <h3 class="text-xl font-semibold text-slate-900">{{ subStyle.name }}</h3>
                                <Badge class="bg-slate-100 text-slate-700">Order: {{ subStyle.sort_order }}</Badge>
                                <Badge :class="subStyle.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'">
                                    {{ subStyle.is_active ? 'Active' : 'Inactive' }}
                                </Badge>
                            </div>
                            <p class="text-sm text-slate-500">Video format options</p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Button variant="outline" @click="openFormatDialog(subStyle)">
                                <Plus class="mr-2 h-4 w-4" />
                                Add Format
                            </Button>
                            <Button variant="outline" size="icon" @click="openStyleDialog(subStyle)">
                                <Pencil class="h-4 w-4" />
                            </Button>
                            <Button variant="destructive" size="icon" @click="deleteStyle(subStyle)">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <div v-if="subStyle.format_pricing.length === 0" class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-500">
                        No video format options yet for this style.
                    </div>

                    <div v-else class="space-y-3">
                        <div v-for="pricing in subStyle.format_pricing" :key="pricing.id" class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 md:flex-row md:items-center md:justify-between">
                            <div class="space-y-1">
                                <div class="font-medium text-slate-900">{{ pricing.format_label }}</div>
                                <div class="text-sm text-slate-500">Order: {{ pricing.sort_order }}</div>
                            </div>

                            <div class="flex flex-col gap-3 md:flex-row md:items-center md:gap-6">
                                <div class="text-sm font-medium text-emerald-600">Customer: {{ formatCustomerMoney(pricing.client_price) }}</div>
                                <div class="text-sm font-medium text-slate-600">Editor: {{ formatEditorMoney(pricing.editor_price) }}</div>
                                <div class="flex gap-2 md:justify-end">
                                    <Button variant="outline" size="icon" @click="openFormatDialog(subStyle, pricing)">
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button variant="destructive" size="icon" @click="deleteFormat(pricing)">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="space-y-6">
                <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div class="space-y-1">
                        <h2 class="text-2xl font-semibold text-slate-900">Add-On Groups</h2>
                        <p class="text-sm text-slate-500">Create dropdown or checkbox groups, then add the options with client and editor pricing.</p>
                    </div>

                    <Button class="bg-indigo-600 hover:bg-indigo-700" @click="openAddonGroupDialog()">
                        <Plus class="mr-2 h-4 w-4" />
                        Add Group
                    </Button>
                </div>

                <div v-if="service.addon_groups.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500">
                    No add-on groups yet. Start by creating a dropdown or checkbox group for this service.
                </div>

                <div v-for="group in service.addon_groups" :key="group.id" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-3">
                                <h3 class="text-xl font-semibold text-slate-900">{{ group.label }}</h3>
                                <Badge class="bg-indigo-100 text-indigo-700">{{ formatInputTypeLabel(group.input_type) }}</Badge>
                                <Badge class="bg-slate-100 text-slate-700">Order: {{ group.sort_order }}</Badge>
                                <Badge v-if="group.is_required" class="bg-amber-100 text-amber-700">Required</Badge>
                                <Badge :class="group.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'">
                                    {{ group.is_active ? 'Active' : 'Inactive' }}
                                </Badge>
                            </div>
                            <p v-if="group.helper_text" class="text-sm text-slate-500">{{ group.helper_text }}</p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Button variant="outline" @click="openAddonOptionDialog(group)">
                                <Plus class="mr-2 h-4 w-4" />
                                Add Option
                            </Button>
                            <Button variant="outline" size="icon" @click="openAddonGroupDialog(group)">
                                <Pencil class="h-4 w-4" />
                            </Button>
                            <Button variant="destructive" size="icon" @click="deleteAddonGroup(group)">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <div v-if="group.options.length === 0" class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-500">
                        No options yet in this group.
                    </div>

                    <div v-else class="space-y-3">
                        <div v-for="option in group.options" :key="option.id" class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 md:flex-row md:items-center md:justify-between">
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-3">
                                    <div class="font-medium text-slate-900">{{ option.name }}</div>
                                    <Badge class="bg-slate-100 text-slate-700">Order: {{ option.sort_order }}</Badge>
                                    <Badge v-if="option.has_quantity" class="bg-sky-100 text-sky-700">Quantity</Badge>
                                    <Badge v-if="option.is_rush_option" class="bg-rose-100 text-rose-700">Rush</Badge>
                                    <Badge :class="option.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'">
                                        {{ option.is_active ? 'Active' : 'Inactive' }}
                                    </Badge>
                                </div>

                                <div class="flex flex-wrap gap-4 text-sm">
                                    <span class="font-medium text-emerald-600">Customer: {{ formatCustomerMoney(option.client_price) }}</span>
                                    <span class="font-medium text-slate-600">Editor: {{ formatEditorMoney(option.editor_price) }}</span>
                                </div>

                                <a v-if="option.sample_link" :href="option.sample_link" target="_blank" rel="noreferrer" class="inline-flex text-sm font-medium text-indigo-600 hover:text-indigo-700">
                                    View sample link
                                </a>
                            </div>

                            <div class="flex gap-2 md:justify-end">
                                <Button variant="outline" size="icon" @click="openAddonOptionDialog(group, option)">
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button variant="destructive" size="icon" @click="deleteAddonOption(option)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Dialog :open="bulletDialogOpen" @update:open="(open) => !open && closeBulletDialog()">
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{ editingBulletIndex === null ? 'Add Bullet Point' : 'Edit Bullet Point' }}</DialogTitle>
                </DialogHeader>

                <form class="space-y-5" @submit.prevent="submitBullet">
                    <div class="space-y-2">
                        <Label>Bullet Point</Label>
                        <Textarea v-model="bulletForm.text" class="min-h-[120px]" placeholder="e.g. Retiming the clip with beat of the music" />
                    </div>

                    <div class="space-y-2">
                        <Label>Sort Order</Label>
                        <Input v-model.number="bulletForm.sort_order" type="number" min="0" />
                    </div>

                    <div class="flex justify-end gap-3">
                        <Button type="button" variant="outline" @click="closeBulletDialog">Cancel</Button>
                        <Button type="submit" class="bg-indigo-600 hover:bg-indigo-700">
                            {{ editingBulletIndex === null ? 'Add Bullet Point' : 'Save Bullet Point' }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="styleDialogOpen" @update:open="(open) => !open && closeStyleDialog()">
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{ editingStyleId ? 'Edit Style' : 'Add Style' }}</DialogTitle>
                </DialogHeader>

                <form class="space-y-5" @submit.prevent="submitStyle">
                    <div class="space-y-2">
                        <Label>Style Name</Label>
                        <Input v-model="styleForm.name" placeholder="e.g. Basic Video" />
                    </div>

                    <div class="space-y-2">
                        <Label>Sort Order</Label>
                        <Input v-model.number="styleForm.sort_order" type="number" min="0" />
                    </div>

                    <div class="flex items-center gap-3">
                        <Checkbox :model-value="styleForm.is_active" @update:model-value="(value) => (styleForm.is_active = value === true)" />
                        <Label>Active</Label>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Button type="button" variant="outline" @click="closeStyleDialog">Cancel</Button>
                        <Button type="submit" class="bg-indigo-600 hover:bg-indigo-700">
                            {{ editingStyleId ? 'Save Style' : 'Add Style' }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="formatDialogOpen" @update:open="(open) => !open && closeFormatDialog()">
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{ editingFormatId ? 'Edit Video Format Option' : 'Add Video Format Option' }}</DialogTitle>
                </DialogHeader>

                <form class="space-y-5" @submit.prevent="submitFormat">
                    <div class="space-y-2">
                        <Label>Format Name</Label>
                        <Input v-model="formatForm.format_name" placeholder="e.g. Horizontal" />
                    </div>

                    <div class="space-y-2">
                        <Label>Price ($)</Label>
                        <Input v-model.number="formatForm.client_price" type="number" min="0" step="0.01" />
                    </div>

                    <div class="space-y-2">
                        <Label>Editor Price (₱)</Label>
                        <Input v-model.number="formatForm.editor_price" type="number" min="0" step="0.01" />
                    </div>

                    <div class="space-y-2">
                        <Label>Sort Order</Label>
                        <Input v-model.number="formatForm.sort_order" type="number" min="0" />
                    </div>

                    <div class="flex justify-end gap-3">
                        <Button type="button" variant="outline" @click="closeFormatDialog">Cancel</Button>
                        <Button type="submit" class="bg-indigo-600 hover:bg-indigo-700">
                            {{ editingFormatId ? 'Save Format' : 'Add Format' }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="addonGroupDialogOpen" @update:open="(open) => !open && closeAddonGroupDialog()">
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{ editingAddonGroupId ? 'Edit Add-On Group' : 'Add Add-On Group' }}</DialogTitle>
                </DialogHeader>

                <form class="space-y-5" @submit.prevent="submitAddonGroup">
                    <div class="space-y-2">
                        <Label>Label</Label>
                        <Input v-model="addonGroupForm.label" placeholder="e.g. With agent or voiceover?" />
                    </div>

                    <div class="space-y-2">
                        <Label>Input Type</Label>
                        <Select v-model="addonGroupForm.input_type">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select an input type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="dropdown">Dropdown</SelectItem>
                                <SelectItem value="checkbox_group">Checkbox Group</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>Helper Text (optional)</Label>
                        <Textarea v-model="addonGroupForm.helper_text" class="min-h-[100px]" placeholder="Optional description shown above this group" />
                    </div>

                    <div class="space-y-2">
                        <Label>Sort Order</Label>
                        <Input v-model.number="addonGroupForm.sort_order" type="number" min="0" />
                    </div>

                    <div class="flex items-center gap-3">
                        <Checkbox :model-value="addonGroupForm.is_required" @update:model-value="(value) => (addonGroupForm.is_required = value === true)" />
                        <Label>Required</Label>
                    </div>

                    <div class="flex items-center gap-3">
                        <Checkbox :model-value="addonGroupForm.is_active" @update:model-value="(value) => (addonGroupForm.is_active = value === true)" />
                        <Label>Active</Label>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Button type="button" variant="outline" @click="closeAddonGroupDialog">Cancel</Button>
                        <Button type="submit" class="bg-indigo-600 hover:bg-indigo-700">
                            {{ editingAddonGroupId ? 'Save Group' : 'Add Group' }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="addonOptionDialogOpen" @update:open="(open) => !open && closeAddonOptionDialog()">
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{ editingAddonOptionId ? 'Edit Option' : 'Add Option' }}</DialogTitle>
                </DialogHeader>

                <form class="space-y-5" @submit.prevent="submitAddonOption">
                    <div class="space-y-2">
                        <Label>Option Name</Label>
                        <Input v-model="addonOptionForm.name" placeholder="e.g. With Agent (Add $10)" />
                    </div>

                    <div class="space-y-2">
                        <Label>Price ($)</Label>
                        <Input v-model.number="addonOptionForm.client_price" type="number" min="0" step="0.01" />
                    </div>

                    <div class="space-y-2">
                        <Label>Editor Price (₱)</Label>
                        <Input v-model.number="addonOptionForm.editor_price" type="number" min="0" step="0.01" />
                    </div>

                    <div class="space-y-2">
                        <Label>Sample URL (optional)</Label>
                        <Input v-model="addonOptionForm.sample_link" placeholder="https://..." />
                    </div>

                    <div class="space-y-2">
                        <Label>Sort Order</Label>
                        <Input v-model.number="addonOptionForm.sort_order" type="number" min="0" />
                    </div>

                    <div class="flex items-center gap-3">
                        <Checkbox :model-value="addonOptionForm.has_quantity" @update:model-value="(value) => (addonOptionForm.has_quantity = value === true)" />
                        <Label>Allow quantity (customer can choose how many)</Label>
                    </div>

                    <div class="flex items-center gap-3">
                        <Checkbox :model-value="addonOptionForm.is_rush_option" @update:model-value="(value) => (addonOptionForm.is_rush_option = value === true)" />
                        <Label>Rush option (auto-sets project priority to Urgent)</Label>
                    </div>

                    <div class="flex items-center gap-3">
                        <Checkbox :model-value="addonOptionForm.is_active" @update:model-value="(value) => (addonOptionForm.is_active = value === true)" />
                        <Label>Active</Label>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Button type="button" variant="outline" @click="closeAddonOptionDialog">Cancel</Button>
                        <Button type="submit" class="bg-indigo-600 hover:bg-indigo-700">
                            {{ editingAddonOptionId ? 'Save Option' : 'Add Option' }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

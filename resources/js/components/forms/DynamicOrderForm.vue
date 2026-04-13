<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import type { AppPageProps, Projects } from '@/types';
import type { SelectedServiceAddon, ServiceAddon, ServiceAddonGroup, ServicePricingData, ServiceSubStyle } from '@/types/services';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

type AddonState = {
    selected: boolean;
    quantity: number;
};

type ClientOption = {
    id: number;
    name: string;
};

const props = defineProps<{
    open: boolean;
    service: ServicePricingData;
    project?: Projects | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const page = usePage<AppPageProps<{ clients?: ClientOption[] }>>();
const userRole = computed(() => page.props.auth.user.role);
const isAdmin = computed(() => userRole.value === 'admin');
const clients = computed(() => [...(page.props.clients ?? [])].sort((a, b) => a.name.localeCompare(b.name)));

const preservedExtraFields = ref<Record<string, any>>({});
const addonState = reactive<Record<string, AddonState>>({});
const selectedDropdownOptions = reactive<Record<string, string>>({});

const form = useForm({
    client_id: props.project?.client_id ?? null,
    service_id: props.service.id,
    service_sub_style_id: props.project?.service_sub_style_id ?? null,
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
    total_price: Number(props.project?.total_price ?? 0),
    with_agent: props.project?.with_agent ?? false,
    per_property: props.project?.per_property ?? false,
    per_property_count: props.project?.per_property_count ?? 0,
    rush: props.project?.rush ?? false,
    extra_fields: {} as Record<string, any>,
});

const selectedSubStyle = computed<ServiceSubStyle | null>(() => {
    if (!props.service?.sub_styles?.length) {
        return null;
    }

    return (
        props.service.sub_styles.find((subStyle) => subStyle.id === Number(form.service_sub_style_id)) ??
        props.service.sub_styles.find((subStyle) => normalizeValue(subStyle.name) === normalizeValue(form.style)) ??
        props.service.sub_styles[0]
    );
});

const formatOptions = computed(() => selectedSubStyle.value?.format_pricing ?? []);

const selectedFormat = computed(() => {
    return (
        formatOptions.value.find((option) => normalizeValue(option.format_name) === normalizeValue(form.format)) ??
        formatOptions.value[0] ??
        null
    );
});

const addonLookup = computed(() => new Map(props.service.addons.map((addon) => [addon.id, addon])));
const structuredAddonGroups = computed<ServiceAddonGroup[]>(() => props.service.addon_groups ?? []);
const hasStructuredAddonGroups = computed(() => structuredAddonGroups.value.length > 0);

const selectedAddons = computed<SelectedServiceAddon[]>(() => {
    if (hasStructuredAddonGroups.value) {
        return structuredAddonGroups.value.flatMap((group) => {
            if (group.input_type === 'dropdown') {
                const selectedSlug = selectedDropdownOptions[String(group.id)] ?? '';

                if (!selectedSlug) {
                    return [];
                }

                const selectedAddon = props.service.addons.find((addon) => addon.slug === selectedSlug);

                if (!selectedAddon) {
                    return [];
                }

                const state = addonState[selectedAddon.slug];
                const quantity = selectedAddon.has_quantity || selectedAddon.addon_type === 'quantity'
                    ? Math.max(1, Number(state?.quantity ?? 1))
                    : 1;

                return [{
                    addon_id: selectedAddon.id,
                    slug: selectedAddon.slug,
                    name: selectedAddon.name,
                    quantity,
                    group: selectedAddon.group,
                }];
            }

            return group.options.reduce<SelectedServiceAddon[]>((selected, option) => {
                const addon = props.service.addons.find((item) => item.id === option.id);

                if (!addon) {
                    return selected;
                }

                const state = addonState[addon.slug];

                if (!state) {
                    return selected;
                }

                const usesQuantity = addon.has_quantity || addon.addon_type === 'quantity';
                const quantity = usesQuantity ? Math.max(0, Number(state.quantity || 0)) : 1;
                const isSelected = usesQuantity ? quantity > 0 : state.selected;

                if (!isSelected) {
                    return selected;
                }

                selected.push({
                    addon_id: addon.id,
                    slug: addon.slug,
                    name: addon.name,
                    quantity: usesQuantity ? quantity : 1,
                    group: addon.group,
                });

                return selected;
            }, []);
        });
    }

    return props.service.addons.reduce<SelectedServiceAddon[]>((selected, addon) => {
        const state = addonState[addon.slug];

        if (!state) {
            return selected;
        }

        const usesQuantity = addon.has_quantity || addon.addon_type === 'quantity';
        const quantity = usesQuantity ? Math.max(0, Number(state.quantity || 0)) : 1;
        const isSelected = usesQuantity ? quantity > 0 : state.selected;

        if (!isSelected) {
            return selected;
        }

        selected.push({
            addon_id: addon.id,
            slug: addon.slug,
            name: addon.name,
            quantity: usesQuantity ? quantity : 1,
            group: addon.group,
        });

        return selected;
    }, []);
});

const customEffectsTotal = computed(() => {
    const customEffects = preservedExtraFields.value.custom_effects;

    if (!customEffects) {
        return 0;
    }

    if (Array.isArray(customEffects)) {
        return customEffects.reduce((sum, effect) => sum + Number(effect?.price ?? 0), 0);
    }

    if (typeof customEffects === 'string') {
        try {
            const parsed = JSON.parse(customEffects);
            if (Array.isArray(parsed)) {
                return parsed.reduce((sum, effect) => sum + Number(effect?.price ?? 0), 0);
            }
        } catch (error) {
            console.error('Failed to parse custom effects', error);
        }
    }

    return 0;
});

const totalPrice = computed(() => {
    const basePrice = Number(selectedFormat.value?.client_price ?? 0);
    const addonTotal = selectedAddons.value.reduce((sum, selectedAddon) => {
        const addon = props.service.addons.find((item) => item.id === selectedAddon.addon_id);
        if (!addon) {
            return sum;
        }

        return sum + Number(addon.client_price) * Number(selectedAddon.quantity || 1);
    }, 0);

    return basePrice + addonTotal + customEffectsTotal.value;
});

const addonGroups = computed(() => {
    return {
        general: props.service.addons.filter((addon) => !addon.group),
        effects: props.service.addons.filter((addon) => addon.group === 'effects'),
        captions: props.service.addons.filter((addon) => addon.group === 'captions'),
    };
});

watch(
    [() => props.open, () => props.project, () => props.service],
    ([open]) => {
        if (open) {
            initializeForm();
        }
    },
    { immediate: true, deep: true },
);

watch(
    [selectedSubStyle, formatOptions],
    ([subStyle, options]) => {
        if (!subStyle) {
            return;
        }

        form.service_sub_style_id = subStyle.id;
        form.style = subStyle.name;

        if (!options.some((option) => normalizeValue(option.format_name) === normalizeValue(form.format))) {
            form.format = options[0]?.format_name ?? '';
        }
    },
    { immediate: true },
);

watch(
    totalPrice,
    (value) => {
        form.total_price = Number(value.toFixed(2));
    },
    { immediate: true },
);

function initializeForm() {
    Object.keys(addonState).forEach((key) => delete addonState[key]);
    Object.keys(selectedDropdownOptions).forEach((key) => delete selectedDropdownOptions[key]);

    const project = props.project;
    const initialSubStyle = resolveInitialSubStyle(project);
    const initialAddons = extractInitialAddons(project);

    preservedExtraFields.value = buildPreservedExtraFields(project?.extra_fields);

    form.reset();
    form.clearErrors();

    form.client_id = project?.client_id ?? null;
    form.service_id = props.service.id;
    form.service_sub_style_id = initialSubStyle?.id ?? null;
    form.style = initialSubStyle?.name ?? project?.style ?? '';
    form.company_name = project?.company_name ?? '';
    form.contact = project?.contact ?? '';
    form.project_name = project?.project_name ?? '';
    form.format = resolveInitialFormat(initialSubStyle, project?.format);
    form.camera = project?.camera ?? '';
    form.quality = project?.quality ?? '';
    form.music = project?.music ?? '';
    form.music_link = project?.music_link ?? '';
    form.file_link = project?.file_link ?? '';
    form.notes = project?.notes ?? '';
    form.total_price = Number(project?.total_price ?? 0);
    form.with_agent = Boolean(project?.with_agent);
    form.per_property = Boolean(project?.per_property);
    form.per_property_count = Number(project?.per_property_count ?? 0);
    form.rush = Boolean(project?.rush);

    props.service.addons.forEach((addon) => {
        const matchedAddon = initialAddons.find(
            (selectedAddon) =>
                normalizeValue(selectedAddon.slug) === normalizeValue(addon.slug) ||
                normalizeValue(selectedAddon.name) === normalizeValue(addon.name),
        );

        const usesQuantity = addon.has_quantity || addon.addon_type === 'quantity';
        addonState[addon.slug] = {
            selected: usesQuantity ? Boolean((matchedAddon?.quantity ?? 0) > 0) : Boolean(matchedAddon),
            quantity: usesQuantity ? Number(matchedAddon?.quantity ?? 0) : 1,
        };

        if (addon.group_input_type === 'dropdown' && matchedAddon && addon.service_addon_group_id) {
            selectedDropdownOptions[String(addon.service_addon_group_id)] = addon.slug;
        }
    });

    form.extra_fields = buildExtraFields();
}

function resolveInitialSubStyle(project?: Projects | null): ServiceSubStyle | null {
    if (project?.service_sub_style_id) {
        return props.service.sub_styles.find((subStyle) => subStyle.id === Number(project.service_sub_style_id)) ?? null;
    }

    if (project?.style) {
        return (
            props.service.sub_styles.find(
                (subStyle) =>
                    normalizeValue(subStyle.name) === normalizeValue(project.style) ||
                    normalizeValue(subStyle.slug) === normalizeValue(project.style),
            ) ?? null
        );
    }

    return props.service.sub_styles[0] ?? null;
}

function resolveInitialFormat(subStyle: ServiceSubStyle | null, projectFormat?: string | null): string {
    if (!subStyle) {
        return projectFormat ?? '';
    }

    if (projectFormat) {
        const matchingFormat = subStyle.format_pricing.find(
            (formatOption) => normalizeValue(formatOption.format_name) === normalizeValue(projectFormat),
        );

        if (matchingFormat) {
            return matchingFormat.format_name;
        }
    }

    return subStyle.format_pricing[0]?.format_name ?? projectFormat ?? '';
}

function extractInitialAddons(project?: Projects | null): SelectedServiceAddon[] {
    const mergedAddons = new Map<string, SelectedServiceAddon>();
    const extraFields = project?.extra_fields ?? {};

    const serviceAddons = Array.isArray(extraFields.service_addons) ? extraFields.service_addons : [];

    serviceAddons.forEach((addon: any) => {
        mergeInitialAddon(mergedAddons, {
            addon_id: addon.addon_id ?? null,
            slug: addon.slug ?? normalizeValue(addon.name ?? ''),
            name: addon.name ?? '',
            quantity: Number(addon.quantity ?? 1),
            group: addon.group ?? null,
        });
    });

    if (project?.with_agent) {
        mergeInitialAddon(mergedAddons, { slug: 'with-agent', name: 'With Agent', quantity: 1 });
    }

    if (project?.rush) {
        mergeInitialAddon(mergedAddons, { slug: 'rush', name: 'Rush', quantity: 1 });
    }

    if (project?.per_property) {
        mergeInitialAddon(mergedAddons, {
            slug: 'per-property-line',
            name: 'Per Property Line',
            quantity: Number(project.per_property_count ?? 1),
        });
    }

    if (Array.isArray(extraFields.effects)) {
        extraFields.effects.forEach((effect: any) => {
            if (typeof effect === 'string') {
                mergeInitialAddon(mergedAddons, {
                    slug: normalizeValue(effect),
                    name: effect,
                    quantity: 1,
                    group: 'effects',
                });
            } else {
                mergeInitialAddon(mergedAddons, {
                    slug: normalizeValue(effect.slug ?? effect.id ?? effect.name ?? ''),
                    name: effect.id ?? effect.name ?? '',
                    quantity: Number(effect.quantity ?? 1),
                    group: 'effects',
                });
            }
        });
    }

    if (Array.isArray(extraFields.captions)) {
        extraFields.captions.forEach((caption: string) => {
            mergeInitialAddon(mergedAddons, {
                slug: normalizeValue(caption),
                name: caption,
                quantity: 1,
                group: 'captions',
            });
        });
    }

    return [...mergedAddons.values()];
}

function mergeInitialAddon(store: Map<string, SelectedServiceAddon>, addon: SelectedServiceAddon) {
    const key = addon.slug || normalizeValue(addon.name);

    if (!key) {
        return;
    }

    const existing = store.get(key);
    store.set(key, {
        ...addon,
        quantity: Math.max(Number(existing?.quantity ?? 0), Number(addon.quantity ?? 1)),
    });
}

function buildPreservedExtraFields(extraFields: Record<string, any> | undefined) {
    if (!extraFields || typeof extraFields !== 'object') {
        return {};
    }

    const { effects, captions, service_addons, per_property_quantity, ...rest } = extraFields;
    return { ...rest };
}

function buildExtraFields() {
    const effects = selectedAddons.value
        .filter((addon) => addon.group === 'effects')
        .map((addon) => {
            const serviceAddon = props.service.addons.find((item) => item.id === addon.addon_id);
            const usesQuantity = serviceAddon?.has_quantity || serviceAddon?.addon_type === 'quantity';

            return usesQuantity
                ? {
                      id: addon.name,
                      quantity: addon.quantity,
                  }
                : addon.name;
        });

    const captions = selectedAddons.value.filter((addon) => addon.group === 'captions').map((addon) => addon.name);

    return {
        ...preservedExtraFields.value,
        effects,
        captions,
        service_addons: selectedAddons.value,
        per_property_quantity: Number(
            selectedAddons.value.find((addon) => addonMatchesLegacyRole(addon, 'per-property-line'))?.quantity ?? 0,
        ),
    };
}

function selectDropdownOption(group: ServiceAddonGroup, value: string) {
    const normalizedValue = value === '__none__' ? '' : value;
    selectedDropdownOptions[String(group.id)] = normalizedValue;

    group.options.forEach((option) => {
        const addon = props.service.addons.find((item) => item.id === option.id);

        if (!addon) {
            return;
        }

        if (!addonState[addon.slug]) {
            addonState[addon.slug] = { selected: false, quantity: 0 };
        }

        const isSelected = addon.slug === normalizedValue;
        addonState[addon.slug].selected = isSelected;
        addonState[addon.slug].quantity = isSelected ? Math.max(1, addonState[addon.slug].quantity || 1) : 0;
    });
}

function toggleAddon(addon: ServiceAddon, checked: boolean | 'indeterminate') {
    const usesQuantity = addon.has_quantity || addon.addon_type === 'quantity';
    const isChecked = checked === true;

    if (!addonState[addon.slug]) {
        addonState[addon.slug] = { selected: false, quantity: 0 };
    }

    addonState[addon.slug].selected = isChecked;

    if (usesQuantity) {
        addonState[addon.slug].quantity = isChecked ? Math.max(1, addonState[addon.slug].quantity || 1) : 0;
    }
}

function incrementAddon(addon: ServiceAddon) {
    if (!addonState[addon.slug]) {
        addonState[addon.slug] = { selected: true, quantity: 1 };
        return;
    }

    addonState[addon.slug].selected = true;
    addonState[addon.slug].quantity = Math.max(1, addonState[addon.slug].quantity || 0) + 1;
}

function decrementAddon(addon: ServiceAddon) {
    if (!addonState[addon.slug]) {
        return;
    }

    addonState[addon.slug].quantity = Math.max(0, addonState[addon.slug].quantity - 1);

    if (addonState[addon.slug].quantity === 0) {
        addonState[addon.slug].selected = false;
    }
}

function resolveAddonOption(option: ServiceAddon) {
    return addonLookup.value.get(option.id) ?? option;
}

function addonUsesQuantity(addon?: Pick<ServiceAddon, 'has_quantity' | 'addon_type'> | null) {
    return Boolean(addon?.has_quantity || addon?.addon_type === 'quantity');
}

function isAddonSelected(addon?: Pick<ServiceAddon, 'slug'> | null) {
    if (!addon?.slug) {
        return false;
    }

    return Boolean(addonState[addon.slug]?.selected);
}

function getAddonQuantity(addon?: Pick<ServiceAddon, 'slug'> | null) {
    if (!addon?.slug) {
        return 0;
    }

    return Number(addonState[addon.slug]?.quantity ?? 0);
}

function formatAddonPrice(addon: ServiceAddon) {
    if (!addon.client_price) {
        return 'Included';
    }

    return addon.has_quantity || addon.addon_type === 'quantity' ? `$${addon.client_price} each` : `+$${addon.client_price}`;
}

function formatSelectOptionLabel(addon: ServiceAddon) {
    if (!addon.client_price) {
        return `${addon.name} (Free)`;
    }

    return `${addon.name} (+$${addon.client_price})`;
}

function handleSubmit() {
    const activeAddons = selectedAddons.value;
    const withAgentAddon = activeAddons.find((addon) => addonMatchesLegacyRole(addon, 'with-agent'));
    const rushAddon = activeAddons.find((addon) => {
        const matchedAddon = props.service.addons.find((item) => item.id === addon.addon_id || item.slug === addon.slug);
        return Boolean(matchedAddon?.is_rush_option) || addonMatchesLegacyRole(addon, 'rush');
    });
    const perPropertyAddon = activeAddons.find((addon) => addonMatchesLegacyRole(addon, 'per-property-line'));

    form.service_id = props.service.id;
    form.service_sub_style_id = selectedSubStyle.value?.id ?? null;
    form.style = selectedSubStyle.value?.name ?? '';
    form.format = selectedFormat.value?.format_name ?? '';
    form.total_price = Number(totalPrice.value.toFixed(2));
    form.with_agent = Boolean(withAgentAddon);
    form.rush = Boolean(rushAddon);
    form.per_property = Boolean(perPropertyAddon);
    form.per_property_count = form.per_property ? Number(perPropertyAddon?.quantity ?? 1) : 0;
    form.extra_fields = buildExtraFields();

    const createRoute = isAdmin.value ? 'admin.project.create' : 'projects.store';
    const updateRoute = isAdmin.value ? 'admin.project.update' : 'projects.client_update';

    if (props.project) {
        form.put(route(updateRoute, props.project.id), {
            onSuccess: () => {
                toast.success('Project updated successfully.', { position: 'top-right' });
                emit('close');
            },
        });

        return;
    }

    form.post(route(createRoute), {
        onSuccess: () => {
            toast.success(isAdmin.value ? 'Project created successfully.' : 'Order placed successfully.', { position: 'top-right' });
            emit('close');
        },
    });
}

function addonMatchesLegacyRole(
    addon: Pick<SelectedServiceAddon, 'slug' | 'name'> | Pick<ServiceAddon, 'slug' | 'name'> | undefined | null,
    role: 'with-agent' | 'rush' | 'per-property-line',
) {
    const normalizedSlug = normalizeValue(addon?.slug);
    const normalizedName = normalizeValue(addon?.name);

    return normalizedSlug === role || normalizedName === role;
}

function normalizeValue(value?: string | null) {
    return String(value ?? '')
        .trim()
        .toLowerCase()
        .replace(/&/g, 'and')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}
</script>

<template>
    <Dialog :open="open" @update:open="(value) => !value && emit('close')">
        <DialogContent class="max-h-[90vh] !w-full !max-w-6xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle>
                    {{ project ? `Edit Project - ${form.project_name}` : `Order: ${service.name}` }}
                </DialogTitle>
            </DialogHeader>

            <form class="space-y-8" @submit.prevent="handleSubmit">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div v-if="isAdmin" class="space-y-2">
                        <Label>Client</Label>
                        <Select v-model="form.client_id">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select a client" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="client in clients" :key="client.id" :value="client.id">
                                    {{ client.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <span v-if="form.errors.client_id" class="text-sm text-red-500">{{ form.errors.client_id }}</span>
                    </div>

                    <div class="space-y-2">
                        <Label>Select Style</Label>
                        <Select v-model="form.service_sub_style_id">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select a sub-style" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="subStyle in service.sub_styles" :key="subStyle.id" :value="subStyle.id">
                                    {{ subStyle.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <span v-if="form.errors.service_sub_style_id" class="text-sm text-red-500">{{ form.errors.service_sub_style_id }}</span>
                    </div>

                    <div class="space-y-2">
                        <Label>Project Name</Label>
                        <Input v-model="form.project_name" placeholder="Enter your project name" />
                        <span v-if="form.errors.project_name" class="text-sm text-red-500">{{ form.errors.project_name }}</span>
                    </div>

                    <div class="space-y-2">
                        <Label>Format</Label>
                        <Select v-model="form.format">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select a format" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="option in formatOptions" :key="option.id" :value="option.format_name">
                                    {{ option.format_label }} (${{ option.client_price }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <span v-if="form.errors.format" class="text-sm text-red-500">{{ form.errors.format }}</span>
                    </div>

                    <div class="space-y-2">
                        <Label>Camera</Label>
                        <Input v-model="form.camera" placeholder="Enter your camera brand and model" />
                        <span v-if="form.errors.camera" class="text-sm text-red-500">{{ form.errors.camera }}</span>
                    </div>

                    <div class="space-y-2">
                        <Label>Video Quality</Label>
                        <Select v-model="form.quality">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select quality" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="4k quality">4K Quality</SelectItem>
                                <SelectItem value="1080p HD quality">1080P HD Quality</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>Music Preference</Label>
                        <Select v-model="form.music">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select music" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="royalty free music with vocals">Royalty free music with vocals</SelectItem>
                                <SelectItem value="royalty free music without vocals">Royalty free music without vocals</SelectItem>
                                <SelectItem value="IG trendy music with vocals">IG trendy music with vocals</SelectItem>
                                <SelectItem value="IG trendy music without vocals">IG trendy music without vocals</SelectItem>
                                <SelectItem value="I will provide my own music">I will provide my own music</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>Music Link</Label>
                        <Input v-model="form.music_link" placeholder="Optional song link or title" />
                    </div>

                    <div class="space-y-2">
                        <Label>File Link</Label>
                        <Input v-model="form.file_link" placeholder="Enter your file link" />
                        <span v-if="form.errors.file_link" class="text-sm text-red-500">{{ form.errors.file_link }}</span>
                    </div>
                </div>

                <div v-if="hasStructuredAddonGroups || addonGroups.general.length || addonGroups.effects.length || addonGroups.captions.length" class="space-y-6">
                    <div class="flex items-center gap-4">
                        <Separator class="flex-1" />
                        <span class="text-sm font-semibold uppercase tracking-[0.22em] text-muted-foreground">Add-Ons</span>
                        <Separator class="flex-1" />
                    </div>

                    <div v-if="hasStructuredAddonGroups" class="columns-1 md:columns-2 md:gap-6">
                        <div v-for="group in structuredAddonGroups" :key="group.id" class="mb-4 break-inside-avoid space-y-3 rounded-lg border p-4 md:mb-6">
                            <div class="space-y-1">
                                <h3 class="text-sm font-semibold">{{ group.label }}</h3>
                                <p v-if="group.helper_text" class="text-sm text-muted-foreground">{{ group.helper_text }}</p>
                            </div>

                            <div v-if="group.input_type === 'dropdown'" class="space-y-2">
                                <Label>Select an option</Label>
                                <Select
                                    :model-value="selectedDropdownOptions[String(group.id)] || '__none__'"
                                    @update:model-value="(value) => selectDropdownOption(group, String(value))"
                                >
                                    <SelectTrigger class="w-full">
                                        <SelectValue :placeholder="group.is_required ? 'Choose an option' : 'No selection'" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-if="!group.is_required" value="__none__">No selection</SelectItem>
                                        <SelectItem
                                            v-for="option in group.options"
                                            :key="option.id"
                                            :value="option.slug"
                                        >
                                            {{ formatSelectOptionLabel(addonLookup.get(option.id) ?? option) }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>

                                <div
                                    v-if="
                                        selectedDropdownOptions[String(group.id)] &&
                                        (addonLookup.get(
                                            group.options.find((option) => option.slug === selectedDropdownOptions[String(group.id)])?.id ?? 0,
                                        )?.has_quantity ||
                                            addonLookup.get(
                                                group.options.find((option) => option.slug === selectedDropdownOptions[String(group.id)])?.id ?? 0,
                                            )?.addon_type === 'quantity')
                                    "
                                    class="flex items-center gap-2"
                                >
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        class="h-8 w-8"
                                        @click="
                                            () => {
                                                const activeOption = group.options.find((option) => option.slug === selectedDropdownOptions[String(group.id)]);
                                                if (activeOption) decrementAddon(addonLookup.get(activeOption.id) ?? (activeOption as ServiceAddon));
                                            }
                                        "
                                    >
                                        -
                                    </Button>
                                    <span class="w-8 text-center text-sm">
                                        {{
                                            addonState[selectedDropdownOptions[String(group.id)]]?.quantity ?? 1
                                        }}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        class="h-8 w-8"
                                        @click="
                                            () => {
                                                const activeOption = group.options.find((option) => option.slug === selectedDropdownOptions[String(group.id)]);
                                                if (activeOption) incrementAddon(addonLookup.get(activeOption.id) ?? (activeOption as ServiceAddon));
                                            }
                                        "
                                    >
                                        +
                                    </Button>
                                </div>
                            </div>

                            <div v-else class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div v-for="option in group.options" :key="option.id" class="rounded-lg border p-4">
                                    <div class="space-y-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="space-y-1">
                                                <div class="font-medium">{{ option.name }}</div>
                                                <div class="text-sm text-muted-foreground">
                                                    {{ formatAddonPrice(resolveAddonOption(option as ServiceAddon)) }}
                                                </div>
                                                <a
                                                    v-if="option.sample_link"
                                                    :href="option.sample_link"
                                                    target="_blank"
                                                    class="text-sm text-blue-600 hover:underline"
                                                >
                                                    View sample
                                                </a>
                                            </div>

                                            <Checkbox
                                                :model-value="isAddonSelected(resolveAddonOption(option as ServiceAddon))"
                                                @update:model-value="(value) => toggleAddon(resolveAddonOption(option as ServiceAddon), value)"
                                            />
                                        </div>

                                        <div
                                            v-if="addonUsesQuantity(resolveAddonOption(option as ServiceAddon))"
                                            class="space-y-2"
                                        >
                                            <p
                                                v-if="!isAddonSelected(resolveAddonOption(option as ServiceAddon))"
                                                class="text-xs text-muted-foreground"
                                            >
                                                Select this add-on first, then choose how many.
                                            </p>
                                            <div v-else class="flex items-center gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon"
                                                    class="h-8 w-8"
                                                    @click="decrementAddon(resolveAddonOption(option as ServiceAddon))"
                                                >
                                                    -
                                                </Button>
                                                <span class="w-8 text-center text-sm">
                                                    {{ getAddonQuantity(resolveAddonOption(option as ServiceAddon)) }}
                                                </span>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon"
                                                    class="h-8 w-8"
                                                    @click="incrementAddon(resolveAddonOption(option as ServiceAddon))"
                                                >
                                                    +
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="space-y-6">
                        <div v-if="addonGroups.general.length" class="space-y-3">
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div v-for="addon in addonGroups.general" :key="addon.slug" class="rounded-lg border p-4">
                                    <div class="space-y-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="space-y-1">
                                                <div class="font-medium">{{ addon.name }}</div>
                                                <div class="text-sm text-muted-foreground">{{ formatAddonPrice(addon) }}</div>
                                            </div>

                                            <Checkbox
                                                :model-value="isAddonSelected(addon)"
                                                @update:model-value="(value) => toggleAddon(addon, value)"
                                            />
                                        </div>

                                        <div v-if="addonUsesQuantity(addon)" class="space-y-2">
                                            <p v-if="!isAddonSelected(addon)" class="text-xs text-muted-foreground">
                                                Select this add-on first, then choose how many.
                                            </p>
                                            <div v-else class="flex items-center gap-2">
                                                <Button type="button" variant="outline" size="icon" class="h-8 w-8" @click="decrementAddon(addon)">-</Button>
                                                <span class="w-8 text-center text-sm">{{ getAddonQuantity(addon) }}</span>
                                                <Button type="button" variant="outline" size="icon" class="h-8 w-8" @click="incrementAddon(addon)">+</Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="addonGroups.effects.length" class="space-y-3">
                            <h3 class="text-lg font-semibold">Effects</h3>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div v-for="addon in addonGroups.effects" :key="addon.slug" class="rounded-lg border p-4">
                                    <div class="space-y-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="space-y-1">
                                                <div class="font-medium">{{ addon.name }}</div>
                                                <div class="text-sm text-muted-foreground">{{ formatAddonPrice(addon) }}</div>
                                                <a v-if="addon.sample_link" :href="addon.sample_link" target="_blank" class="text-sm text-blue-600 hover:underline">
                                                    View sample
                                                </a>
                                            </div>

                                            <Checkbox
                                                :model-value="isAddonSelected(addon)"
                                                @update:model-value="(value) => toggleAddon(addon, value)"
                                            />
                                        </div>

                                        <div v-if="addonUsesQuantity(addon)" class="space-y-2">
                                            <p v-if="!isAddonSelected(addon)" class="text-xs text-muted-foreground">
                                                Select this add-on first, then choose how many.
                                            </p>
                                            <div v-else class="flex items-center gap-2">
                                                <Button type="button" variant="outline" size="icon" class="h-8 w-8" @click="decrementAddon(addon)">-</Button>
                                                <span class="w-8 text-center text-sm">{{ getAddonQuantity(addon) }}</span>
                                                <Button type="button" variant="outline" size="icon" class="h-8 w-8" @click="incrementAddon(addon)">+</Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="addonGroups.captions.length" class="space-y-3">
                            <h3 class="text-lg font-semibold">Captions & Text</h3>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div v-for="addon in addonGroups.captions" :key="addon.slug" class="rounded-lg border p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="space-y-1">
                                            <div class="font-medium">{{ addon.name }}</div>
                                            <div class="text-sm text-muted-foreground">{{ formatAddonPrice(addon) }}</div>
                                        </div>
                                        <Checkbox
                                            :model-value="addonState[addon.slug]?.selected ?? false"
                                            @update:model-value="(value) => toggleAddon(addon, value)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center gap-4">
                        <Separator class="flex-1" />
                        <span class="text-sm font-semibold uppercase tracking-[0.22em] text-muted-foreground">More Instructions</span>
                        <Separator class="flex-1" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Notes</Label>
                        <Textarea v-model="form.notes" class="min-h-[140px]" placeholder="Enter any instructions" />
                    </div>
                </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="text-xl font-semibold">Total: ${{ totalPrice.toFixed(2) }}</div>
                    <Button type="submit" :disabled="form.processing || !selectedFormat">
                        {{ project ? 'Save Changes' : 'Place Order' }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>

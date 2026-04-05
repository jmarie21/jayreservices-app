<?php

namespace App\Services;

use App\Models\Service;
use App\Models\ServiceAddonAssignment;
use App\Models\ServiceCategory;
use App\Models\ServiceFormatPricing;
use App\Models\ServiceSubStyle;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PricingService
{
    /**
     * @var array<int, \App\Models\Service>
     */
    protected array $serviceCache = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $pricingDataCache = [];

    public function calculateClientPrice(array $orderData): float
    {
        return $this->calculatePrices($orderData)['client_total'];
    }

    public function calculateEditorPrice(array $orderData): float
    {
        return $this->calculatePrices($orderData)['editor_total'];
    }

    /**
     * @return array{
     *     service_id:int,
     *     sub_style_id:int|null,
     *     sub_style_name:string|null,
     *     format_name:string|null,
     *     client_total:float,
     *     editor_total:float,
     *     selected_addons:array<int, array<string, mixed>>
     * }
     */
    public function calculatePrices(array $orderData): array
    {
        $serviceId = (int) Arr::get($orderData, 'service_id', 0);
        $service = $this->loadService($serviceId);
        $availableAddons = $this->resolveAvailableAddons($service);

        $subStyle = $this->resolveSubStyle($service, $orderData);
        $formatPricing = $this->resolveFormatPricing($subStyle, (string) Arr::get($orderData, 'format', ''));

        $clientTotal = (float) ($formatPricing?->client_price ?? 0);
        $editorTotal = (float) ($formatPricing?->editor_price ?? 0);

        $selectedAddons = $this->extractSelectedAddons($orderData);

        foreach ($selectedAddons as $selectedAddon) {
            $resolvedAddon = $this->findMatchingAddon($availableAddons, $selectedAddon);

            if (! $resolvedAddon) {
                continue;
            }

            $quantity = (int) ($selectedAddon['quantity'] ?? 1);
            $multiplier = $quantity > 0 ? $quantity : 1;

            $clientTotal += ((float) $resolvedAddon['client_price']) * $multiplier;
            $editorTotal += ((float) $resolvedAddon['editor_price']) * $multiplier;
        }

        $clientTotal += $this->extractCustomEffectsTotal($orderData);

        return [
            'service_id' => $service->id,
            'sub_style_id' => $subStyle?->id,
            'sub_style_name' => $subStyle?->name,
            'format_name' => $formatPricing?->format_name,
            'client_total' => round($clientTotal, 2),
            'editor_total' => round($editorTotal, 2),
            'selected_addons' => $selectedAddons->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getServicePricingData(int $serviceId, bool $includeEditor = false): array
    {
        $cacheKey = $serviceId.'|'.($includeEditor ? 'admin' : 'client');

        if (isset($this->pricingDataCache[$cacheKey])) {
            return $this->pricingDataCache[$cacheKey];
        }

        $service = $this->loadService($serviceId);
        $availableAddons = $this->resolveAvailableAddons($service, $includeEditor);

        $pricingData = [
            'id' => $service->id,
            'name' => $service->name,
            'slug' => $service->slug,
            'description' => $service->description,
            'features' => $service->features ?? [],
            'video_link' => $service->video_link,
            'thumbnail_url' => $service->thumbnail_url,
            'sort_order' => $service->sort_order,
            'is_active' => (bool) $service->is_active,
            'category' => $service->category ? [
                'id' => $service->category->id,
                'name' => $service->category->name,
                'slug' => $service->category->slug,
                'icon' => $service->category->icon,
            ] : null,
            'sub_styles' => $service->subStyles
                ->where('is_active', true)
                ->sortBy('sort_order')
                ->values()
                ->map(function (ServiceSubStyle $subStyle) use ($includeEditor) {
                    return [
                        'id' => $subStyle->id,
                        'name' => $subStyle->name,
                        'slug' => $subStyle->slug,
                        'sort_order' => $subStyle->sort_order,
                        'is_active' => (bool) $subStyle->is_active,
                        'format_pricing' => $subStyle->formatPricing
                            ->sortBy('sort_order')
                            ->values()
                            ->map(function (ServiceFormatPricing $formatPricing) use ($includeEditor) {
                                $data = [
                                    'id' => $formatPricing->id,
                                    'format_name' => $formatPricing->format_name,
                                    'format_label' => $formatPricing->format_label,
                                    'client_price' => (float) $formatPricing->client_price,
                                    'sort_order' => $formatPricing->sort_order,
                                ];

                                if ($includeEditor) {
                                    $data['editor_price'] = (float) $formatPricing->editor_price;
                                }

                                return $data;
                            })
                            ->all(),
                    ];
                })
                ->all(),
            'addons' => $availableAddons
                ->sortBy([
                    ['group_sort_order', 'asc'],
                    ['sort_order', 'asc'],
                    ['name', 'asc'],
                ])
                ->values()
                ->all(),
            'addon_groups' => $this->buildAddonGroupsPayload($service, $availableAddons),
        ];

        return $this->pricingDataCache[$cacheKey] = $pricingData;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCategoryCatalogData(ServiceCategory $category, bool $includeEditor = false): array
    {
        $category = ServiceCategory::query()
            ->whereKey($category->id)
            ->firstOrFail();

        $services = Service::query()
            ->where('service_category_id', $category->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'video_link' => $category->video_link,
            'thumbnail_url' => $category->thumbnail_url,
            'icon' => $category->icon,
            'sort_order' => $category->sort_order,
            'is_active' => (bool) $category->is_active,
            'services' => $services
                ->map(fn (Service $service) => $this->getServicePricingData($service->id, $includeEditor))
                ->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllCategoriesCatalogData(bool $includeEditor = false, bool $onlyActive = true): array
    {
        $query = ServiceCategory::query()->orderBy('sort_order');

        if ($onlyActive) {
            $query->where('is_active', true);
        }

        return $query
            ->get()
            ->map(fn (ServiceCategory $category) => $this->getCategoryCatalogData($category, $includeEditor))
            ->all();
    }

    protected function loadService(int $serviceId): Service
    {
        if (isset($this->serviceCache[$serviceId])) {
            return $this->serviceCache[$serviceId];
        }

        $service = Service::query()
            ->with([
                'category.addonAssignments.addon',
                'addonAssignments.addon.addonGroup',
                'addonGroups.addons',
                'subStyles.formatPricing',
            ])
            ->findOrFail($serviceId);

        return $this->serviceCache[$serviceId] = $service;
    }

    protected function resolveSubStyle(Service $service, array $orderData): ?ServiceSubStyle
    {
        $requestedSubStyleId = Arr::get($orderData, 'service_sub_style_id');

        if ($requestedSubStyleId) {
            return $service->subStyles->firstWhere('id', (int) $requestedSubStyleId);
        }

        $style = trim((string) Arr::get($orderData, 'style', ''));

        if ($style !== '') {
            $normalizedStyle = $this->normalizeValue($style);

            return $service->subStyles->first(function (ServiceSubStyle $subStyle) use ($normalizedStyle) {
                return $this->normalizeValue($subStyle->name) === $normalizedStyle
                    || $this->normalizeValue($subStyle->slug) === $normalizedStyle;
            });
        }

        return $service->subStyles
            ->where('is_active', true)
            ->sortBy('sort_order')
            ->first();
    }

    protected function resolveFormatPricing(?ServiceSubStyle $subStyle, string $format): ?ServiceFormatPricing
    {
        if (! $subStyle) {
            return null;
        }

        $formatPricing = $subStyle->formatPricing->sortBy('sort_order');

        if ($format === '' && $formatPricing->count() === 1) {
            return $formatPricing->first();
        }

        $normalizedFormat = $this->normalizeValue($format);

        return $formatPricing->first(function (ServiceFormatPricing $pricing) use ($normalizedFormat) {
            return $this->normalizeValue($pricing->format_name) === $normalizedFormat
                || $this->normalizeValue($pricing->format_label) === $normalizedFormat;
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function resolveAvailableAddons(Service $service, bool $includeEditor = true): Collection
    {
        $resolved = collect();
        $assignments = collect();

        if ($service->category) {
            $assignments = $assignments->concat($service->category->addonAssignments);
        }

        $assignments = $assignments->concat($service->addonAssignments);

        /** @var ServiceAddonAssignment $assignment */
        foreach ($assignments as $assignment) {
            $addon = $assignment->addon;

            if (! $addon || ! $addon->is_active) {
                continue;
            }

            $payload = [
                'id' => $addon->id,
                'assignment_id' => $assignment->id,
                'name' => $addon->name,
                'slug' => $addon->slug,
                'addon_type' => $addon->addon_type,
                'client_price' => (float) ($assignment->client_price_override ?? $addon->client_price),
                'editor_price' => (float) ($assignment->editor_price_override ?? $addon->editor_price),
                'has_quantity' => (bool) $addon->has_quantity,
                'is_rush_option' => (bool) $addon->is_rush_option,
                'sample_link' => $addon->sample_link,
                'group' => $addon->addonGroup?->slug ?? $addon->group,
                'group_label' => $addon->addonGroup?->label,
                'group_input_type' => $addon->addonGroup?->input_type,
                'group_helper_text' => $addon->addonGroup?->helper_text,
                'group_sort_order' => $addon->addonGroup?->sort_order ?? 9999,
                'group_required' => (bool) ($addon->addonGroup?->is_required ?? false),
                'service_addon_group_id' => $addon->service_addon_group_id,
                'sort_order' => $addon->sort_order,
                'is_active' => (bool) $addon->is_active,
            ];

            if (! $includeEditor) {
                unset($payload['editor_price']);
            }

            $resolved->put($addon->slug, $payload);
        }

        return $resolved->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $availableAddons
     * @return array<int, array<string, mixed>>
     */
    protected function buildAddonGroupsPayload(Service $service, Collection $availableAddons): array
    {
        $addonsById = $availableAddons->keyBy('id');

        return $service->addonGroups
            ->where('is_active', true)
            ->sortBy('sort_order')
            ->values()
            ->map(function ($group) use ($addonsById) {
                return [
                    'id' => $group->id,
                    'label' => $group->label,
                    'slug' => $group->slug,
                    'input_type' => $group->input_type,
                    'helper_text' => $group->helper_text,
                    'sort_order' => $group->sort_order,
                    'is_required' => (bool) $group->is_required,
                    'is_active' => (bool) $group->is_active,
                    'options' => $group->addons
                        ->where('is_active', true)
                        ->sortBy('sort_order')
                        ->values()
                        ->map(fn ($addon) => $addonsById->get($addon->id))
                        ->filter()
                        ->values()
                        ->all(),
                ];
            })
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function extractSelectedAddons(array $orderData): Collection
    {
        $selectedAddons = collect();
        $extraFields = Arr::get($orderData, 'extra_fields', []);

        foreach (Arr::get($extraFields, 'service_addons', []) as $addon) {
            $this->mergeSelectedAddon($selectedAddons, [
                'addon_id' => isset($addon['addon_id']) ? (int) $addon['addon_id'] : null,
                'slug' => $addon['slug'] ?? null,
                'name' => $addon['name'] ?? null,
                'quantity' => (int) ($addon['quantity'] ?? 1),
                'group' => $addon['group'] ?? null,
            ]);
        }

        if (Arr::get($orderData, 'with_agent') && ! $this->selectedAddonsContainRole($selectedAddons, 'with-agent')) {
            $this->mergeSelectedAddon($selectedAddons, [
                'slug' => 'with-agent',
                'name' => 'With Agent',
                'quantity' => 1,
            ]);
        }

        if (Arr::get($orderData, 'rush') && ! $this->selectedAddonsContainRole($selectedAddons, 'rush')) {
            $this->mergeSelectedAddon($selectedAddons, [
                'slug' => 'rush',
                'name' => 'Rush',
                'quantity' => 1,
            ]);
        }

        if (Arr::get($orderData, 'per_property') && ! $this->selectedAddonsContainRole($selectedAddons, 'per-property-line')) {
            $this->mergeSelectedAddon($selectedAddons, [
                'slug' => 'per-property-line',
                'name' => 'Per Property Line',
                'quantity' => max(
                    1,
                    (int) Arr::get($orderData, 'per_property_count', Arr::get($extraFields, 'per_property_quantity', 1))
                ),
            ]);
        }

        foreach (Arr::get($extraFields, 'effects', []) as $effect) {
            if (is_array($effect)) {
                $this->mergeSelectedAddon($selectedAddons, [
                    'slug' => Arr::get($effect, 'slug'),
                    'name' => Arr::get($effect, 'id', Arr::get($effect, 'name')),
                    'quantity' => max(1, (int) Arr::get($effect, 'quantity', 1)),
                    'group' => 'effects',
                ]);

                continue;
            }

            $this->mergeSelectedAddon($selectedAddons, [
                'name' => (string) $effect,
                'quantity' => 1,
                'group' => 'effects',
            ]);
        }

        foreach (Arr::get($extraFields, 'captions', []) as $caption) {
            $this->mergeSelectedAddon($selectedAddons, [
                'name' => (string) $caption,
                'quantity' => 1,
                'group' => 'captions',
            ]);
        }

        return $selectedAddons->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int|string, array<string, mixed>>  $selectedAddons
     */
    protected function selectedAddonsContainRole(Collection $selectedAddons, string $role): bool
    {
        return $selectedAddons->contains(fn (array $selectedAddon) => $this->selectedAddonMatchesRole($selectedAddon, $role));
    }

    /**
     * @param  array<string, mixed>  $selectedAddon
     */
    protected function selectedAddonMatchesRole(array $selectedAddon, string $role): bool
    {
        return $this->normalizeValue((string) ($selectedAddon['slug'] ?? '')) === $role
            || $this->normalizeValue((string) ($selectedAddon['name'] ?? '')) === $role;
    }

    /**
     * @param  \Illuminate\Support\Collection<int|string, array<string, mixed>>  $selectedAddons
     * @param  array<string, mixed>  $addon
     */
    protected function mergeSelectedAddon(Collection $selectedAddons, array $addon): void
    {
        $name = trim((string) ($addon['name'] ?? ''));
        $slug = trim((string) ($addon['slug'] ?? ''));
        $key = $slug !== '' ? $slug : $this->normalizeValue($name);

        if ($key === '') {
            return;
        }

        $existing = $selectedAddons->get($key, []);
        $quantity = max(1, (int) ($addon['quantity'] ?? 1));

        $selectedAddons->put($key, [
            'addon_id' => $addon['addon_id'] ?? ($existing['addon_id'] ?? null),
            'slug' => $slug !== '' ? $slug : ($existing['slug'] ?? Str::slug($name)),
            'name' => $name !== '' ? $name : ($existing['name'] ?? null),
            'quantity' => max((int) ($existing['quantity'] ?? 0), $quantity),
            'group' => $addon['group'] ?? ($existing['group'] ?? null),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $availableAddons
     * @param  array<string, mixed>  $selectedAddon
     * @return array<string, mixed>|null
     */
    protected function findMatchingAddon(Collection $availableAddons, array $selectedAddon): ?array
    {
        $addonId = $selectedAddon['addon_id'] ?? null;

        if ($addonId) {
            $matchedById = $availableAddons->firstWhere('id', (int) $addonId);

            if ($matchedById) {
                return $matchedById;
            }
        }

        $slug = (string) ($selectedAddon['slug'] ?? '');
        $name = (string) ($selectedAddon['name'] ?? '');

        return $availableAddons->first(function (array $availableAddon) use ($slug, $name) {
            return $this->normalizeValue($availableAddon['slug']) === $this->normalizeValue($slug)
                || $this->normalizeValue($availableAddon['name']) === $this->normalizeValue($name);
        });
    }

    protected function extractCustomEffectsTotal(array $orderData): float
    {
        $customEffects = Arr::get($orderData, 'extra_fields.custom_effects', []);

        if (is_string($customEffects)) {
            $decoded = json_decode($customEffects, true);
            $customEffects = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($customEffects)) {
            return 0;
        }

        return round(
            collect($customEffects)->sum(fn ($effect) => (float) Arr::get((array) $effect, 'price', 0)),
            2
        );
    }

    protected function normalizeValue(?string $value): string
    {
        return Str::of((string) $value)
            ->trim()
            ->lower()
            ->replace('&', 'and')
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->value();
    }
}

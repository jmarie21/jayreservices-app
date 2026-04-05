<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAddonGroupRequest;
use App\Http\Requests\Admin\StoreAddonRequest;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\StoreFormatPricingRequest;
use App\Http\Requests\Admin\StoreServiceRequest;
use App\Http\Requests\Admin\StoreSubStyleRequest;
use App\Http\Requests\Admin\UpdateAddonGroupRequest;
use App\Http\Requests\Admin\UpdateAddonRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Http\Requests\Admin\UpdateFormatPricingRequest;
use App\Http\Requests\Admin\UpdateServiceRequest;
use App\Http\Requests\Admin\UpdateSubStyleRequest;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Models\ServiceAddonAssignment;
use App\Models\ServiceAddonGroup;
use App\Models\ServiceCategory;
use App\Models\ServiceFormatPricing;
use App\Models\ServiceSubStyle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ServiceManagementController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/ServiceManagement', [
            'categories' => $this->categoryManagementPayload(),
            'services' => $this->serviceManagementPayload(),
        ]);
    }

    public function showServiceEditor(Service $service): Response
    {
        return Inertia::render('admin/ServiceEditor', [
            'service' => $this->serviceEditorPayload($service),
            'categories' => ServiceCategory::query()
                ->orderBy('sort_order')
                ->get(['id', 'name'])
                ->map(fn (ServiceCategory $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                ])
                ->all(),
        ]);
    }

    public function storeCategory(StoreCategoryRequest $request): RedirectResponse
    {
        ServiceCategory::create($this->prepareCategoryData($request->validated()));

        return back()->with('message', 'Category created successfully.');
    }

    public function updateCategory(UpdateCategoryRequest $request, ServiceCategory $category): RedirectResponse
    {
        $category->update($this->prepareCategoryData($request->validated()));

        return back()->with('message', 'Category updated successfully.');
    }

    public function destroyCategory(ServiceCategory $category): RedirectResponse
    {
        if ($category->services()->exists()) {
            return back()->withErrors([
                'category' => 'Deactivate or move the services in this category before deleting it.',
            ]);
        }

        $category->delete();

        return back()->with('message', 'Category deleted successfully.');
    }

    public function storeService(StoreServiceRequest $request): RedirectResponse
    {
        Service::create($this->prepareServiceData($request->validated()));

        return back()->with('message', 'Service created successfully.');
    }

    public function updateService(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $service->update($this->prepareServiceData($request->validated()));

        return back()->with('message', 'Service updated successfully.');
    }

    public function destroyService(Service $service): RedirectResponse
    {
        if ($service->projects()->exists()) {
            return back()->withErrors([
                'service' => 'This service already has projects. Set it inactive instead of deleting it.',
            ]);
        }

        $service->delete();

        return back()->with('message', 'Service deleted successfully.');
    }

    public function storeSubStyle(StoreSubStyleRequest $request): RedirectResponse
    {
        ServiceSubStyle::create($this->prepareSubStyleData($request->validated()));

        return back()->with('message', 'Sub-style created successfully.');
    }

    public function updateSubStyle(UpdateSubStyleRequest $request, ServiceSubStyle $subStyle): RedirectResponse
    {
        $subStyle->update($this->prepareSubStyleData($request->validated()));

        return back()->with('message', 'Sub-style updated successfully.');
    }

    public function destroySubStyle(ServiceSubStyle $subStyle): RedirectResponse
    {
        if ($subStyle->projects()->exists()) {
            return back()->withErrors([
                'sub_style' => 'This sub-style is already linked to projects. Set it inactive instead of deleting it.',
            ]);
        }

        $subStyle->delete();

        return back()->with('message', 'Sub-style deleted successfully.');
    }

    public function storeFormatPricing(StoreFormatPricingRequest $request): RedirectResponse
    {
        ServiceFormatPricing::create($request->validated());

        return back()->with('message', 'Format pricing created successfully.');
    }

    public function updateFormatPricing(UpdateFormatPricingRequest $request, ServiceFormatPricing $pricing): RedirectResponse
    {
        $pricing->update($request->validated());

        return back()->with('message', 'Format pricing updated successfully.');
    }

    public function destroyFormatPricing(ServiceFormatPricing $pricing): RedirectResponse
    {
        $pricing->delete();

        return back()->with('message', 'Format pricing deleted successfully.');
    }

    public function storeAddonGroup(StoreAddonGroupRequest $request): RedirectResponse
    {
        ServiceAddonGroup::create($this->prepareAddonGroupData($request->validated()));

        return back()->with('message', 'Add-on group created successfully.');
    }

    public function updateAddonGroup(UpdateAddonGroupRequest $request, ServiceAddonGroup $addonGroup): RedirectResponse
    {
        $addonGroup->update($this->prepareAddonGroupData($request->validated(), $addonGroup));

        return back()->with('message', 'Add-on group updated successfully.');
    }

    public function destroyAddonGroup(ServiceAddonGroup $addonGroup): RedirectResponse
    {
        $addonGroup->addons()->each(function (ServiceAddon $addon) {
            $addon->delete();
        });

        $addonGroup->delete();

        return back()->with('message', 'Add-on group deleted successfully.');
    }

    public function storeAddon(StoreAddonRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $group = ! empty($validated['service_addon_group_id'])
            ? ServiceAddonGroup::query()->findOrFail($validated['service_addon_group_id'])
            : null;

        $addon = ServiceAddon::create($this->prepareAddonData($validated, null, $group));

        if ($group) {
            $this->syncAddonAssignmentToService($addon, $group->service);
        }

        return back()->with('message', 'Addon created successfully.');
    }

    public function updateAddon(UpdateAddonRequest $request, ServiceAddon $addon): RedirectResponse
    {
        $validated = $request->validated();
        $group = ! empty($validated['service_addon_group_id'])
            ? ServiceAddonGroup::query()->findOrFail($validated['service_addon_group_id'])
            : null;

        $addon->update($this->prepareAddonData($validated, $addon, $group));

        if ($group) {
            $this->syncAddonAssignmentToService($addon, $group->service);
        }

        return back()->with('message', 'Addon updated successfully.');
    }

    public function destroyAddon(ServiceAddon $addon): RedirectResponse
    {
        $addon->delete();

        return back()->with('message', 'Addon deleted successfully.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function categoryManagementPayload(): array
    {
        return ServiceCategory::query()
            ->with(['services:id,service_category_id,features'])
            ->orderBy('sort_order')
            ->get()
            ->map(function (ServiceCategory $category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'video_link' => $category->video_link,
                    'thumbnail_url' => $category->thumbnail_url,
                    'sort_order' => $category->sort_order,
                    'is_active' => (bool) $category->is_active,
                    'services_count' => $category->services->count(),
                    'bullet_points_count' => $category->services->sum(fn (Service $service) => count($service->features ?? [])),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function serviceManagementPayload(): array
    {
        return Service::query()
            ->with(['category:id,name', 'subStyles:id,service_id', 'addonGroups:id,service_id'])
            ->orderBy('sort_order')
            ->get()
            ->map(function (Service $service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'slug' => $service->slug,
                    'video_link' => $service->video_link,
                    'thumbnail_url' => $service->thumbnail_url,
                    'sort_order' => $service->sort_order,
                    'is_active' => (bool) $service->is_active,
                    'styles_count' => $service->subStyles->count(),
                    'addon_groups_count' => $service->addonGroups->count(),
                    'category' => $service->category ? [
                        'id' => $service->category->id,
                        'name' => $service->category->name,
                    ] : null,
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function serviceEditorPayload(Service $service): array
    {
        $service->load([
            'category:id,name',
            'subStyles.formatPricing',
            'addonGroups.addons',
        ]);

        return [
            'id' => $service->id,
            'name' => $service->name,
            'slug' => $service->slug,
            'description' => $service->description,
            'video_link' => $service->video_link,
            'thumbnail_url' => $service->thumbnail_url,
            'sort_order' => $service->sort_order,
            'is_active' => (bool) $service->is_active,
            'features' => collect($service->features ?? [])->values()->all(),
            'category' => $service->category ? [
                'id' => $service->category->id,
                'name' => $service->category->name,
            ] : null,
            'sub_styles' => $service->subStyles
                ->sortBy('sort_order')
                ->values()
                ->map(function (ServiceSubStyle $subStyle) {
                    return [
                        'id' => $subStyle->id,
                        'name' => $subStyle->name,
                        'slug' => $subStyle->slug,
                        'sort_order' => $subStyle->sort_order,
                        'is_active' => (bool) $subStyle->is_active,
                        'format_pricing' => $subStyle->formatPricing
                            ->sortBy('sort_order')
                            ->values()
                            ->map(fn (ServiceFormatPricing $pricing) => [
                                'id' => $pricing->id,
                                'format_name' => $pricing->format_name,
                                'format_label' => $pricing->format_label,
                                'client_price' => (float) $pricing->client_price,
                                'editor_price' => (float) $pricing->editor_price,
                                'sort_order' => $pricing->sort_order,
                            ])
                            ->all(),
                    ];
                })
                ->all(),
            'addon_groups' => $service->addonGroups
                ->sortBy('sort_order')
                ->values()
                ->map(function (ServiceAddonGroup $group) {
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
                            ->sortBy('sort_order')
                            ->values()
                            ->map(function (ServiceAddon $addon) {
                                return [
                                    'id' => $addon->id,
                                    'name' => $addon->name,
                                    'slug' => $addon->slug,
                                    'client_price' => (float) $addon->client_price,
                                    'editor_price' => (float) $addon->editor_price,
                                    'sample_link' => $addon->sample_link,
                                    'sort_order' => $addon->sort_order,
                                    'has_quantity' => (bool) $addon->has_quantity,
                                    'is_rush_option' => (bool) $addon->is_rush_option,
                                    'is_active' => (bool) $addon->is_active,
                                ];
                            })
                            ->all(),
                    ];
                })
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareCategoryData(array $data): array
    {
        $data['slug'] = Str::slug((string) (($data['slug'] ?? null) ?: $data['name']));
        $data['video_link'] = ($data['video_link'] ?? null) ?: null;
        $data['thumbnail_url'] = ($data['thumbnail_url'] ?? null) ?: null;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareServiceData(array $data): array
    {
        $data['slug'] = Str::slug((string) (($data['slug'] ?? null) ?: $data['name']));
        $data['video_link'] = ($data['video_link'] ?? null) ?: null;
        $data['thumbnail_url'] = ($data['thumbnail_url'] ?? null) ?: null;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        if (array_key_exists('features', $data)) {
            $data['features'] = collect($data['features'] ?? [])
                ->map(fn ($feature) => trim((string) $feature))
                ->filter()
                ->values()
                ->all();
        } else {
            unset($data['features']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareSubStyleData(array $data): array
    {
        $data['slug'] = Str::slug((string) (($data['slug'] ?? null) ?: $data['name']));
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareAddonGroupData(array $data, ?ServiceAddonGroup $addonGroup = null): array
    {
        $serviceId = (int) $data['service_id'];
        $preferredSlug = (string) (($data['slug'] ?? null) ?: $data['label']);

        $data['slug'] = $this->makeUniqueAddonGroupSlug($serviceId, $preferredSlug, $addonGroup?->id);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_required'] = (bool) ($data['is_required'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['helper_text'] = ($data['helper_text'] ?? null) ?: null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareAddonData(array $data, ?ServiceAddon $addon = null, ?ServiceAddonGroup $group = null): array
    {
        $group ??= ! empty($data['service_addon_group_id'])
            ? ServiceAddonGroup::query()->find($data['service_addon_group_id'])
            : null;

        $preferredSlug = (string) (($data['slug'] ?? null) ?: $data['name']);

        $data['slug'] = $this->makeUniqueAddonSlug($preferredSlug, $addon?->id);
        $data['service_addon_group_id'] = $group?->id ?? ($data['service_addon_group_id'] ?? null);
        $data['group'] = $group?->slug ?? (($data['group'] ?? null) ?: null);
        $data['addon_type'] = ! empty($data['has_quantity']) ? 'quantity' : ($data['addon_type'] ?? 'boolean');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['has_quantity'] = (bool) ($data['has_quantity'] ?? false);
        $data['is_rush_option'] = (bool) ($data['is_rush_option'] ?? false);
        $data['sample_link'] = ($data['sample_link'] ?? null) ?: null;

        return $data;
    }

    protected function syncAddonAssignmentToService(ServiceAddon $addon, Service $service): void
    {
        $addon->assignments()
            ->where('assignable_type', Service::class)
            ->where('assignable_id', '!=', $service->id)
            ->delete();

        ServiceAddonAssignment::updateOrCreate(
            [
                'service_addon_id' => $addon->id,
                'assignable_type' => Service::class,
                'assignable_id' => $service->id,
            ],
            [
                'client_price_override' => null,
                'editor_price_override' => null,
            ]
        );
    }

    protected function makeUniqueAddonSlug(string $preferredSlug, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($preferredSlug) ?: 'addon-option';
        $candidate = $baseSlug;
        $counter = 2;

        while (
            ServiceAddon::query()
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->where('slug', $candidate)
                ->exists()
        ) {
            $candidate = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $candidate;
    }

    protected function makeUniqueAddonGroupSlug(int $serviceId, string $preferredSlug, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($preferredSlug) ?: 'addon-group';
        $candidate = $baseSlug;
        $counter = 2;

        while (
            ServiceAddonGroup::query()
                ->where('service_id', $serviceId)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->where('slug', $candidate)
                ->exists()
        ) {
            $candidate = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $candidate;
    }
}

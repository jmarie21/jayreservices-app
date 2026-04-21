<?php

use App\Models\Service;
use App\Models\ServiceAddon;
use App\Models\ServiceAddonAssignment;
use App\Models\ServiceAddonGroup;
use App\Models\ServiceCategory;
use App\Models\ServiceFormatPricing;
use App\Models\ServiceSubStyle;
use App\Services\PricingService;

beforeEach(function () {
    $this->category = ServiceCategory::create([
        'name' => 'Real Estate Services',
        'slug' => 'real-estate',
        'description' => 'Test',
        'icon' => 'Hotel',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->service = Service::create([
        'name' => 'Real Estate Premium Style',
        'slug' => 'premium-style',
        'service_category_id' => $this->category->id,
        'sort_order' => 3,
        'is_active' => true,
        'features' => [],
        'price' => 0,
    ]);

    $subStyle = ServiceSubStyle::create([
        'service_id' => $this->service->id,
        'name' => 'Premium Video',
        'slug' => 'premium-video',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    ServiceFormatPricing::create([
        'service_sub_style_id' => $subStyle->id,
        'format_name' => 'horizontal',
        'format_label' => 'Horizontal',
        'client_price' => 80,
        'editor_price' => 1500,
        'sort_order' => 1,
    ]);

    // Base addons (old-style)
    $withAgent = ServiceAddon::create(['name' => 'With Agent', 'slug' => 'with-agent', 'addon_type' => 'boolean', 'client_price' => 10, 'editor_price' => 100, 'has_quantity' => false, 'is_rush_option' => false, 'sort_order' => 1, 'is_active' => true]);
    $perProperty = ServiceAddon::create(['name' => 'Per Property Line', 'slug' => 'per-property-line', 'addon_type' => 'quantity', 'client_price' => 5, 'editor_price' => 100, 'has_quantity' => true, 'is_rush_option' => false, 'sort_order' => 2, 'is_active' => true]);
    $rush = ServiceAddon::create(['name' => 'Rush', 'slug' => 'rush', 'addon_type' => 'boolean', 'client_price' => 10, 'editor_price' => 200, 'has_quantity' => false, 'is_rush_option' => true, 'sort_order' => 3, 'is_active' => true]);
    $paintingTransition = ServiceAddon::create(['name' => 'Painting Transition', 'slug' => 'painting-transition', 'addon_type' => 'quantity', 'client_price' => 10, 'editor_price' => 150, 'has_quantity' => true, 'group' => 'effects', 'is_rush_option' => false, 'sort_order' => 14, 'is_active' => true]);
    $text3d = ServiceAddon::create(['name' => '3D Text behind the Agent Talking', 'slug' => '3d-text-behind-the-agent-talking', 'addon_type' => 'boolean', 'client_price' => 10, 'editor_price' => 350, 'has_quantity' => false, 'group' => 'captions', 'is_rush_option' => false, 'sort_order' => 20, 'is_active' => true]);

    // Category-level assignments (with-agent, per-property-line)
    ServiceAddonAssignment::create(['service_addon_id' => $withAgent->id, 'assignable_type' => ServiceCategory::class, 'assignable_id' => $this->category->id]);
    ServiceAddonAssignment::create(['service_addon_id' => $perProperty->id, 'assignable_type' => ServiceCategory::class, 'assignable_id' => $this->category->id]);

    // Service-level assignments (old-style: rush with override, painting, 3d-text)
    ServiceAddonAssignment::create(['service_addon_id' => $rush->id, 'assignable_type' => Service::class, 'assignable_id' => $this->service->id, 'client_price_override' => 20, 'editor_price_override' => 500]);
    ServiceAddonAssignment::create(['service_addon_id' => $paintingTransition->id, 'assignable_type' => Service::class, 'assignable_id' => $this->service->id]);
    ServiceAddonAssignment::create(['service_addon_id' => $text3d->id, 'assignable_type' => Service::class, 'assignable_id' => $this->service->id]);

    // Structured addon groups with cloned service-specific addons
    $rushGroup = ServiceAddonGroup::create(['service_id' => $this->service->id, 'label' => 'Rush', 'slug' => 'rush', 'input_type' => 'dropdown', 'sort_order' => 20, 'is_required' => false, 'is_active' => true]);
    $effectsGroup = ServiceAddonGroup::create(['service_id' => $this->service->id, 'label' => 'Effects', 'slug' => 'effects', 'input_type' => 'checkbox_group', 'sort_order' => 30, 'is_required' => false, 'is_active' => true]);
    $captionsGroup = ServiceAddonGroup::create(['service_id' => $this->service->id, 'label' => 'Captions', 'slug' => 'captions', 'input_type' => 'checkbox_group', 'sort_order' => 40, 'is_required' => false, 'is_active' => true]);

    $serviceId = $this->service->id;

    $clonedWithAgent = ServiceAddon::create(['service_addon_group_id' => null, 'name' => 'With Agent', 'slug' => "service-{$serviceId}-with-agent", 'addon_type' => 'boolean', 'client_price' => 10, 'editor_price' => 100, 'has_quantity' => false, 'is_rush_option' => false, 'sort_order' => 0, 'is_active' => true]);
    $clonedPerProperty = ServiceAddon::create(['service_addon_group_id' => null, 'name' => 'Per Property Line', 'slug' => "service-{$serviceId}-per-property-line", 'addon_type' => 'quantity', 'client_price' => 5, 'editor_price' => 100, 'has_quantity' => true, 'is_rush_option' => false, 'sort_order' => 0, 'is_active' => true]);
    $clonedRush = ServiceAddon::create(['service_addon_group_id' => $rushGroup->id, 'name' => 'Rush', 'slug' => "service-{$serviceId}-rush", 'addon_type' => 'boolean', 'client_price' => 20, 'editor_price' => 500, 'has_quantity' => false, 'is_rush_option' => true, 'sort_order' => 0, 'is_active' => true]);
    $clonedPainting = ServiceAddon::create(['service_addon_group_id' => $effectsGroup->id, 'name' => 'Painting Transition', 'slug' => "service-{$serviceId}-painting-transition", 'addon_type' => 'quantity', 'client_price' => 10, 'editor_price' => 150, 'has_quantity' => true, 'group' => 'effects', 'is_rush_option' => false, 'sort_order' => 14, 'is_active' => true]);
    $clonedText3d = ServiceAddon::create(['service_addon_group_id' => $captionsGroup->id, 'name' => '3D Text behind the Agent Talking', 'slug' => "service-{$serviceId}-3d-text-behind-the-agent-talking", 'addon_type' => 'boolean', 'client_price' => 10, 'editor_price' => 350, 'has_quantity' => false, 'group' => 'captions', 'is_rush_option' => false, 'sort_order' => 20, 'is_active' => true]);

    foreach ([$clonedWithAgent, $clonedPerProperty, $clonedRush, $clonedPainting, $clonedText3d] as $addon) {
        ServiceAddonAssignment::create(['service_addon_id' => $addon->id, 'assignable_type' => Service::class, 'assignable_id' => $this->service->id]);
    }

    $this->pricingService = app(PricingService::class);
    $this->serviceId = $this->service->id;
    $this->clonedRushId = $clonedRush->id;
    $this->clonedWithAgentId = $clonedWithAgent->id;
    $this->clonedPerPropertyId = $clonedPerProperty->id;
    $this->clonedPaintingId = $clonedPainting->id;
    $this->clonedText3dId = $clonedText3d->id;
});

it('does not double-count effects and captions already present in service_addons', function () {
    // Mirrors the exact scenario from the bug report:
    // Real Estate Premium Style, Premium Video, Horizontal ($80)
    // + With Agent ($10) + Per Property Line ($5) + Rush ($20) + Painting Transition ($10) + 3D Text ($10)
    // Expected: $135 (not $155)
    $serviceId = $this->serviceId;

    $orderData = [
        'service_id' => $serviceId,
        'service_sub_style_id' => null,
        'style' => 'Premium Video',
        'format' => 'horizontal',
        'with_agent' => true,
        'rush' => true,
        'per_property' => true,
        'per_property_count' => 1,
        'extra_fields' => [
            'service_addons' => [
                ['addon_id' => $this->clonedWithAgentId, 'slug' => "service-{$serviceId}-with-agent", 'name' => 'With Agent', 'quantity' => 1, 'group' => null],
                ['addon_id' => $this->clonedPerPropertyId, 'slug' => "service-{$serviceId}-per-property-line", 'name' => 'Per Property Line', 'quantity' => 1, 'group' => null],
                ['addon_id' => $this->clonedRushId, 'slug' => "service-{$serviceId}-rush", 'name' => 'Rush', 'quantity' => 1, 'group' => null],
                ['addon_id' => $this->clonedPaintingId, 'slug' => "service-{$serviceId}-painting-transition", 'name' => 'Painting Transition', 'quantity' => 1, 'group' => 'effects'],
                ['addon_id' => $this->clonedText3dId, 'slug' => "service-{$serviceId}-3d-text-behind-the-agent-talking", 'name' => '3D Text behind the Agent Talking', 'quantity' => 1, 'group' => 'captions'],
            ],
            // Vue buildExtraFields also sends these redundant arrays:
            'effects' => [['id' => 'Painting Transition', 'quantity' => 1]],
            'captions' => ['3D Text behind the Agent Talking'],
        ],
    ];

    $result = $this->pricingService->calculatePrices($orderData);

    expect($result['client_total'])->toBe(135.0);
});

it('still counts effects correctly when not present in service_addons', function () {
    // Backward-compat: old orders have effects/captions but no service_addons
    $orderData = [
        'service_id' => $this->serviceId,
        'style' => 'Premium Video',
        'format' => 'horizontal',
        'with_agent' => false,
        'rush' => false,
        'per_property' => false,
        'extra_fields' => [
            'service_addons' => [],
            'effects' => [['id' => 'Painting Transition', 'quantity' => 1]],
            'captions' => ['3D Text behind the Agent Talking'],
        ],
    ];

    $result = $this->pricingService->calculatePrices($orderData);

    // $80 base + $10 painting + $10 3d-text = $100
    expect($result['client_total'])->toBe(100.0);
});

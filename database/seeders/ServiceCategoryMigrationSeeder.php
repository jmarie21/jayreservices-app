<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Models\ServiceAddonAssignment;
use App\Models\ServiceAddonGroup;
use App\Models\ServiceCategory;
use App\Models\ServiceFormatPricing;
use App\Models\ServiceSubStyle;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ServiceCategoryMigrationSeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect($this->serviceCatalog())
            ->mapWithKeys(function (array $categoryData) {
                $category = ServiceCategory::updateOrCreate(
                    ['slug' => $categoryData['slug']],
                    [
                        'name' => $categoryData['name'],
                        'description' => $categoryData['description'],
                        'icon' => $categoryData['icon'],
                        'sort_order' => $categoryData['sort_order'],
                        'is_active' => true,
                    ]
                );

                return [$categoryData['slug'] => $category];
            });

        foreach ($this->serviceCatalog() as $categoryData) {
            /** @var ServiceCategory $category */
            $category = $categories[$categoryData['slug']];

            foreach ($categoryData['services'] as $serviceData) {
                $service = Service::updateOrCreate(
                    ['name' => $serviceData['name']],
                    [
                        'service_category_id' => $category->id,
                        'slug' => $serviceData['slug'],
                        'sort_order' => $serviceData['sort_order'],
                        'is_active' => true,
                    ]
                );

                foreach ($serviceData['sub_styles'] as $subStyleData) {
                    $subStyle = ServiceSubStyle::updateOrCreate(
                        [
                            'service_id' => $service->id,
                            'slug' => $subStyleData['slug'],
                        ],
                        [
                            'name' => $subStyleData['name'],
                            'sort_order' => $subStyleData['sort_order'],
                            'is_active' => true,
                        ]
                    );

                    foreach ($subStyleData['format_pricing'] as $pricingData) {
                        ServiceFormatPricing::updateOrCreate(
                            [
                                'service_sub_style_id' => $subStyle->id,
                                'format_name' => $pricingData['format_name'],
                            ],
                            [
                                'format_label' => $pricingData['format_label'],
                                'client_price' => $pricingData['client_price'],
                                'editor_price' => $pricingData['editor_price'],
                                'sort_order' => $pricingData['sort_order'],
                            ]
                        );
                    }
                }
            }
        }

        $addons = collect($this->addonCatalog())
            ->mapWithKeys(function (array $addonData) {
                $addon = ServiceAddon::updateOrCreate(
                    ['slug' => $addonData['slug']],
                    [
                        'name' => $addonData['name'],
                        'addon_type' => $addonData['addon_type'],
                        'client_price' => $addonData['client_price'],
                        'editor_price' => $addonData['editor_price'],
                        'has_quantity' => $addonData['has_quantity'],
                        'sample_link' => $addonData['sample_link'],
                        'group' => $addonData['group'],
                        'sort_order' => $addonData['sort_order'],
                        'is_active' => true,
                    ]
                );

                return [$addonData['slug'] => $addon];
            });

        foreach (['real-estate', 'wedding', 'event', 'construction'] as $categorySlug) {
            $this->assignAddon($addons['with-agent'], $categories[$categorySlug]);
            $this->assignAddon($addons['per-property-line'], $categories[$categorySlug]);
        }

        $servicesByName = Service::query()
            ->with('category')
            ->get()
            ->keyBy('name');

        foreach ($this->serviceAssignments() as $serviceName => $assignmentSlugs) {
            /** @var Service|null $service */
            $service = $servicesByName->get($serviceName);

            if (! $service) {
                continue;
            }

            foreach ($assignmentSlugs as $assignment) {
                $this->assignAddon(
                    $addons[$assignment['slug']],
                    $service,
                    $assignment['client_price_override'] ?? null,
                    $assignment['editor_price_override'] ?? null,
                );
            }
        }

        $this->seedStructuredAddonGroups($servicesByName, $addons);

        Project::query()
            ->whereNull('service_sub_style_id')
            ->whereNotNull('style')
            ->get()
            ->each(function (Project $project) {
                $service = Service::query()
                    ->with('subStyles')
                    ->find($project->service_id);

                if (! $service) {
                    return;
                }

                $normalizedStyle = $this->normalizeValue($project->style);
                $subStyle = $service->subStyles->first(function (ServiceSubStyle $subStyle) use ($normalizedStyle) {
                    return $this->normalizeValue($subStyle->name) === $normalizedStyle
                        || $this->normalizeValue($subStyle->slug) === $normalizedStyle;
                });

                if ($subStyle) {
                    $project->update([
                        'service_sub_style_id' => $subStyle->id,
                    ]);
                }
            });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function serviceCatalog(): array
    {
        return [
            [
                'name' => 'Real Estate Services',
                'slug' => 'real-estate',
                'description' => 'Property marketing edits from simple walkthroughs to premium cinematic packages.',
                'icon' => 'Hotel',
                'sort_order' => 1,
                'services' => [
                    [
                        'name' => 'Real Estate Basic Style',
                        'slug' => 'basic-style',
                        'sort_order' => 1,
                        'sub_styles' => [
                            $this->subStyle('Basic Video', 'basic-video', 1, [
                                $this->formatPricing('horizontal', 'Horizontal', 40, 500, 1),
                                $this->formatPricing('vertical', 'Vertical', 25, 350, 2),
                                $this->formatPricing('horizontal and vertical package', 'Horizontal and Vertical Package', 65, 850, 3),
                            ]),
                            $this->subStyle('Basic Drone Only', 'basic-drone-only', 2, [
                                $this->formatPricing('horizontal', 'Horizontal', 25, 350, 1),
                                $this->formatPricing('vertical', 'Vertical', 20, 300, 2),
                                $this->formatPricing('horizontal and vertical package', 'Horizontal and Vertical Package', 45, 650, 3),
                            ]),
                        ],
                    ],
                    [
                        'name' => 'Real Estate Deluxe Style',
                        'slug' => 'deluxe-style',
                        'sort_order' => 2,
                        'sub_styles' => [
                            $this->subStyle('Deluxe Video', 'deluxe-video', 1, [
                                $this->formatPricing('horizontal', 'Horizontal', 60, 1000, 1),
                                $this->formatPricing('vertical', 'Vertical', 35, 700, 2),
                                $this->formatPricing('horizontal and vertical package', 'Horizontal and Vertical Package', 95, 1700, 3),
                            ]),
                            $this->subStyle('Deluxe Drone Only', 'deluxe-drone-only', 2, [
                                $this->formatPricing('horizontal', 'Horizontal', 35, 500, 1),
                                $this->formatPricing('vertical', 'Vertical', 30, 400, 2),
                                $this->formatPricing('horizontal and vertical package', 'Horizontal and Vertical Package', 65, 900, 3),
                            ]),
                        ],
                    ],
                    [
                        'name' => 'Real Estate Premium Style',
                        'slug' => 'premium-style',
                        'sort_order' => 3,
                        'sub_styles' => [
                            $this->subStyle('Premium Video', 'premium-video', 1, [
                                $this->formatPricing('horizontal', 'Horizontal', 80, 1500, 1),
                                $this->formatPricing('vertical', 'Vertical', 50, 1200, 2),
                                $this->formatPricing('horizontal and vertical package', 'Horizontal and Vertical Package', 130, 2700, 3),
                            ]),
                            $this->subStyle('Premium Drone Only', 'premium-drone-only', 2, [
                                $this->formatPricing('horizontal', 'Horizontal', 45, 800, 1),
                                $this->formatPricing('vertical', 'Vertical', 40, 600, 2),
                                $this->formatPricing('horizontal and vertical package', 'Horizontal and Vertical Package', 85, 1400, 3),
                            ]),
                        ],
                    ],
                    [
                        'name' => 'Real Estate Luxury Style',
                        'slug' => 'luxury-style',
                        'sort_order' => 4,
                        'sub_styles' => [
                            $this->subStyle('Luxury Video', 'luxury-video', 1, [
                                $this->formatPricing('horizontal', 'Horizontal', 100, 1800, 1),
                                $this->formatPricing('vertical', 'Vertical', 70, 1500, 2),
                                $this->formatPricing('horizontal and vertical package', 'Horizontal and Vertical Package', 170, 3300, 3),
                            ]),
                            $this->subStyle('Luxury Drone Only', 'luxury-drone-only', 2, [
                                $this->formatPricing('horizontal', 'Horizontal', 60, 1000, 1),
                                $this->formatPricing('vertical', 'Vertical', 50, 800, 2),
                                $this->formatPricing('horizontal and vertical package', 'Horizontal and Vertical Package', 110, 1800, 3),
                            ]),
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Talking Heads Services',
                'slug' => 'talking-heads',
                'description' => 'Short-form talking head edits with creator-style pacing and optional premium overlays.',
                'icon' => 'Laugh',
                'sort_order' => 2,
                'services' => [
                    [
                        'name' => 'Talking Heads',
                        'slug' => 'talking-heads',
                        'sort_order' => 1,
                        'sub_styles' => [
                            $this->subStyle('Hormozi Style', 'hormozi-style', 1, [
                                $this->formatPricing('horizontal', 'Horizontal', 40, 0, 1),
                                $this->formatPricing('vertical', 'Vertical', 30, 0, 2),
                            ]),
                            $this->subStyle('Ali Abdaal Style', 'ali-abdaal-style', 2, [
                                $this->formatPricing('horizontal', 'Horizontal', 40, 0, 1),
                                $this->formatPricing('vertical', 'Vertical', 30, 0, 2),
                            ]),
                        ],
                    ],
                    [
                        'name' => 'Horsemen Style',
                        'slug' => 'horsemen-style',
                        'sort_order' => 2,
                        'sub_styles' => [
                            $this->subStyle('Horsemen Style', 'horsemen-style', 1, [
                                $this->formatPricing('horizontal', 'Horizontal', 40, 0, 1),
                                $this->formatPricing('vertical', 'Vertical', 30, 0, 2),
                            ]),
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Wedding Services',
                'slug' => 'wedding',
                'description' => 'Wedding highlight edits ranging from quick reels to longer cinematic stories.',
                'icon' => 'Church',
                'sort_order' => 3,
                'services' => [
                    [
                        'name' => 'Wedding Basic Style',
                        'slug' => 'basic-style',
                        'sort_order' => 1,
                        'sub_styles' => [
                            $this->subStyle('Basic Video', 'basic-video', 1, [
                                $this->formatPricing('horizontal', 'Horizontal', 50, 500, 1),
                                $this->formatPricing('vertical', 'Vertical', 50, 350, 2),
                                $this->formatPricing('horizontal and vertical package', 'Horizontal and Vertical Package', 100, 850, 3),
                            ]),
                        ],
                    ],
                    [
                        'name' => 'Wedding Premium Style',
                        'slug' => 'premium-style',
                        'sort_order' => 2,
                        'sub_styles' => [
                            $this->subStyle('Premium Video', 'premium-video', 1, [
                                $this->formatPricing('horizontal', 'Horizontal', 100, 1500, 1),
                                $this->formatPricing('vertical', 'Vertical', 100, 1200, 2),
                                $this->formatPricing('horizontal and vertical package', 'Horizontal and Vertical Package', 200, 2700, 3),
                            ]),
                        ],
                    ],
                    [
                        'name' => 'Wedding Luxury Style',
                        'slug' => 'luxury-style',
                        'sort_order' => 3,
                        'sub_styles' => [
                            $this->subStyle('Luxury Video', 'luxury-video', 1, [
                                $this->formatPricing('horizontal', 'Horizontal', 150, 1800, 1),
                                $this->formatPricing('vertical', 'Vertical', 150, 1500, 2),
                                $this->formatPricing('horizontal and vertical package', 'Horizontal and Vertical Package', 300, 3300, 3),
                            ]),
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Event Services',
                'slug' => 'event',
                'description' => 'Fast-turn event edits for highlights, recaps, and polished premium social deliverables.',
                'icon' => 'PartyPopper',
                'sort_order' => 4,
                'services' => [
                    [
                        'name' => 'Event Basic Style',
                        'slug' => 'basic-style',
                        'sort_order' => 1,
                        'sub_styles' => [
                            $this->subStyle('Basic Video', 'basic-video', 1, [
                                $this->formatPricing('horizontal', 'Horizontal', 50, 500, 1),
                                $this->formatPricing('vertical', 'Vertical', 50, 350, 2),
                                $this->formatPricing('horizontal and vertical package', 'Horizontal and Vertical Package', 100, 850, 3),
                            ]),
                        ],
                    ],
                    [
                        'name' => 'Event Premium Style',
                        'slug' => 'premium-style',
                        'sort_order' => 2,
                        'sub_styles' => [
                            $this->subStyle('Premium Video', 'premium-video', 1, [
                                $this->formatPricing('horizontal', 'Horizontal', 80, 1500, 1),
                                $this->formatPricing('vertical', 'Vertical', 80, 1200, 2),
                                $this->formatPricing('horizontal and vertical package', 'Horizontal and Vertical Package', 160, 2700, 3),
                            ]),
                        ],
                    ],
                    [
                        'name' => 'Event Luxury Style',
                        'slug' => 'luxury-style',
                        'sort_order' => 3,
                        'sub_styles' => [
                            $this->subStyle('Luxury Video', 'luxury-video', 1, [
                                $this->formatPricing('horizontal', 'Horizontal', 120, 1800, 1),
                                $this->formatPricing('vertical', 'Vertical', 120, 1500, 2),
                                $this->formatPricing('horizontal and vertical package', 'Horizontal and Vertical Package', 240, 3300, 3),
                            ]),
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Construction Services',
                'slug' => 'construction',
                'description' => 'Construction and progress-update edits for sites, builds, and branded marketing recaps.',
                'icon' => 'Construction',
                'sort_order' => 5,
                'services' => [
                    [
                        'name' => 'Construction Basic Style',
                        'slug' => 'basic-style',
                        'sort_order' => 1,
                        'sub_styles' => [
                            $this->subStyle('Basic Video', 'basic-video', 1, [
                                $this->formatPricing('horizontal', 'Horizontal', 50, 500, 1),
                                $this->formatPricing('vertical', 'Vertical', 50, 350, 2),
                                $this->formatPricing('horizontal and vertical package', 'Horizontal and Vertical Package', 100, 850, 3),
                            ]),
                        ],
                    ],
                    [
                        'name' => 'Construction Premium Style',
                        'slug' => 'premium-style',
                        'sort_order' => 2,
                        'sub_styles' => [
                            $this->subStyle('Premium Video', 'premium-video', 1, [
                                $this->formatPricing('horizontal', 'Horizontal', 80, 1500, 1),
                                $this->formatPricing('vertical', 'Vertical', 80, 1200, 2),
                                $this->formatPricing('horizontal and vertical package', 'Horizontal and Vertical Package', 160, 2700, 3),
                            ]),
                        ],
                    ],
                    [
                        'name' => 'Construction Luxury Style',
                        'slug' => 'luxury-style',
                        'sort_order' => 3,
                        'sub_styles' => [
                            $this->subStyle('Luxury Video', 'luxury-video', 1, [
                                $this->formatPricing('horizontal', 'Horizontal', 120, 1800, 1),
                                $this->formatPricing('vertical', 'Vertical', 120, 1500, 2),
                                $this->formatPricing('horizontal and vertical package', 'Horizontal and Vertical Package', 240, 3300, 3),
                            ]),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function addonCatalog(): array
    {
        return [
            $this->addon('With Agent', 'with-agent', 'boolean', 10, 100, false, null, null, 1),
            $this->addon('Per Property Line', 'per-property-line', 'quantity', 5, 100, true, null, null, 2),
            $this->addon('Rush', 'rush', 'boolean', 10, 200, false, null, null, 3),
            $this->addon('Ken Burns', 'ken-burns', 'boolean', 0, 0, false, 'https://www.youtube.com/watch?v=lIK2S0eIvwY&list=TLGG7aKmePKcyR8xMzEwMjAyNQ', 'effects', 10),
            $this->addon('Building A House Transition', 'building-a-house-transition', 'boolean', 0, 0, false, 'https://www.youtube.com/watch?v=ERrkbiFAOow', 'effects', 11),
            $this->addon('House Drop', 'house-drop', 'boolean', 0, 0, false, 'https://youtu.be/3vVfB8AZkMw', 'effects', 12),
            $this->addon('Pillar Masking', 'pillar-masking', 'boolean', 0, 0, false, 'https://www.youtube.com/watch?v=byh1nKAE3Pk&list=TLGG_YXdMMvhwfsxMzEwMjAyNQ&t=2s', 'effects', 13),
            $this->addon('Painting Transition', 'painting-transition', 'quantity', 10, 150, true, 'https://youtu.be/vCW4H7puU1c?si=GoI72aCscroTvYqk', 'effects', 14),
            $this->addon('Earth Zoom Transition', 'earth-zoom-transition', 'quantity', 15, 150, true, 'https://www.youtube.com/watch?v=dyuRMbjDJas&feature=youtu.be', 'effects', 15),
            $this->addon('Day to Night AI', 'day-to-night-ai', 'quantity', 15, 150, true, 'https://youtu.be/OPpyyb77ijs?si=q-IjufGmarVw8kMu', 'effects', 16),
            $this->addon('Virtual Staging AI', 'virtual-staging-ai', 'quantity', 20, 300, true, 'https://youtu.be/79vg5WqKgYE?si=TkXflrhPmUfTAQFX', 'effects', 17),
            $this->addon('3D Text behind the Agent Talking', '3d-text-behind-the-agent-talking', 'boolean', 10, 350, false, null, 'captions', 20),
            $this->addon('Captions while the agent is talking', 'captions-while-the-agent-is-talking', 'boolean', 10, 200, false, null, 'captions', 21),
            $this->addon('3D Text tracked on the ground etc.', '3d-text-tracked-on-the-ground-etc', 'boolean', 15, 400, false, null, 'captions', 22),
            $this->addon('3D Graphics together with text', '3d-graphics-together-with-text', 'boolean', 20, 500, false, null, 'captions', 23),
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function serviceAssignments(): array
    {
        return [
            'Real Estate Basic Style' => [
                $this->assignment('rush', 10, 200),
            ],
            'Real Estate Deluxe Style' => [
                $this->assignment('rush', 10, 200),
                $this->assignment('ken-burns'),
            ],
            'Real Estate Premium Style' => [
                $this->assignment('rush', 20, 500),
                $this->assignment('ken-burns'),
                $this->assignment('building-a-house-transition'),
                $this->assignment('painting-transition'),
                $this->assignment('earth-zoom-transition'),
                $this->assignment('3d-text-behind-the-agent-talking'),
                $this->assignment('captions-while-the-agent-is-talking'),
            ],
            'Real Estate Luxury Style' => [
                $this->assignment('rush', 20, 500),
                $this->assignment('ken-burns'),
                $this->assignment('house-drop'),
                $this->assignment('pillar-masking'),
                $this->assignment('painting-transition'),
                $this->assignment('earth-zoom-transition'),
                $this->assignment('day-to-night-ai'),
                $this->assignment('virtual-staging-ai'),
                $this->assignment('3d-text-behind-the-agent-talking'),
                $this->assignment('captions-while-the-agent-is-talking'),
                $this->assignment('3d-text-tracked-on-the-ground-etc'),
                $this->assignment('3d-graphics-together-with-text'),
            ],
            'Talking Heads' => [
                $this->assignment('rush', 5, 0),
            ],
            'Horsemen Style' => [
                $this->assignment('rush', 5, 0),
                $this->assignment('day-to-night-ai'),
                $this->assignment('virtual-staging-ai'),
                $this->assignment('3d-text-behind-the-agent-talking'),
                $this->assignment('captions-while-the-agent-is-talking'),
                $this->assignment('3d-text-tracked-on-the-ground-etc'),
                $this->assignment('3d-graphics-together-with-text'),
            ],
            'Wedding Basic Style' => [
                $this->assignment('rush', 10, 200),
            ],
            'Wedding Premium Style' => [
                $this->assignment('rush', 20, 500),
                $this->assignment('ken-burns'),
                $this->assignment('building-a-house-transition'),
                $this->assignment('painting-transition'),
                $this->assignment('earth-zoom-transition'),
            ],
            'Wedding Luxury Style' => [
                $this->assignment('rush', 20, 500),
                $this->assignment('ken-burns'),
                $this->assignment('house-drop'),
                $this->assignment('pillar-masking'),
                $this->assignment('painting-transition'),
                $this->assignment('earth-zoom-transition'),
                $this->assignment('day-to-night-ai'),
                $this->assignment('3d-text-behind-the-agent-talking'),
                $this->assignment('captions-while-the-agent-is-talking'),
                $this->assignment('3d-text-tracked-on-the-ground-etc'),
            ],
            'Event Basic Style' => [
                $this->assignment('rush', 10, 200),
            ],
            'Event Premium Style' => [
                $this->assignment('rush', 20, 500),
                $this->assignment('ken-burns'),
                $this->assignment('building-a-house-transition'),
                $this->assignment('painting-transition'),
                $this->assignment('earth-zoom-transition'),
                $this->assignment('3d-text-behind-the-agent-talking'),
                $this->assignment('captions-while-the-agent-is-talking'),
            ],
            'Event Luxury Style' => [
                $this->assignment('rush', 20, 500),
                $this->assignment('ken-burns'),
                $this->assignment('house-drop'),
                $this->assignment('pillar-masking'),
                $this->assignment('painting-transition'),
                $this->assignment('earth-zoom-transition'),
                $this->assignment('day-to-night-ai'),
                $this->assignment('3d-text-behind-the-agent-talking'),
                $this->assignment('captions-while-the-agent-is-talking'),
                $this->assignment('3d-text-tracked-on-the-ground-etc'),
            ],
            'Construction Basic Style' => [
                $this->assignment('rush', 10, 200),
            ],
            'Construction Premium Style' => [
                $this->assignment('rush', 20, 500),
                $this->assignment('ken-burns'),
                $this->assignment('building-a-house-transition'),
                $this->assignment('painting-transition'),
                $this->assignment('earth-zoom-transition'),
                $this->assignment('3d-text-behind-the-agent-talking'),
                $this->assignment('captions-while-the-agent-is-talking'),
            ],
            'Construction Luxury Style' => [
                $this->assignment('rush', 20, 500),
                $this->assignment('ken-burns'),
                $this->assignment('house-drop'),
                $this->assignment('pillar-masking'),
                $this->assignment('painting-transition'),
                $this->assignment('earth-zoom-transition'),
                $this->assignment('day-to-night-ai'),
                $this->assignment('virtual-staging-ai'),
                $this->assignment('3d-text-behind-the-agent-talking'),
                $this->assignment('captions-while-the-agent-is-talking'),
                $this->assignment('3d-text-tracked-on-the-ground-etc'),
            ],
        ];
    }

    /**
     * @param  \App\Models\ServiceAddon  $addon
     * @param  \Illuminate\Database\Eloquent\Model  $assignable
     */
    protected function assignAddon(ServiceAddon $addon, Model $assignable, ?float $clientPriceOverride = null, ?float $editorPriceOverride = null): void
    {
        ServiceAddonAssignment::updateOrCreate(
            [
                'service_addon_id' => $addon->id,
                'assignable_type' => $assignable::class,
                'assignable_id' => $assignable->getKey(),
            ],
            [
                'client_price_override' => $clientPriceOverride,
                'editor_price_override' => $editorPriceOverride,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function subStyle(string $name, string $slug, int $sortOrder, array $formatPricing): array
    {
        return [
            'name' => $name,
            'slug' => $slug,
            'sort_order' => $sortOrder,
            'format_pricing' => $formatPricing,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatPricing(string $formatName, string $formatLabel, float $clientPrice, float $editorPrice, int $sortOrder): array
    {
        return [
            'format_name' => $formatName,
            'format_label' => $formatLabel,
            'client_price' => $clientPrice,
            'editor_price' => $editorPrice,
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function addon(
        string $name,
        string $slug,
        string $addonType,
        float $clientPrice,
        float $editorPrice,
        bool $hasQuantity,
        ?string $sampleLink,
        ?string $group,
        int $sortOrder,
    ): array {
        return [
            'name' => $name,
            'slug' => $slug,
            'addon_type' => $addonType,
            'client_price' => $clientPrice,
            'editor_price' => $editorPrice,
            'has_quantity' => $hasQuantity,
            'sample_link' => $sampleLink,
            'group' => $group,
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function assignment(string $slug, ?float $clientPriceOverride = null, ?float $editorPriceOverride = null): array
    {
        return [
            'slug' => $slug,
            'client_price_override' => $clientPriceOverride,
            'editor_price_override' => $editorPriceOverride,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<string, \App\Models\Service>  $servicesByName
     * @param  \Illuminate\Support\Collection<string, \App\Models\ServiceAddon>  $baseAddons
     */
    protected function seedStructuredAddonGroups(Collection $servicesByName, Collection $baseAddons): void
    {
        $serviceAssignments = collect($this->serviceAssignments());

        $servicesByName->each(function (Service $service) use ($serviceAssignments, $baseAddons) {
            $assignmentMap = collect($serviceAssignments->get($service->name, []))
                ->mapWithKeys(fn (array $assignment) => [$assignment['slug'] => $assignment]);

            foreach ($this->addonGroupBlueprints($service, $assignmentMap, $baseAddons) as $groupBlueprint) {
                $group = ServiceAddonGroup::updateOrCreate(
                    [
                        'service_id' => $service->id,
                        'slug' => $groupBlueprint['slug'],
                    ],
                    [
                        'label' => $groupBlueprint['label'],
                        'input_type' => $groupBlueprint['input_type'],
                        'helper_text' => $groupBlueprint['helper_text'],
                        'sort_order' => $groupBlueprint['sort_order'],
                        'is_required' => $groupBlueprint['is_required'],
                        'is_active' => true,
                    ]
                );

                foreach ($groupBlueprint['options'] as $optionBlueprint) {
                    $addon = ServiceAddon::updateOrCreate(
                        ['slug' => $optionBlueprint['slug']],
                        [
                            'service_addon_group_id' => $group->id,
                            'name' => $optionBlueprint['name'],
                            'addon_type' => $optionBlueprint['addon_type'],
                            'client_price' => $optionBlueprint['client_price'],
                            'editor_price' => $optionBlueprint['editor_price'],
                            'has_quantity' => $optionBlueprint['has_quantity'],
                            'is_rush_option' => $optionBlueprint['is_rush_option'],
                            'sample_link' => $optionBlueprint['sample_link'],
                            'group' => $optionBlueprint['group'],
                            'sort_order' => $optionBlueprint['sort_order'],
                            'is_active' => true,
                        ]
                    );

                    $this->assignAddon($addon, $service);
                }
            }
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<string, array<string, mixed>>  $assignmentMap
     * @param  \Illuminate\Support\Collection<string, \App\Models\ServiceAddon>  $baseAddons
     * @return array<int, array<string, mixed>>
     */
    protected function addonGroupBlueprints(Service $service, Collection $assignmentMap, Collection $baseAddons): array
    {
        $groups = [];
        $categorySlug = $service->category?->slug;
        $supportsPropertyAddons = in_array($categorySlug, ['real-estate', 'wedding', 'event', 'construction'], true);

        if ($supportsPropertyAddons && $baseAddons->has('with-agent')) {
            /** @var ServiceAddon $agentAddon */
            $agentAddon = $baseAddons->get('with-agent');

            $groups[] = $this->addonGroupBlueprint(
                'With agent or voiceover?',
                'with-agent-or-voiceover',
                'dropdown',
                0,
                null,
                false,
                [
                    $this->clonedAddonOption($service, $agentAddon, 'with-agent', 'With Agent', 0),
                    $this->freeAddonOption($service, 'no-agent', 'No Agent', 10),
                ]
            );
        }

        if ($supportsPropertyAddons && $baseAddons->has('per-property-line')) {
            /** @var ServiceAddon $perPropertyAddon */
            $perPropertyAddon = $baseAddons->get('per-property-line');

            $groups[] = $this->addonGroupBlueprint(
                'With per property line?',
                'per-property-line',
                'dropdown',
                10,
                null,
                false,
                [
                    $this->clonedAddonOption($service, $perPropertyAddon, 'per-property-line', 'Per Property Line', 0),
                    $this->freeAddonOption($service, 'no-per-property-line', 'No Per Property Line', 10),
                ]
            );
        }

        if ($assignmentMap->has('rush') && $baseAddons->has('rush')) {
            /** @var ServiceAddon $rushAddon */
            $rushAddon = $baseAddons->get('rush');
            $rushAssignment = $assignmentMap->get('rush', []);

            $groups[] = $this->addonGroupBlueprint(
                'Rush (with additional charges)',
                'rush',
                'dropdown',
                20,
                null,
                false,
                [
                    $this->clonedAddonOption(
                        $service,
                        $rushAddon,
                        'rush',
                        'Rush',
                        0,
                        $rushAssignment['client_price_override'] ?? null,
                        $rushAssignment['editor_price_override'] ?? null,
                        true
                    ),
                    $this->freeAddonOption($service, 'no-rush', 'No Rush', 10),
                ]
            );
        }

        $effectAssignments = $assignmentMap
            ->filter(function (array $assignment, string $slug) use ($baseAddons) {
                /** @var ServiceAddon|null $addon */
                $addon = $baseAddons->get($slug);

                return $addon?->group === 'effects';
            })
            ->sortBy(fn (array $assignment, string $slug) => $baseAddons->get($slug)?->sort_order ?? 9999);

        if ($effectAssignments->isNotEmpty()) {
            $groups[] = $this->addonGroupBlueprint(
                'Do you want to customize the effects?',
                'effects',
                'checkbox_group',
                30,
                null,
                false,
                $effectAssignments
                    ->map(function (array $assignment, string $slug) use ($service, $baseAddons) {
                        /** @var ServiceAddon $addon */
                        $addon = $baseAddons->get($slug);

                        return $this->clonedAddonOption(
                            $service,
                            $addon,
                            $slug,
                            $addon->name,
                            (int) $addon->sort_order,
                            $assignment['client_price_override'] ?? null,
                            $assignment['editor_price_override'] ?? null,
                        );
                    })
                    ->values()
                    ->all()
            );
        }

        $captionAssignments = $assignmentMap
            ->filter(function (array $assignment, string $slug) use ($baseAddons) {
                /** @var ServiceAddon|null $addon */
                $addon = $baseAddons->get($slug);

                return $addon?->group === 'captions';
            })
            ->sortBy(fn (array $assignment, string $slug) => $baseAddons->get($slug)?->sort_order ?? 9999);

        if ($captionAssignments->isNotEmpty()) {
            $groups[] = $this->addonGroupBlueprint(
                'Do you need 3D text and captions?',
                'captions',
                'checkbox_group',
                40,
                null,
                false,
                $captionAssignments
                    ->map(function (array $assignment, string $slug) use ($service, $baseAddons) {
                        /** @var ServiceAddon $addon */
                        $addon = $baseAddons->get($slug);

                        return $this->clonedAddonOption(
                            $service,
                            $addon,
                            $slug,
                            $addon->name,
                            (int) $addon->sort_order,
                            $assignment['client_price_override'] ?? null,
                            $assignment['editor_price_override'] ?? null,
                        );
                    })
                    ->values()
                    ->all()
            );
        }

        return $groups;
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return array<string, mixed>
     */
    protected function addonGroupBlueprint(
        string $label,
        string $slug,
        string $inputType,
        int $sortOrder,
        ?string $helperText,
        bool $isRequired,
        array $options,
    ): array {
        return [
            'label' => $label,
            'slug' => $slug,
            'input_type' => $inputType,
            'helper_text' => $helperText,
            'sort_order' => $sortOrder,
            'is_required' => $isRequired,
            'options' => $options,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function clonedAddonOption(
        Service $service,
        ServiceAddon $sourceAddon,
        string $slugSuffix,
        string $name,
        int $sortOrder,
        ?float $clientPriceOverride = null,
        ?float $editorPriceOverride = null,
        bool $isRushOption = false,
    ): array {
        return [
            'slug' => sprintf('service-%d-%s', $service->id, $slugSuffix),
            'name' => $name,
            'addon_type' => $sourceAddon->addon_type,
            'client_price' => $clientPriceOverride ?? (float) $sourceAddon->client_price,
            'editor_price' => $editorPriceOverride ?? (float) $sourceAddon->editor_price,
            'has_quantity' => (bool) $sourceAddon->has_quantity,
            'is_rush_option' => $isRushOption,
            'sample_link' => $sourceAddon->sample_link,
            'group' => $sourceAddon->group,
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function freeAddonOption(Service $service, string $slugSuffix, string $name, int $sortOrder): array
    {
        return [
            'slug' => sprintf('service-%d-%s', $service->id, $slugSuffix),
            'name' => $name,
            'addon_type' => 'boolean',
            'client_price' => 0,
            'editor_price' => 0,
            'has_quantity' => false,
            'is_rush_option' => false,
            'sample_link' => null,
            'group' => null,
            'sort_order' => $sortOrder,
        ];
    }

    protected function normalizeValue(?string $value): string
    {
        return str((string) $value)
            ->trim()
            ->lower()
            ->replace('&', 'and')
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->value();
    }
}

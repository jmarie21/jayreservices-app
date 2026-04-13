<?php

namespace App\Exports;

use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProjectsExport implements FromQuery, ShouldAutoSize, WithColumnWidths, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private readonly ?string $status = null,
        private readonly ?string $dateFrom = null,
        private readonly ?string $dateTo = null,
        private readonly ?string $search = null,
        private readonly ?string $editorId = null,
    ) {}

    public function query(): Builder
    {
        $query = Project::with([
            'client',
            'service.category.addonAssignments.addon',
            'service.addonAssignments.addon',
            'editor',
        ])
            ->oldest();

        if ($this->status) {
            $query->where('status', $this->status);
        }

        if ($this->editorId) {
            if ($this->editorId === 'unassigned') {
                $query->whereNull('editor_id');
            } else {
                $query->where('editor_id', $this->editorId);
            }
        }

        if ($this->dateFrom && $this->dateTo) {
            $query->whereBetween('projects.created_at', [
                Carbon::parse($this->dateFrom)->startOfDay(),
                Carbon::parse($this->dateTo)->endOfDay(),
            ]);
        } elseif ($this->dateFrom) {
            $query->where('projects.created_at', '>=', Carbon::parse($this->dateFrom)->startOfDay());
        } elseif ($this->dateTo) {
            $query->where('projects.created_at', '<=', Carbon::parse($this->dateTo)->endOfDay());
        }

        if ($this->search) {
            $searchTerm = $this->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('project_name', 'like', "%{$searchTerm}%")
                    ->orWhere('style', 'like', "%{$searchTerm}%")
                    ->orWhereHas('service', fn ($sq) => $sq->where('name', 'like', "%{$searchTerm}%"))
                    ->orWhereHas('client', fn ($sq) => $sq->where('name', 'like', "%{$searchTerm}%"));
            });
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'Client Name',
            'Project Name',
            'Service',
            'Video Format',
            'Add Ons',
            'Editor',
            'Status',
            'Priority',
            'Total Price',
            'Editor Price',
            'Created At',
        ];
    }

    /**
     * @param  Project  $project
     */
    public function map($project): array
    {
        return [
            $project->client?->name ?? 'N/A',
            $project->project_name,
            $project->service?->name ?? 'N/A',
            self::formatVideoFormat($project),
            self::formatAddOns($project),
            $project->editor?->name ?? 'Unassigned',
            $project->status,
            $project->priority,
            $project->total_price,
            $project->editor_price,
            Carbon::parse($project->created_at)->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  Project  $project
     */
    public static function formatVideoFormat($project): string
    {
        $videoFormat = trim((string) ($project->format ?? ''));

        return $videoFormat !== '' ? $videoFormat : 'N/A';
    }

    /**
     * @param  Project  $project
     */
    public static function formatAddOns($project): string
    {
        $addOns = collect();
        $availableAddons = self::availableServiceAddons($project);
        $appendAddOn = static function (string $label, int $quantity = 1, bool $showQuantity = false) use ($addOns): void {
            $safeLabel = trim($label);

            if ($safeLabel === '') {
                return;
            }

            $normalized = strtolower($safeLabel);

            if ($addOns->contains(fn (array $item) => $item['key'] === $normalized)) {
                return;
            }

            $addOns->push([
                'key' => $normalized,
                'label' => $showQuantity || $quantity > 1 ? "{$safeLabel} ({$quantity}x)" : $safeLabel,
            ]);
        };

        $style = strtolower(trim($project->style ?? ''));
        $isPremiumOrLuxury = str_contains($style, 'premium') || str_contains($style, 'luxury');
        $isLuxury = str_contains($style, 'luxury');
        $isHorsemenStyle = str_contains($style, 'horsemen');

        if ($project->with_agent) {
            $appendAddOn('With Agent');
        }

        if ($project->rush) {
            $appendAddOn('Rush');
        }

        if ($project->per_property) {
            $count = $project->per_property_count ?? 0;
            $appendAddOn('Per Property Line', (int) max(1, $count), true);
        }

        $extraFields = $project->extra_fields;

        if (is_array($extraFields)) {
            $serviceAddons = $extraFields['service_addons'] ?? [];

            if (is_array($serviceAddons) && ! empty($serviceAddons)) {
                foreach ($serviceAddons as $addon) {
                    $matchedAddon = $availableAddons->first(function (array $serviceAddon) use ($addon) {
                        return (int) ($serviceAddon['id'] ?? 0) === (int) ($addon['addon_id'] ?? 0)
                            || self::normalizeAddonValue((string) ($serviceAddon['slug'] ?? '')) === self::normalizeAddonValue((string) ($addon['slug'] ?? ''))
                            || self::normalizeAddonValue((string) ($serviceAddon['name'] ?? '')) === self::normalizeAddonValue((string) ($addon['name'] ?? ''));
                    });

                    $appendAddOn(
                        (string) ($addon['name'] ?? $addon['slug'] ?? ''),
                        (int) max(1, (int) ($addon['quantity'] ?? 1)),
                        (bool) (($matchedAddon['has_quantity'] ?? false) || ($matchedAddon['addon_type'] ?? null) === 'quantity')
                    );
                }
            }

            // Only include captions that have a price for this style
            $pricedCaptions = [];
            if ($isPremiumOrLuxury) {
                $pricedCaptions[] = 'Captions while the agent is talking';
                $pricedCaptions[] = '3D Text behind the Agent Talking';
            }
            if ($isLuxury) {
                $pricedCaptions[] = '3D Text tracked on the ground etc.';
                $pricedCaptions[] = '3D Graphics together with text';
            }
            if ($isHorsemenStyle) {
                $pricedCaptions[] = 'Captions while the agent is talking';
                $pricedCaptions[] = '3D Text behind the Agent Talking';
                $pricedCaptions[] = '3D Text tracked on the ground etc.';
                $pricedCaptions[] = '3D Graphics together with text';
            }

            if (! empty($extraFields['captions'])) {
                foreach ($extraFields['captions'] as $caption) {
                    if (in_array($caption, $pricedCaptions)) {
                        $appendAddOn($caption);
                    }
                }
            }

            // Include effects that have a price (premium/luxury and horsemen style)
            if (($isPremiumOrLuxury || $isHorsemenStyle) && ! empty($extraFields['effects'])) {
                $pricedEffects = [
                    'Painting Transition',
                    'Earth Zoom Transition',
                    'Day to Night AI',
                    'Virtual Staging AI',
                ];

                foreach ($extraFields['effects'] as $effect) {
                    $name = $effect['id'] ?? '';
                    $qty = $effect['quantity'] ?? 1;
                    if (in_array($name, $pricedEffects)) {
                        $appendAddOn($name, (int) max(1, $qty), true);
                    }
                }
            }
        }

        return $addOns
            ->pluck('label')
            ->implode(', ');
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected static function availableServiceAddons(Project $project): Collection
    {
        $assignments = collect();

        if ($project->service?->category) {
            $assignments = $assignments->concat($project->service->category->addonAssignments ?? []);
        }

        $assignments = $assignments->concat($project->service->addonAssignments ?? []);

        return $assignments
            ->map(function ($assignment) {
                $addon = $assignment->addon;

                if (! $addon) {
                    return null;
                }

                return [
                    'id' => $addon->id,
                    'name' => $addon->name,
                    'slug' => $addon->slug,
                    'addon_type' => $addon->addon_type,
                    'has_quantity' => (bool) $addon->has_quantity,
                ];
            })
            ->filter()
            ->values();
    }

    protected static function normalizeAddonValue(?string $value): string
    {
        return str((string) $value)
            ->trim()
            ->lower()
            ->replace('&', 'and')
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->value();
    }

    public function columnWidths(): array
    {
        return [
            'D' => 24,
            'E' => 40,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            'E' => [
                'alignment' => [
                    'wrapText' => true,
                ],
            ],
        ];
    }
}

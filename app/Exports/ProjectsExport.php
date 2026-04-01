<?php

namespace App\Exports;

use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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
        $query = Project::with(['client', 'service', 'editor'])
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
    public static function formatAddOns($project): string
    {
        $addOns = [];
        $style = strtolower(trim($project->style ?? ''));
        $isPremiumOrLuxury = str_contains($style, 'premium') || str_contains($style, 'luxury');
        $isLuxury = str_contains($style, 'luxury');
        $isHorsemenStyle = str_contains($style, 'horsemen');

        if ($project->with_agent) {
            $addOns[] = 'With Agent';
        }

        if ($project->rush) {
            $addOns[] = 'Rush';
        }

        if ($project->per_property) {
            $count = $project->per_property_count ?? 0;
            $addOns[] = "Per Property Line ({$count}x)";
        }

        $extraFields = $project->extra_fields;

        if (is_array($extraFields)) {
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
                        $addOns[] = $caption;
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
                        $addOns[] = "{$name} ({$qty}x)";
                    }
                }
            }
        }

        return implode(', ', $addOns);
    }

    public function columnWidths(): array
    {
        return [
            'D' => 40,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            'D' => [
                'alignment' => [
                    'wrapText' => true,
                ],
            ],
        ];
    }
}

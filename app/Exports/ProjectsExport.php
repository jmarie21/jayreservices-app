<?php

namespace App\Exports;

use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProjectsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
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
            ->latest();

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
            'Project Name',
            'Service',
            'Client Name',
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
            $project->project_name,
            $project->service?->name ?? 'N/A',
            $project->client?->name ?? 'N/A',
            $project->editor?->name ?? 'Unassigned',
            $project->status,
            $project->priority,
            $project->total_price,
            $project->editor_price,
            Carbon::parse($project->created_at)->format('Y-m-d H:i:s'),
        ];
    }
}

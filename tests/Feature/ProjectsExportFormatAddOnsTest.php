<?php

use App\Exports\ProjectsExport;
use App\Models\Project;

it('excludes effects without a price from add-ons', function () {
    $project = Project::factory()->create([
        'style' => 'premium video',
        'with_agent' => true,
        'rush' => false,
        'per_property' => false,
        'extra_fields' => [
            'captions' => ['3D Text behind the Agent Talking'],
            'effects' => [
                ['id' => 'Ken Burns', 'quantity' => 1],
                ['id' => 'Painting Transition', 'quantity' => 2],
            ],
        ],
    ]);

    $result = ProjectsExport::formatAddOns($project);

    expect($result)->toContain('With Agent')
        ->toContain('3D Text behind the Agent Talking')
        ->toContain('Painting Transition (2x)')
        ->not->toContain('Ken Burns');
});

it('excludes captions without a price for basic style', function () {
    $project = Project::factory()->create([
        'style' => 'basic video',
        'with_agent' => false,
        'rush' => true,
        'per_property' => false,
        'extra_fields' => [
            'captions' => ['3D Text behind the Agent Talking', 'No Captions'],
            'effects' => [
                ['id' => 'Ken Burns', 'quantity' => 1],
            ],
        ],
    ]);

    $result = ProjectsExport::formatAddOns($project);

    expect($result)->toBe('Rush');
});

it('includes luxury-only captions for luxury style', function () {
    $project = Project::factory()->create([
        'style' => 'luxury video',
        'with_agent' => false,
        'rush' => false,
        'per_property' => false,
        'extra_fields' => [
            'captions' => ['3D Text tracked on the ground etc.', '3D Graphics together with text'],
            'effects' => [],
        ],
    ]);

    $result = ProjectsExport::formatAddOns($project);

    expect($result)->toContain('3D Text tracked on the ground etc.')
        ->toContain('3D Graphics together with text');
});

it('excludes luxury captions from premium style', function () {
    $project = Project::factory()->create([
        'style' => 'premium video',
        'with_agent' => false,
        'rush' => false,
        'per_property' => false,
        'extra_fields' => [
            'captions' => ['3D Text tracked on the ground etc.'],
            'effects' => [],
        ],
    ]);

    $result = ProjectsExport::formatAddOns($project);

    expect($result)->toBe('');
});

it('returns empty string when no priced add-ons exist', function () {
    $project = Project::factory()->create([
        'style' => 'basic video',
        'with_agent' => false,
        'rush' => false,
        'per_property' => false,
        'extra_fields' => [
            'captions' => ['No Captions'],
            'effects' => [
                ['id' => 'Ken Burns', 'quantity' => 1],
            ],
            'custom_effects' => [
                ['description' => 'Some custom effect'],
            ],
        ],
    ]);

    $result = ProjectsExport::formatAddOns($project);

    expect($result)->toBe('');
});

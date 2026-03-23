<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => User::factory()->create(['role' => 'client'])->id,
            'service_id' => Service::factory(),
            'style' => fake()->word(),
            'company_name' => fake()->company(),
            'contact' => fake()->name(),
            'project_name' => fake()->sentence(3),
            'file_link' => fake()->url(),
            'total_price' => fake()->randomFloat(2, 100, 5000),
            'status' => 'todo',
            'priority' => 'normal',
            'in_progress_since' => null,
        ];
    }
}

<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clients = User::where('role', 'client')->get();
        $editors = User::where('role', 'editor')->get();
        $services = Service::all();

        $projectsData = [
            [
                'project_name' => 'Project A',
                'style' => 'Basic Style',
                'company_name' => 'Company A',
                'contact' => '123456789',
                'format' => '1080p',
                'camera' => 'Canon',
                'quality' => 'High',
                'music' => 'Song A',
                'music_link' => null,
                'file_link' => 'linkA',
                'notes' => 'First project',
                'total_price' => 1000,
                'status' => 'pending',
            ],
            [
                'project_name' => 'Project B',
                'style' => 'Deluxe Style',
                'company_name' => 'Company B',
                'contact' => '987654321',
                'format' => '4K',
                'camera' => 'Sony',
                'quality' => 'High',
                'music' => 'Song B',
                'music_link' => null,
                'file_link' => 'linkB',
                'notes' => 'Second project',
                'total_price' => 2000,
                'status' => 'in_progress',
            ],
            [
                'project_name' => 'Project C',
                'style' => 'Premium Style',
                'company_name' => 'Company C',
                'contact' => '111222333',
                'format' => '4K',
                'camera' => 'Panasonic',
                'quality' => 'High',
                'music' => 'Song C',
                'music_link' => null,
                'file_link' => 'linkC',
                'notes' => 'Third project',
                'total_price' => 3000,
                'status' => 'completed',
            ],
            [
                'project_name' => 'Project D',
                'style' => 'Luxury Style',
                'company_name' => 'Company D',
                'contact' => '444555666',
                'format' => '4K',
                'camera' => 'Canon',
                'quality' => 'High',
                'music' => 'Song D',
                'music_link' => null,
                'file_link' => 'linkD',
                'notes' => 'Fourth project',
                'total_price' => 4000,
                'status' => 'pending',
            ],
            [
                'project_name' => 'Project E',
                'style' => 'Basic Style',
                'company_name' => 'Company E',
                'contact' => '777888999',
                'format' => '1080p',
                'camera' => 'Sony',
                'quality' => 'Medium',
                'music' => 'Song E',
                'music_link' => null,
                'file_link' => 'linkE',
                'notes' => 'Fifth project',
                'total_price' => 1000,
                'status' => 'in_progress',
            ],
            [
                'project_name' => 'Project F',
                'style' => 'Deluxe Style',
                'company_name' => 'Company F',
                'contact' => '000111222',
                'format' => '1080p',
                'camera' => 'Panasonic',
                'quality' => 'Medium',
                'music' => 'Song F',
                'music_link' => null,
                'file_link' => 'linkF',
                'notes' => 'Sixth project',
                'total_price' => 2000,
                'status' => 'completed',
            ],
        ];

        $projectIndex = 0;

        foreach ($clients as $client) {
            for ($i = 0; $i < 3; $i++) {
                $editor = $editors[$projectIndex % count($editors)];
                $service = $services->where('name', $projectsData[$projectIndex]['style'])->first();

                Project::create(array_merge($projectsData[$projectIndex], [
                    'client_id' => $client->id,
                    'editor_id' => $editor->id,
                    'service_id' => $service->id,
                    'with_agent' => false,
                    'extra_fields' => null,
                ]));

                $projectIndex++;
            }
        }
    }
}

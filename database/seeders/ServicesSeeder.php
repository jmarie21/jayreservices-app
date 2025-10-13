<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ServicesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            [
                'name' => 'Basic Style',
                'features' => [
                    'Retiming the clip with beat of the music',
                    'With 2-3 smooth speed ramps only',
                    'Some simple transitions like zoom after speed ramps',
                ],
                'price' => 0,
                // 'video_link' => 'https://www.youtube.com/embed/e5SCQSz5R7Q',
                'video_link' => 'https://www.youtube.com/embed/vQzUuq14zcM?si=mr85vx65t8FspD4y'
            ],
            [
                'name' => 'Deluxe Style',
                'features' => [
                    'Retiming the clip with beat of the music',
                    'With Ken Burns transition',
                    'With day to night (Applicable if you have both Day & Night Clips)',
                    'With more than 3 smooth speed ramps',
                    'With Motion Blur every speed ramps',
                ],
                'price' => 0,
                'video_link' => 'https://www.youtube.com/embed/oaqW7kBki1c',
            ],
            [
                'name' => 'Talking Heads',
                'features' => [
                    'Retiming the clip with beat of the music',
                    'With 2-3 smooth speed ramps only',
                    'Some simple transitions like zoom after speed ramps',
                ],
                'price' => 0,
                'video_link' => 'https://www.youtube.com/embed/PCTdP__t9bI'
            ],
            [
                'name' => 'Premium Style',
                'features' => [
                    'Retiming the clip with beat of the music',
                    'With Ken Burns transition',
                    'With building a house transition',
                    'With day to night (Applicable if you have both Day & Night Clips)',
                    'With more than 3 smooth speed ramps',
                    'With Motion Blur every speed ramps',
                    'With some 3D Text if requested',
                    'With sound effects',
                    'With Earth zoom transition if requested',
                ],
                'price' => 0,
                'video_link' => 'https://player.vimeo.com/video/1106034319',
            ],
            [
                'name' => 'Luxury Style',
                'features' => [
                    'Retiming the clip with beat of the music',
                    'With Ken Burns transition',
                    'With house drop transition',
                    'With day to night (Applicable if you have both Day & Night Clips)',
                    'With more than 3 smooth speed ramps',
                    'With Motion Blur every speed ramps',
                    'With Pillar masking transition (Applicable if you have clips showing passing in the house’s pillar.)',
                    'With a smooth pro zoom-in effect on the speaking agent',
                    'With a smooth zoom-in effect on detailed shot clips.',
                    'With few 3D Text if requested',
                    'With sound effects',
                    'With Earth zoom transition if requested',
                ],
                'price' => 0,
                'video_link' => 'https://player.vimeo.com/video/1106032025',
            ],
        ];

        foreach ($services as $service) {
            Service::updateOrCreate(
                ['name' => $service['name']], // unique field to check
                [
                    'features' => $service['features'],
                    'price' => $service['price'],
                    'video_link' => $service['video_link'],
                ]
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServicesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            // Real Estate
            [
                'name' => 'Real Estate Basic Style',
                'features' => [
                    'Retiming the clip with beat of the music',
                    'With 2-3 smooth speed ramps only',
                    'Turn around time 12 hours or less if not busy',
                ],
                'price' => 0,
                // 'video_link' => 'https://www.youtube.com/embed/e5SCQSz5R7Q',
                'video_link' => 'https://www.youtube.com/embed/vQzUuq14zcM?si=mr85vx65t8FspD4y',
            ],
            [
                'name' => 'Real Estate Deluxe Style',
                'features' => [
                    'Retiming the clip with beat of the music',
                    'With Ken Burns transition',
                    'With day to night (Applicable if you have both Day & Night Clips)',
                    'With more than 3 smooth speed ramps',
                    'With Motion Blur every speed ramps',
                    'Turn around time 12 hours or less if not busy',
                ],
                'price' => 0,
                'video_link' => 'https://www.youtube.com/embed/oaqW7kBki1c',
            ],
            [
                'name' => 'Real Estate Premium Style',
                'features' => [
                    'Retiming the clip with beat of the music',
                    'With Ken Burns transition (if requested)',
                    'With building a house transition (if requested)',
                    'With day to night (Applicable if you have both Day & Night Clips)',
                    'With more than 3 smooth speed ramps',
                    'With Motion Blur every speed ramps',
                    'With some 3D Text (if requested)',
                    'With sound effects',
                    'With Earth zoom transition (if requested)',
                    'With Day to Night AI (if requested)',
                    'NOT ALLOWED TO DO VIRTUAL STAGING ONLY FOR LUXURY',
                    'Turn around time 24 hours or less if not busy',
                ],
                'price' => 0,
                'video_link' => 'https://player.vimeo.com/video/1106034319',
            ],
            [
                'name' => 'Real Estate Luxury Style',
                'features' => [
                    'Retiming the clip with beat of the music',
                    'With Ken Burns transition (if requested)',
                    'With house drop transition (if requested)',
                    'With day to night (Applicable if you have both Day & Night Clips)',
                    'With more than 3 smooth speed ramps',
                    'With Boomerang speed ramps',
                    'With Motion Blur every speed ramps',
                    'With Pillar masking transition (Applicable if you have clips showing passing in the house\'s pillar.)',
                    'With a smooth pro zoom-in effect on the speaking agent',
                    'With a smooth zoom-in effect on detailed shot clips.',
                    'With few 3D Text',
                    'With 3D Graphics together with text (if requested additional)',
                    'With a 3D text suction effect (if requested)',
                    'With cinematic sound effects',
                    'With Earth zoom transition (if requested)',
                    'With Virtual Staging (if requested)',
                    'Turn around time 24-72 hours',
                ],
                'price' => 0,
                'video_link' => 'https://www.youtube.com/embed/fYBEUla1HOY',
            ],
            // Talking Heads Services
            [
                'name' => 'Talking Heads',
                'features' => [
                    'Retiming the clip with beat of the music',
                    'With 2-3 smooth speed ramps only',
                    'Some simple transitions like zoom after speed ramps',
                ],
                'price' => 0,
                'video_link' => 'https://www.youtube.com/embed/PCTdP__t9bI',
            ],
            // Wedding Services
            [
                'name' => 'Wedding Basic Style',
                'features' => [
                    'Maximum 60 seconds',
                    'Simple cut ',
                    'No sfx',
                    'One music only',
                    'Plain video',
                ],
                'price' => 0,
                'video_link' => '/images/wedding/wedding.jpg',
            ],
            [
                'name' => 'Wedding Premium Style',
                'features' => [
                    '60-90 Seconds',
                    'Can do transitions',
                    'With sfx',
                    'Can change music ',
                    'Plain video ',
                ],
                'price' => 0,
                'video_link' => '/images/wedding/wedding2.jpg',
            ],
            [
                'name' => 'Wedding Luxury Style',
                'features' => [
                    '1 min - 3 min',
                    'Can do nice transitions (if requested)',
                    'With Sfx',
                    'Can change music ',
                    'Video with a little vows from Bride and Groom',
                    'Can put AI transitions but charge each clip that needs to put AI transition ($15 each clip)',
                ],
                'price' => 0,
                'video_link' => '/images/wedding/wedding3.jpg',
            ],
            // Events Services
            [
                'name' => 'Event Basic Style',
                'features' => [
                    'Maximum 60 seconds',
                    'Simple cut ',
                    'No sfx',
                    'Plain video',
                ],
                'price' => 0,
                'video_link' => '/images/events/event.jpg',
            ],
            [
                'name' => 'Event Premium Style',
                'features' => [
                    '60-90 Seconds',
                    'With speedramps',
                    'With sfx',
                    'Can change music ',
                    'Can do transitions',
                    'Can put AI transitions but charge each clip that needs to put AI transition ($15 each clip)',
                ],
                'price' => 0,
                'video_link' => '/images/events/event2.jpg',
            ],
            [
                'name' => 'Event Luxury Style',
                'features' => [
                    '1 min - 3 min',
                    'Can do nice transitions (if requested)',
                    'With Sfx',
                    'With speedramps',
                    'Video with a little vows from Bride and Groom',
                    'Can put AI transitions but charge each clip that needs to put AI transition ($15 each clip)',
                ],
                'price' => 0,
                'video_link' => '/images/events/event3.jpg',
            ],
            // Construction Video Services
            [
                'name' => 'Construction Basic Style',
                'features' => [
                    'Maximum 60 seconds',
                    'Simple cut ',
                    'No sfx',
                    'Plain video',
                ],
                'price' => 0,
                'video_link' => '/images/construction/construction.jpg',
            ],
            [
                'name' => 'Construction Premium Style',
                'features' => [
                    '60-90 Seconds',
                    'With speedramps',
                    'With sfx',
                    'Can change music ',
                    'Can do transitions',
                    'Can put AI transitions but charge each clip that needs to put AI transition ($15 each clip)',
                ],
                'price' => 0,
                'video_link' => '/images/construction/construction2.jpg',
            ],
            [
                'name' => 'Construction Luxury Style',
                'features' => [
                    '1 min - 3 min',
                    'Can do nice transitions (if requested)',
                    'With Sfx',
                    'With speedramps',
                    'Video with a little vows from Bride and Groom',
                    'Can put AI transitions but charge each clip that needs to put AI transition ($15 each clip)',
                ],
                'price' => 0,
                'video_link' => '/images/construction/construction3.jpg',
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

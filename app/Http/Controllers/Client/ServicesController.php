<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Inertia\Inertia;

class ServicesController extends Controller
{
    public function index()
    {
        // Only return services that are real estate related (names starting with "Real Estate")
        $services = Service::where('name', 'like', 'Real Estate%')->get();

        return Inertia::render('client/Services', [
            'services' => $services,
        ]);
    }

    public function weddingServices()
    {
        // Only return services that are wedding related (names starting with "Wedding")
        $services = Service::where('name', 'like', 'Wedding%')->get();

        return Inertia::render('client/WeddingServices', [
            'services' => $services,
        ]);
    }

    public function eventServices()
    {
        // Only return services that are event related (names starting with "Event")
        $services = Service::where('name', 'like', 'Event%')->get();

        return Inertia::render('client/EventServices', [
            'services' => $services,
        ]);
    }

    public function constructionServices()
    {
        // Only return services that are construction related (names starting with "Construction")
        $services = Service::where('name', 'like', 'Construction%')->get();

        return Inertia::render('client/ConstructionServices', [
            'services' => $services,
        ]);
    }

    public function talkingHeadsServices()
    {
        $services = Service::where('name', 'like', 'Talking Heads%')
            ->orWhere('name', 'Horsemen Style')
            ->get();

        return Inertia::render('client/TalkingHeadsServices', [
            'services' => $services,
        ]);
    }
}

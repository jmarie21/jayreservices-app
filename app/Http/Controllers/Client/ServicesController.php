<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ServicesController extends Controller
{
    public function index()
    {
        // Only return services that are real estate related (names starting with "Real Estate")
        $services = Service::where('name', 'like', 'Real Estate%')->get();

        return Inertia::render("client/Services", [
            "services" => $services
        ]);
    }

    public function weddingServices(){
        // Only return services that are wedding related (names starting with "Wedding")
        $services = Service::where('name', 'like', 'Wedding%')->get();

        return Inertia::render("client/WeddingServices", [
            "services" => $services
        ]);
    }

    public function eventServices(){
        // Only return services that are event related (names starting with "Event")
        $services = Service::where('name', 'like', 'Event%')->get();

        return Inertia::render("client/EventServices", [
            "services" => $services
        ]);
    }

    public function talkingHeadsServices(){
        // Only return services that are talking head related (names starting with "Talking Head")
        $services = Service::where('name', 'like', 'Talking Heads%')->get();

        return Inertia::render("client/TalkingHeadsServices", [
            "services" => $services
        ]);
    }
}

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
}

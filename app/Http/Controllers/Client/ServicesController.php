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
        $services = Service::all();

        return Inertia::render("client/Services", [
            "services" => $services
        ]);
    }
}

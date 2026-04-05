<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use App\Services\PricingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ServicesController extends Controller
{
    public function index(PricingService $pricingService): Response
    {
        return Inertia::render('client/ServicesIndex', [
            'categories' => $pricingService->getAllCategoriesCatalogData(),
        ]);
    }

    public function show(ServiceCategory $category, PricingService $pricingService): Response
    {
        abort_unless($category->is_active, 404);

        return Inertia::render('client/ServiceCategory', [
            'category' => $pricingService->getCategoryCatalogData($category),
        ]);
    }

    public function legacyRealEstate(): RedirectResponse
    {
        return redirect()->route('services.category', ['category' => 'real-estate']);
    }

    public function legacyWedding(): RedirectResponse
    {
        return redirect()->route('services.category', ['category' => 'wedding']);
    }

    public function legacyEvent(): RedirectResponse
    {
        return redirect()->route('services.category', ['category' => 'event']);
    }

    public function legacyConstruction(): RedirectResponse
    {
        return redirect()->route('services.category', ['category' => 'construction']);
    }

    public function legacyTalkingHeads(): RedirectResponse
    {
        return redirect()->route('services.category', ['category' => 'talking-heads']);
    }
}

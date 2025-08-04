<?php

use App\Http\Controllers\Client\ServicesController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'client'])->group(function () {
    Route::get("/services", [ServicesController::class, "index"])->name("services");

    Route::get("/projects",  function() {
        return Inertia::render("client/Projects");
    });
});

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('admin/Dashboard');
    })->name('dashboard');
});


require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

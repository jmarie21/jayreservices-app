<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\ProjectManagement;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Client\ProjectsController;
use App\Http\Controllers\Client\ServicesController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'client'])->group(function () {
    Route::get("/services", [ServicesController::class, "index"])->name("services");

    Route::get("/projects",  [ProjectsController::class, 'index'])->name("projects");
    Route::post("/projects", [ProjectsController::class, 'createProject'])->name('projects.store');
    Route::put("/projects/{project}", [ProjectsController::class, 'updateProject'])->name('projects.update');
});

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('admin/Dashboard');
    })->name('dashboard');

    Route::get("/user-mgmt", [UserController::class, 'index'])->name('user-mgmt');
    Route::post("/user-mgmt", [UserController::class, 'createNewUser'])->name('user-mgmt.store');
    Route::put("/user-mgmt/{user}", [UserController::class, 'updateUser'])->name("user-mgmt.update");
    Route::delete("/user-mgmt/{user}", [UserController::class, 'deleteUser'])->name('user-mgmt.destroy');

    Route::get("/project-mgmt/{client}", [ProjectManagement::class, 'showClientProjects'])->name("client.projects");
    Route::patch('/projects/{project}', [ProjectManagement::class, 'update'])->name('projects.update');
});


require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

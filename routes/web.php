<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\EditorManagement;
use App\Http\Controllers\Admin\InvoiceManagementController;
use App\Http\Controllers\Admin\ProjectManagement;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Client\ProjectsController;
use App\Http\Controllers\Client\ServicesController;
use App\Http\Controllers\Editor\EditorProjectsController;
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
    Route::get('/admin-dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get("/user-mgmt", [UserController::class, 'index'])->name('user-mgmt');
    Route::post("/user-mgmt", [UserController::class, 'createNewUser'])->name('user-mgmt.store');
    Route::put("/user-mgmt/{user}", [UserController::class, 'updateUser'])->name("user-mgmt.update");
    Route::delete("/user-mgmt/{user}", [UserController::class, 'deleteUser'])->name('user-mgmt.destroy');

    Route::get("/project-mgmt/{client}", [ProjectManagement::class, 'showClientProjects'])->name("client.projects");
    Route::patch('/projects/{project}', [ProjectManagement::class, 'update'])->name('projects.update');

    Route::get('/editor-mgmt/{editor}', [EditorManagement::class, 'showEditorProjects'])->name('editor.projects');

    Route::get('/invoice-mgmt', [InvoiceManagementController::class, 'index'])->name('invoice.index');
    Route::get('/invoice-mgmt/{invoice}/view', [InvoiceManagementController::class, 'view'])->name('invoice.view');
    Route::post('/invoice-mgmt', [InvoiceManagementController::class, 'store'])->name('invoice.store');
    Route::post('invoice-mgmt/{invoice}/send', [InvoiceManagementController::class, 'send'])->name('invoice.send');
    Route::put('/invoice-mgmt/{invoice}', [InvoiceManagementController::class, 'update'])->name('invoice.update');
    Route::post('/invoice-mgmt/{id}/paid', [InvoiceManagementController::class, 'markAsPaid'])->name('invoice.markPaid');
    Route::post('/invoice-mgmt/{id}/cancel', [InvoiceManagementController::class, 'cancel'])->name('invoice.cancel');

});

Route::middleware(['auth', 'editor'])->group(function () {
    Route::get('editor-dashboard', function () {
        return Inertia::render('editor/EditorDashboard');
    })->name('editor-dashboard');

    Route::get('/editor-projects', [EditorProjectsController::class, 'index'])->name('editor.projects.index');
    Route::patch('/editor-projects/{project}', [EditorProjectsController::class, 'update'])->name('editor.projects.update');
});


require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\BulkNotificationController;
use App\Http\Controllers\Admin\EditorManagement;
use App\Http\Controllers\Admin\InvoiceManagementController;
use App\Http\Controllers\Admin\ProjectManagement;
use App\Http\Controllers\Admin\ServiceManagementController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Client\ProjectsController;
use App\Http\Controllers\Client\ServicesController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\Editor\EditorProjectsController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'client'])->group(function () {
    Route::get('/services', [ServicesController::class, 'index'])->name('services');
    Route::get('/services/{category:slug}', [ServicesController::class, 'show'])->name('services.category');
    Route::get('/realestate-services', [ServicesController::class, 'legacyRealEstate'])->name('legacy.realestate-services');
    Route::get('/wedding-services', [ServicesController::class, 'legacyWedding'])->name('legacy.wedding-services');
    Route::get('/event-services', [ServicesController::class, 'legacyEvent'])->name('legacy.event-services');
    Route::get('/construction-services', [ServicesController::class, 'legacyConstruction'])->name('legacy.construction-services');
    Route::get('/talkingheads-services', [ServicesController::class, 'legacyTalkingHeads'])->name('legacy.talkingheads-services');

    Route::get('/projects', [ProjectsController::class, 'index'])->name('projects');
    Route::post('/projects', [ProjectsController::class, 'createProject'])->name('projects.store');
    Route::put('/projects/{project}', [ProjectsController::class, 'updateProject'])->name('projects.client_update');
    Route::put('/projects/{project}/status', [ProjectsController::class, 'updateStatus'])->name('projects.updateStatus');
});

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/admin-dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/user-mgmt', [UserController::class, 'index'])->name('user-mgmt');
    Route::post('/user-mgmt', [UserController::class, 'createNewUser'])->name('user-mgmt.store');
    Route::put('/user-mgmt/{user}', [UserController::class, 'updateUser'])->name('user-mgmt.update');
    Route::delete('/user-mgmt/{user}', [UserController::class, 'deleteUser'])->name('user-mgmt.destroy');
    Route::delete('/admin/projects/{project}', [ProjectManagement::class, 'destroy'])->name('projects.destroy');

    Route::get('/project-mgmt/{client}', [ProjectManagement::class, 'showClientProjects'])->name('client.projects');
    Route::patch('/projects/{project}', [ProjectManagement::class, 'update'])->name('projects.admin_update');
    Route::patch('/projects/{project}/price', [ProjectManagement::class, 'updatePrice'])->name('projects.update-price');

    Route::get('/all-projects', [ProjectManagement::class, 'showAllProjects'])->name('projects.all');
    Route::get('/all-projects/export', [ProjectManagement::class, 'exportAllProjects'])->name('projects.all.export');
    Route::get('/all-projects/preview-export', [ProjectManagement::class, 'previewExport'])->name('projects.all.preview-export');
    Route::put('/project-mgmt/{project}', [ProjectManagement::class, 'adminUpdateProject'])->name('admin.project.update');

    // Admin services management
    Route::get('/admin-services', [ServiceManagementController::class, 'index'])->name('admin.services.management');
    Route::post('/admin-services/categories', [ServiceManagementController::class, 'storeCategory'])->name('admin.services.categories.store');
    Route::put('/admin-services/categories/{category}', [ServiceManagementController::class, 'updateCategory'])->name('admin.services.categories.update');
    Route::delete('/admin-services/categories/{category}', [ServiceManagementController::class, 'destroyCategory'])->name('admin.services.categories.destroy');
    Route::get('/admin-services/services/{service}', [ServiceManagementController::class, 'showServiceEditor'])->name('admin.services.editor');
    Route::post('/admin-services/services', [ServiceManagementController::class, 'storeService'])->name('admin.services.store');
    Route::put('/admin-services/services/{service}', [ServiceManagementController::class, 'updateService'])->name('admin.services.update');
    Route::delete('/admin-services/services/{service}', [ServiceManagementController::class, 'destroyService'])->name('admin.services.destroy');
    Route::post('/admin-services/sub-styles', [ServiceManagementController::class, 'storeSubStyle'])->name('admin.services.sub-styles.store');
    Route::put('/admin-services/sub-styles/{subStyle}', [ServiceManagementController::class, 'updateSubStyle'])->name('admin.services.sub-styles.update');
    Route::delete('/admin-services/sub-styles/{subStyle}', [ServiceManagementController::class, 'destroySubStyle'])->name('admin.services.sub-styles.destroy');
    Route::post('/admin-services/format-pricing', [ServiceManagementController::class, 'storeFormatPricing'])->name('admin.services.format-pricing.store');
    Route::put('/admin-services/format-pricing/{pricing}', [ServiceManagementController::class, 'updateFormatPricing'])->name('admin.services.format-pricing.update');
    Route::delete('/admin-services/format-pricing/{pricing}', [ServiceManagementController::class, 'destroyFormatPricing'])->name('admin.services.format-pricing.destroy');
    Route::post('/admin-services/addon-groups', [ServiceManagementController::class, 'storeAddonGroup'])->name('admin.services.addon-groups.store');
    Route::put('/admin-services/addon-groups/{addonGroup}', [ServiceManagementController::class, 'updateAddonGroup'])->name('admin.services.addon-groups.update');
    Route::delete('/admin-services/addon-groups/{addonGroup}', [ServiceManagementController::class, 'destroyAddonGroup'])->name('admin.services.addon-groups.destroy');
    Route::post('/admin-services/addons', [ServiceManagementController::class, 'storeAddon'])->name('admin.services.addons.store');
    Route::put('/admin-services/addons/{addon}', [ServiceManagementController::class, 'updateAddon'])->name('admin.services.addons.update');
    Route::delete('/admin-services/addons/{addon}', [ServiceManagementController::class, 'destroyAddon'])->name('admin.services.addons.destroy');

    // Services catalog
    Route::get('/admin-realestate-services', [ProjectManagement::class, 'realEstateServices'])->name('services.all');
    Route::get('/admin-wedding-services', [ProjectManagement::class, 'weddingServices'])->name('services.wedding');
    Route::get('/admin-event-services', [ProjectManagement::class, 'eventServices'])->name('services.event');
    Route::get('/admin-construction-services', [ProjectManagement::class, 'constructionServices'])->name('services.construction');
    Route::get('/admin-talkingheads-services', [ProjectManagement::class, 'talkingHeadsServices'])->name('services.talkingheads');
    Route::get('/admin-service-catalog', [ProjectManagement::class, 'servicesIndex'])->name('admin.services.index');
    Route::get('/admin-services/{category:slug}', [ProjectManagement::class, 'serviceCatalog'])->name('admin.services.category');
    Route::post('/services', [ProjectManagement::class, 'adminCreateProject'])->name('admin.project.create');

    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
    Route::put('/comments/{comment}', [CommentController::class, 'update'])->name('comments.update');

    Route::get('/editor-mgmt/{editor}', [EditorManagement::class, 'showEditorProjects'])->name('admin.editor.projects');

    Route::get('/invoice-mgmt', [InvoiceManagementController::class, 'index'])->name('invoice.index');
    Route::get('/invoice-mgmt/{invoice}/view', [InvoiceManagementController::class, 'view'])->name('invoice.view');
    Route::post('/invoice-mgmt', [InvoiceManagementController::class, 'store'])->name('invoice.store');
    Route::post('invoice-mgmt/{invoice}/send', [InvoiceManagementController::class, 'send'])->name('invoice.send');
    Route::put('/invoice-mgmt/{invoice}', [InvoiceManagementController::class, 'update'])->name('invoice.update');
    Route::post('/invoice-mgmt/{id}/paid', [InvoiceManagementController::class, 'markAsPaid'])->name('invoice.markPaid');
    Route::post('/invoice-mgmt/{id}/cancel', [InvoiceManagementController::class, 'cancel'])->name('invoice.cancel');
    Route::delete('/notifications/delete-all', [NotificationController::class, 'destroyAll'])
        ->name('notifications.destroyAll');

    // Bulk Notification Routes
    Route::post('/bulk-notification/send', [BulkNotificationController::class, 'sendToAllClients'])->name('bulk-notification.send');
    Route::get('/bulk-notification/stats', [BulkNotificationController::class, 'getClientEmailStats'])->name('bulk-notification.stats');

});

Route::middleware(['auth', 'editor'])->group(function () {
    Route::get('editor-dashboard', function () {
        return Inertia::render('editor/EditorDashboard');
    })->name('editor-dashboard');

    Route::get('/editor-projects', [EditorProjectsController::class, 'index'])->name('editor.projects.index');
    Route::patch('/editor-projects/{project}', [EditorProjectsController::class, 'update'])->name('editor.projects.update');
});

Route::middleware(['auth'])->group(function () {
    Route::post('/projects/{project}/comments', [CommentController::class, 'store'])
        ->name('projects.comments.store');
    Route::put('/comments/{comment}', [CommentController::class, 'update'])->name('comments.update');

    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::delete('/notifications/delete-all', [NotificationController::class, 'destroyAll'])
        ->name('notifications.destroyAll');

});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

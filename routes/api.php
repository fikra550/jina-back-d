<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccidentController;
use App\Http\Controllers\CarController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\GarageController;
use App\Http\Controllers\MatriculeController;
use App\Http\Controllers\MatriculeProductController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\UtilisateurController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\AdminPermissionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SousLocationController;
use App\Http\Controllers\LavageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductCategoryController;

require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| Public Routes (no authentication required)
|--------------------------------------------------------------------------
*/
Route::post('/clients', [ClientController::class, 'store']);
Route::post('/contacts', [ContactController::class, 'store']);
Route::get('/cars', [CarController::class, 'index']);
Route::post('/reservations', [ReservationController::class, 'store']);

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
Route::post('/admin/login', [UtilisateurController::class, 'login']);
Route::post('/admin/logout', [UtilisateurController::class, 'logout'])
    ->middleware('auth:sanctum');

Route::post('/forgot-password', [UtilisateurController::class, 'forgotPassword']);

/*
|--------------------------------------------------------------------------
| Protected Routes (require authentication)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('/admin/profile', [UtilisateurController::class, 'me']);
    Route::get('/admin/permissions/me', [AdminPermissionController::class, 'myPermissions']);

    // -------------------- CARS --------------------
    Route::apiResource('cars', CarController::class)->except(['index']);
    Route::post('/cars/{id}/assign-matricule', [CarController::class, 'assignMatricule']);
    Route::get('/cars/{id}/current-matricule', [CarController::class, 'getCurrentMatricule']);

    // -------------------- MATRICULES --------------------
    Route::apiResource('matricules', MatriculeController::class);
    Route::get('/matricules/car/{carId}', [MatriculeController::class, 'getByCar']);
    Route::put('/matricules/{id}/kilometrage', [MatriculeController::class, 'updateKilometrage']);

    /*
    |-----------------------------------------------------------------
    | MAINTENANCE — aligned with frontend (store.jsx)
    |-----------------------------------------------------------------
    */

    Route::post('/matricules/{id}/maintenance', [MatriculeController::class, 'addMaintenanceWithQuantity']);
    Route::get('/matricules/{id}/maintenance/{type}/history', [MatriculeController::class, 'getMaintenanceHistory']);
    Route::delete('/matricules/{id}/maintenance/history', [MatriculeController::class, 'removeMaintenanceHistoryEntry']);
    Route::post('/matricules/{id}/periodic-maintenance', [MatriculeController::class, 'addPeriodicKmMaintenance']);
    Route::post('/matricules/{id}/periodic-maintenance/change', [MatriculeController::class, 'addPeriodicKmChange']);
    Route::get('/matricules/{id}/periodic-maintenance/{itemId}/history', [MatriculeController::class, 'getPeriodicKmHistory']);

    // Aliases for backward compatibility
    Route::post('/matricules/{id}/add-maintenance-quantity', [MatriculeController::class, 'addMaintenanceWithQuantity']);
    Route::get('/matricules/{id}/maintenance-history/{type}', [MatriculeController::class, 'getMaintenanceHistory']);
    Route::delete('/matricules/{id}/remove-maintenance-history', [MatriculeController::class, 'removeMaintenanceHistoryEntry']);
    Route::post('/matricules/{id}/add-periodic-km-maintenance', [MatriculeController::class, 'addPeriodicKmMaintenance']);
    Route::post('/matricules/{id}/add-periodic-km-change', [MatriculeController::class, 'addPeriodicKmChange']);
    Route::get('/matricules/{id}/periodic-km-history/{itemId}', [MatriculeController::class, 'getPeriodicKmHistory']);

    Route::delete('/matricules/{id}/remove-periodic-km-history', [MatriculeController::class, 'removePeriodicKmHistoryEntry']);
    Route::put('/matricules/{id}/update-periodic-km-item', [MatriculeController::class, 'updatePeriodicKmItem']);
    Route::delete('/matricules/{id}/remove-periodic-km-item', [MatriculeController::class, 'removePeriodicKmItem']);
    Route::post('/matricules/{id}/add-maintenance', [MatriculeController::class, 'addMaintenanceItem']);
    Route::put('/matricules/{id}/update-maintenance', [MatriculeController::class, 'updateMaintenanceItem']);
    Route::post('/matricules/{id}/remove-maintenance', [MatriculeController::class, 'removeMaintenanceItem']);
    Route::post('/matricules/{id}/complete-maintenance', [MatriculeController::class, 'completeMaintenance']);
    Route::get('/matricules/{id}/maintenance-alerts', [MatriculeController::class, 'getMaintenanceAlerts']);
    Route::get('/matricules/maintenance-alerts/all', [MatriculeController::class, 'getAllMaintenanceAlerts']);
    Route::post('/matricules/{id}/add-quantity-item', [MatriculeController::class, 'addMaintenanceItemWithQuantity']);

    // -------------------- MATRICULE PRODUCTS (stock consommé, litres inclus) --------------------
    Route::get('/matricules/{matriculeId}/products', [MatriculeProductController::class, 'index']);
    Route::post('/matricules/{matriculeId}/products', [MatriculeProductController::class, 'store']);
    Route::delete('/matricules/{matriculeId}/products/{matriculeProductId}', [MatriculeProductController::class, 'destroy']);

    // -------------------- CLIENTS --------------------
    Route::apiResource('clients', ClientController::class)->except(['store'])
        ->middleware('page.permission:clients');
    Route::get('/clients/search', [ClientController::class, 'search'])
        ->middleware('page.permission:clients');
    Route::get('/clients/{id}/download/{documentType}', [ClientController::class, 'downloadDocument'])
        ->middleware('page.permission:clients');

    // -------------------- GARAGES --------------------
    Route::get('/garages', [GarageController::class, 'index'])
        ->middleware('page.permission:garages');
    Route::post('/garages', [GarageController::class, 'store'])
        ->middleware('page.permission:garages');
    Route::get('/garages/{id}', [GarageController::class, 'show'])
        ->middleware('page.permission:garages');
    Route::put('/garages/{id}', [GarageController::class, 'update'])
        ->middleware('page.permission:garages');
    Route::delete('/garages/{id}', [GarageController::class, 'destroy'])
        ->middleware('page.permission:garages');

    // -------------------- PAYMENTS (Financing) --------------------
    Route::get('/payments', [PaymentController::class, 'index'])
        ->middleware('page.permission:payments');
    Route::get('/payments/stats', [PaymentController::class, 'getStats'])
        ->middleware('page.permission:payments');
    Route::post('/payments', [PaymentController::class, 'store'])
        ->middleware('page.permission:payments');
    Route::get('/payments/{id}', [PaymentController::class, 'show'])
        ->middleware('page.permission:payments');
    Route::put('/payments/{id}', [PaymentController::class, 'update'])
        ->middleware('page.permission:payments');
    Route::delete('/payments/{id}', [PaymentController::class, 'destroy'])
        ->middleware('page.permission:payments');
    Route::get('/payments/{financingId}/payments', [PaymentController::class, 'getPayments'])
        ->middleware('page.permission:payments');
    Route::post('/payments/{financingId}/record-payment', [PaymentController::class, 'recordPayment'])
        ->middleware('page.permission:payments');
    Route::post('/payments/{financingId}/generate-schedule', [PaymentController::class, 'generateSchedule'])
        ->middleware('page.permission:payments');

    // -------------------- RESERVATIONS --------------------
    Route::get('/reservations/check-late', [ReservationController::class, 'checkLate']);
    Route::get('/reservations/{id}/contract', [ReservationController::class, 'getContract']);
    Route::apiResource('reservations', ReservationController::class)->except(['store']);
    Route::post('/reservations/{id}/add-payment', [ReservationController::class, 'addPayment']);
    Route::post('/reservations/{id}/remove-payment', [ReservationController::class, 'removePayment']);

    Route::post('/reservations/{id}/generate-signature', [ReservationController::class, 'generateSignature'])
        ->middleware('page.permission:reservations');
    Route::post('/reservations/{id}/generate-second-signature', [ReservationController::class, 'generateSecondDriverSignature'])
        ->middleware('page.permission:reservations');

    // -------------------- ACCIDENTS --------------------
    Route::get('/accidents/statistics', [AccidentController::class, 'statistics'])
        ->middleware('page.permission:accidents');
    Route::get('/accidents/{id}/document-info/{documentType}', [AccidentController::class, 'getDocumentInfo'])
        ->middleware('page.permission:accidents');
    Route::get('/accidents/{id}/download/{documentType}', [AccidentController::class, 'downloadDocument'])
        ->middleware('page.permission:accidents');
    Route::get('/accidents/{id}/next-statuses', [AccidentController::class, 'getNextStatuses'])
        ->middleware('page.permission:accidents');
    Route::post('/accidents/{id}/record-payment', [AccidentController::class, 'recordPayment'])
        ->middleware('page.permission:accidents');
    Route::post('/accidents/{id}/close', [AccidentController::class, 'closeAccident'])
        ->middleware('page.permission:accidents');
    Route::apiResource('accidents', AccidentController::class)
        ->middleware('page.permission:accidents');

    // -------------------- CONTACTS --------------------
    Route::apiResource('contacts', ContactController::class)->except(['store'])
        ->middleware('page.permission:contacts');
    Route::get('/contacts/count', [ContactController::class, 'count'])
        ->middleware('page.permission:contacts');
    Route::get('/contacts/recent', [ContactController::class, 'recent'])
        ->middleware('page.permission:contacts');

    // -------------------- SOUS-LOCATIONS --------------------
    Route::get('/sous-locations', [SousLocationController::class, 'index']);

    Route::post('/sous-locations', [SousLocationController::class, 'store'])
        ->middleware('page.permission:sous-locations');
    Route::get('/sous-locations/{id}', [SousLocationController::class, 'show'])
        ->middleware('page.permission:sous-locations');
    Route::put('/sous-locations/{id}', [SousLocationController::class, 'update'])
        ->middleware('page.permission:sous-locations');
    Route::delete('/sous-locations/{id}', [SousLocationController::class, 'destroy'])
        ->middleware('page.permission:sous-locations');

    // -------------------- USERS --------------------
    Route::apiResource('utilisateurs', UtilisateurController::class)
        ->middleware('page.permission:users');
    Route::put('/utilisateurs/{utilisateur}/status', [UtilisateurController::class, 'updateStatus'])
        ->middleware('page.permission:users');
    Route::post('/utilisateurs/{utilisateur}/toggle-status', [UtilisateurController::class, 'toggleStatus'])
        ->middleware('page.permission:users');
    Route::put('/admin/profile', [UtilisateurController::class, 'updateProfile']);

    // -------------------- PERMISSION MANAGEMENT --------------------
    Route::prefix('admin/permissions')->middleware('page.permission:users')->group(function () {
        Route::get('/pages', [AdminPermissionController::class, 'getPages']);
        Route::get('/user/{userId}', [AdminPermissionController::class, 'getUserPermissions']);
        Route::post('/assign', [AdminPermissionController::class, 'assignPermission']);
        Route::delete('/revoke', [AdminPermissionController::class, 'revokePermission']);
    });

    // -------------------- REPORTS --------------------
    Route::get('/reports', [ReportController::class, 'index']);
    Route::post('/reports', [ReportController::class, 'store']);
    Route::put('/reports/{id}', [ReportController::class, 'update']);
    Route::delete('/reports/{id}', [ReportController::class, 'destroy']);
    Route::get('/download-report/{id}', [ReportController::class, 'download']);

    // -------------------- PRODUCT CATEGORIES --------------------
    Route::get('/product-categories', [ProductCategoryController::class, 'index'])
        ->middleware('page.permission:products');
    Route::post('/product-categories', [ProductCategoryController::class, 'store'])
        ->middleware('page.permission:products');
    Route::get('/product-categories/{id}', [ProductCategoryController::class, 'show'])
        ->middleware('page.permission:products');
    Route::put('/product-categories/{id}', [ProductCategoryController::class, 'update'])
        ->middleware('page.permission:products');
    Route::delete('/product-categories/{id}', [ProductCategoryController::class, 'destroy'])
        ->middleware('page.permission:products');

    // -------------------- PRODUCTS --------------------
    Route::get('/products/{id}/stock-movements', [ProductController::class, 'stockMovements'])
        ->middleware('page.permission:products');
    Route::apiResource('products', ProductController::class)
        ->middleware('page.permission:products');

    // -------------------- PRODUCT SALES --------------------
    Route::get('/product-sales', [ProductController::class, 'sales'])
        ->middleware('page.permission:products');
    Route::post('/product-sales', [ProductController::class, 'recordSale'])
        ->middleware('page.permission:products');
    Route::put('/product-sales/{saleId}', [ProductController::class, 'updateSale'])
        ->middleware('page.permission:products');
    Route::delete('/product-sales/{saleId}', [ProductController::class, 'deleteSale'])
        ->middleware('page.permission:products');

    // -------------------- LAVAGES --------------------
    Route::get('/lavages/statistics', [LavageController::class, 'statistics'])
        ->middleware('page.permission:lavage');
    Route::apiResource('lavages', LavageController::class)
        ->middleware('page.permission:lavage');
    Route::post('/lavages/{id}/start', [LavageController::class, 'start'])
        ->middleware('page.permission:lavage');
    Route::post('/lavages/{id}/complete', [LavageController::class, 'complete'])
        ->middleware('page.permission:lavage');
    Route::post('/lavages/{id}/cancel', [LavageController::class, 'cancel'])
        ->middleware('page.permission:lavage');
    Route::post('/lavages/{id}/add-payment', [LavageController::class, 'addPayment'])
        ->middleware('page.permission:lavage');
    Route::post('/lavages/{id}/remove-payment', [LavageController::class, 'removePayment'])
        ->middleware('page.permission:lavage');
    Route::post('/lavages/{id}/add-products', [LavageController::class, 'addProducts'])
        ->middleware('page.permission:lavage');
    Route::post('/lavages/{id}/remove-product', [LavageController::class, 'removeProduct'])
        ->middleware('page.permission:lavage');
});

// ============================================================
// PUBLIC ROUTE: Sign contract using token (no auth)
// ============================================================
Route::post('/reservations/sign/{token}', [ReservationController::class, 'signContract']);
Route::get('/reservations/sign-info/{token}', [ReservationController::class, 'getSignInfo']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::fallback(function () {
    return response()->json([
        'message' => 'API endpoint not found.'
    ], 404);
});
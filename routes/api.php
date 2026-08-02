<?php

use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\BorrowRequestController;
use App\Http\Controllers\Api\CapexController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ChecklistController;
use App\Http\Controllers\Api\CustomFieldController;
use App\Http\Controllers\Api\CustomStatusController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepotController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\QrController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\ToolTypeController;
use App\Http\Controllers\Api\ToolTypeSpecFieldController;
use App\Http\Controllers\Api\UpdaterController;
use App\Http\Controllers\Api\WaitlistController;
use App\Http\Controllers\Install\InstallController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    Route::get('/config', [AuthController::class, 'config']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/magic', [AuthController::class, 'requestMagic']);
    Route::post('/magic/consume', [AuthController::class, 'consumeMagic']);
    Route::post('/saml/acs', [AuthController::class, 'samlAcs']);
});

Route::prefix('install')->middleware('throttle:auth')->group(function () {
    Route::get('/status', [InstallController::class, 'status']);
    Route::post('/run', [InstallController::class, 'run']);
});

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Catalog browsing (any authenticated user)
    Route::get('/catalog/categories', [CatalogController::class, 'categories']);
    Route::get('/catalog/tool-types/{toolType}/items', [CatalogController::class, 'items']);
    Route::get('/custom-statuses', [CustomStatusController::class, 'index']);
    Route::get('/custom-fields', [CustomFieldController::class, 'index']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/tool-types', [ToolTypeController::class, 'index']);
    Route::get('/items', [ItemController::class, 'index']);
    Route::get('/items/{item}', [ItemController::class, 'show']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'myNotifications']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    // Borrow requests (borrowers)
    Route::middleware('permission:borrow_items')->group(function () {
        Route::get('/borrow-requests', [BorrowRequestController::class, 'index']);
        Route::post('/borrow-requests', [BorrowRequestController::class, 'store']);
        Route::get('/borrow-requests/{borrowRequest}', [BorrowRequestController::class, 'show']);
        Route::post('/borrow-requests/{borrowRequest}/submit', [BorrowRequestController::class, 'submit']);
        Route::post('/borrow-requests/{borrowRequest}/accept-modification', [BorrowRequestController::class, 'acceptModification']);
        Route::post('/borrow-requests/{borrowRequest}/reject-modification', [BorrowRequestController::class, 'rejectModification']);
        Route::post('/borrow-requests/{borrowRequest}/cancel', [BorrowRequestController::class, 'cancel']);

        Route::get('/waitlist', [WaitlistController::class, 'index']);
        Route::post('/waitlist', [WaitlistController::class, 'store']);
        Route::delete('/waitlist/{waitlist}', [WaitlistController::class, 'destroy']);
    });

    Route::middleware('permission:approve_requests')->group(function () {
        Route::post('/borrow-requests/{borrowRequest}/approve', [BorrowRequestController::class, 'approve']);
        Route::post('/loan-extensions/{extension}/decide', [LoanController::class, 'decideExtension']);
    });

    // Loans
    Route::get('/loans', [LoanController::class, 'index']);
    Route::get('/loans/{loan}', [LoanController::class, 'show']);
    Route::post('/loans/{loan}/self-return', [LoanController::class, 'selfReturn']);
    Route::post('/loans/{loan}/request-extension', [LoanController::class, 'requestExtension']);

    Route::middleware('permission:checkout_items')->group(function () {
        Route::get('/loans/{loan}/companion-suggestions', [LoanController::class, 'companionSuggestions']);
        Route::post('/loans/{loan}/checkout', [LoanController::class, 'checkout']);
        Route::post('/loans/{loan}/review-return', [LoanController::class, 'reviewReturn']);
        Route::post('/loans/sync-offline', [LoanController::class, 'syncOffline']);
    });

    // Tickets (any authenticated user may report, management requires permission)
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::post('/tickets/{ticket}/photos', [TicketController::class, 'uploadPhoto']);

    Route::middleware('permission:manage_tickets')->group(function () {
        Route::put('/tickets/{ticket}', [TicketController::class, 'update']);
        Route::post('/tickets/{ticket}/resolve', [TicketController::class, 'resolve']);
    });

    // Properties & depots (read for all authenticated; write for IT)
    Route::get('/properties', [PropertyController::class, 'index']);
    Route::get('/depots', [DepotController::class, 'index']);

    Route::middleware('permission:manage_properties')->group(function () {
        Route::post('/properties', [PropertyController::class, 'store']);
        Route::put('/properties/{property}', [PropertyController::class, 'update']);
        Route::delete('/properties/{property}', [PropertyController::class, 'destroy']);

        Route::post('/depots', [DepotController::class, 'store']);
        Route::put('/depots/{depot}', [DepotController::class, 'update']);
    });

    // Catalog & inventory management
    Route::middleware('permission:manage_catalog')->group(function () {
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

        Route::post('/tool-types', [ToolTypeController::class, 'store']);
        Route::put('/tool-types/{toolType}', [ToolTypeController::class, 'update']);

        Route::get('/tool-types/{toolType}/spec-fields', [ToolTypeSpecFieldController::class, 'index']);
        Route::post('/tool-types/{toolType}/spec-fields', [ToolTypeSpecFieldController::class, 'store']);
        Route::put('/tool-types/{toolType}/spec-fields/{specField}', [ToolTypeSpecFieldController::class, 'update']);
        Route::delete('/tool-types/{toolType}/spec-fields/{specField}', [ToolTypeSpecFieldController::class, 'destroy']);

        Route::get('/tool-types/{toolType}/links', [ToolTypeController::class, 'links']);
        Route::put('/tool-types/{toolType}/links', [ToolTypeController::class, 'syncLinks']);

        Route::post('/custom-statuses', [CustomStatusController::class, 'store']);
        Route::put('/custom-statuses/{customStatus}', [CustomStatusController::class, 'update']);

        Route::post('/custom-fields', [CustomFieldController::class, 'store']);
        Route::put('/custom-fields/{customField}', [CustomFieldController::class, 'update']);

        Route::get('/checklists', [ChecklistController::class, 'index']);
        Route::get('/checklists/{checklist}', [ChecklistController::class, 'show']);
        Route::post('/checklists', [ChecklistController::class, 'store']);
        Route::put('/checklists/{checklist}', [ChecklistController::class, 'update']);
        Route::delete('/checklists/{checklist}', [ChecklistController::class, 'destroy']);
    });

    Route::middleware('permission:manage_inventory')->group(function () {
        Route::post('/items', [ItemController::class, 'store']);
        Route::put('/items/{item}', [ItemController::class, 'update']);
        Route::delete('/items/{item}', [ItemController::class, 'destroy']);
        Route::post('/items/{item}/manual', [ItemController::class, 'uploadManual']);
        Route::post('/items/{item}/image', [ItemController::class, 'uploadImage']);
        Route::post('/items/{item}/link-items', [ItemController::class, 'linkItems']);
        Route::delete('/items/{item}/link-items/{child}', [ItemController::class, 'unlinkItem']);

        Route::get('/stock/consumables', [StockController::class, 'consumables']);
        Route::get('/stock/movements', [StockController::class, 'movements']);
        Route::post('/items/{item}/stock/restock', [StockController::class, 'restock']);
        Route::post('/items/{item}/stock/adjust', [StockController::class, 'adjust']);

        Route::post('/qr/items/{item}/generate', [QrController::class, 'generate']);
        Route::get('/qr/items/{item}/label', [QrController::class, 'label']);
        Route::post('/qr/export-zip', [QrController::class, 'exportZip']);
        Route::post('/qr/sheet', [QrController::class, 'sheet']);
    });

    Route::middleware('permission:manage_inventory|manage_settings')->group(function () {
        Route::get('/qr/sizes', [QrController::class, 'sizes']);
        Route::post('/qr/preview', [QrController::class, 'preview']);
    });

    // Maintenance
    Route::get('/maintenance/types', [MaintenanceController::class, 'typesIndex']);
    Route::get('/maintenance/plans', [MaintenanceController::class, 'plansIndex']);
    Route::get('/maintenance/work-orders', [MaintenanceController::class, 'workOrdersIndex']);

    Route::middleware('permission:manage_maintenance')->group(function () {
        Route::post('/maintenance/types', [MaintenanceController::class, 'typesStore']);
        Route::post('/maintenance/plans', [MaintenanceController::class, 'plansStore']);
        Route::put('/maintenance/plans/{plan}', [MaintenanceController::class, 'plansUpdate']);
        Route::post('/maintenance/work-orders', [MaintenanceController::class, 'workOrdersStore']);
        Route::post('/maintenance/work-orders/{workOrder}/complete', [MaintenanceController::class, 'workOrdersComplete']);
    });

    // Capex
    Route::middleware('permission:view_capex')->group(function () {
        Route::get('/capex/forecast', [CapexController::class, 'forecast']);
        Route::get('/capex/export/excel', [CapexController::class, 'exportExcel']);
        Route::get('/capex/export/pdf', [CapexController::class, 'exportPdf']);
    });

    // Audit
    Route::middleware('permission:view_audit')->group(function () {
        Route::get('/audit', [AuditController::class, 'index']);
        Route::get('/audit/export', [AuditController::class, 'export']);
    });

    // Users & roles
    Route::middleware('permission:manage_users')->group(function () {
        Route::get('/admin/users', [AdminUserController::class, 'index']);
        Route::post('/admin/users', [AdminUserController::class, 'store']);
        Route::put('/admin/users/{user}', [AdminUserController::class, 'update']);
        Route::post('/admin/users/{user}/roles', [AdminUserController::class, 'assignRoles']);
        Route::post('/admin/users/{user}/properties', [AdminUserController::class, 'assignProperties']);
    });

    Route::middleware('permission:manage_roles')->group(function () {
        Route::get('/admin/roles', [RoleController::class, 'index']);
        Route::post('/admin/roles', [RoleController::class, 'store']);
        Route::put('/admin/roles/{role}', [RoleController::class, 'update']);
    });

    // Settings
    Route::middleware('permission:manage_settings')->group(function () {
        Route::get('/settings/{group}', [SettingsController::class, 'show']);
        Route::put('/settings/branding', [SettingsController::class, 'updateBranding']);
        Route::post('/settings/branding/logo', [SettingsController::class, 'uploadLogo']);
        Route::post('/settings/branding/favicon', [SettingsController::class, 'uploadFavicon']);
        Route::put('/settings/smtp', [SettingsController::class, 'updateSmtp']);
        Route::put('/settings/twilio', [SettingsController::class, 'updateTwilio']);
        Route::put('/settings/saml', [SettingsController::class, 'updateSaml']);
        Route::put('/settings/features', [SettingsController::class, 'updateFeatures']);
        Route::put('/settings/defaults', [SettingsController::class, 'updateDefaults']);
        Route::put('/settings/updates', [SettingsController::class, 'updateUpdates']);
        Route::put('/settings/labels', [SettingsController::class, 'updateLabels']);

        Route::get('/notifications/matrix', [NotificationController::class, 'matrixGet']);
        Route::put('/notifications/matrix', [NotificationController::class, 'matrixUpdate']);
    });

    // Updates & backups (IT admin)
    Route::middleware('permission:manage_updates')->group(function () {
        Route::get('/updater/check', [UpdaterController::class, 'check']);
        Route::post('/updater/apply', [UpdaterController::class, 'apply']);
    });

    Route::middleware('permission:manage_it')->group(function () {
        Route::get('/backups', [BackupController::class, 'index']);
        Route::post('/backups/export', [BackupController::class, 'export']);
        Route::get('/backups/{filename}/download', [BackupController::class, 'download']);
        Route::post('/backups/import', [BackupController::class, 'import']);
    });
});

<?php

use App\Http\Controllers\Api\V1\Admin\ActivityLogController;
use App\Http\Controllers\Api\V1\Admin\RecycleBinController;
use App\Http\Controllers\Api\V1\Admin\SystemSettingsController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Sales\ClientDocumentController;
use App\Http\Controllers\Api\V1\Sales\ClientController;
use App\Http\Controllers\Api\V1\Sales\SaleBuildingController;
use App\Http\Controllers\Api\V1\Sales\SalesDashboardController;
use App\Http\Controllers\Api\V1\Sales\SalesExpenseController;
use App\Http\Controllers\Api\V1\Sales\SalesPaymentController;
use App\Http\Controllers\Api\V1\Sales\SalesReportController;
use App\Http\Controllers\Api\V1\Sales\SaleUnitController;
use App\Http\Controllers\Api\V1\Rental\ChargeBatchController;
use App\Http\Controllers\Api\V1\Rental\BuildingUtilityController;
use App\Http\Controllers\Api\V1\Rental\BulkMeterReadingController;
use App\Http\Controllers\Api\V1\Rental\MeterReadingContextController;
use App\Http\Controllers\Api\V1\Rental\EmployeeController;
use App\Http\Controllers\Api\V1\Rental\PayrollEntryController;
use App\Http\Controllers\Api\V1\Rental\RentChargeController;
use App\Http\Controllers\Api\V1\Rental\RentPaymentController;
use App\Http\Controllers\Api\V1\Rental\RentalBuildingController;
use App\Http\Controllers\Api\V1\Rental\RentalDashboardController;
use App\Http\Controllers\Api\V1\Rental\RentalExpenseController;
use App\Http\Controllers\Api\V1\Rental\RentalReportController;
use App\Http\Controllers\Api\V1\Rental\RentalUnitController;
use App\Http\Controllers\Api\V1\Rental\ShareholderBillController;
use App\Http\Controllers\Api\V1\Rental\ShareholderController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\Rental\TenantController;
use App\Http\Controllers\Api\V1\Rental\TenantDocumentController;
use App\Http\Controllers\Api\V1\Rental\TenantElectricityBillController;
use App\Http\Controllers\Api\V1\Rental\TenantWaterBillController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:6,1');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware('throttle:3,1');
        Route::post('verify-reset-code', [AuthController::class, 'verifyResetCode'])
            ->middleware('throttle:10,1');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])
            ->middleware('throttle:6,1');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::patch('profile', [AuthController::class, 'updateProfile']);
            Route::put('password', [AuthController::class, 'updatePassword']);
        });
    });

    Route::middleware(['auth:sanctum', 'role:rental'])->prefix('rental')->name('rental.')->group(function (): void {
        Route::get('dashboard', RentalDashboardController::class);
        Route::apiResource('buildings', RentalBuildingController::class);
        Route::apiResource('units', RentalUnitController::class);
        Route::get('move-outs', [TenantController::class, 'moveOuts']);
        Route::post('tenants/{tenant}/move-out', [TenantController::class, 'moveOut']);
        Route::get('tenants/{tenant}/payment-summary', [TenantController::class, 'paymentSummary']);
        Route::get('tenants/{tenant}/documents', [TenantDocumentController::class, 'index']);
        Route::post('tenants/{tenant}/documents', [TenantDocumentController::class, 'store']);
        Route::apiResource('tenants', TenantController::class)->only(['index', 'store', 'show', 'update']);

        Route::get('charges', [RentChargeController::class, 'index']);
        Route::put('charges/{rentCharge}', [RentChargeController::class, 'update']);

        Route::get('charge-batches/pending-count', [ChargeBatchController::class, 'pendingCount']);
        Route::get('charge-batches', [ChargeBatchController::class, 'show']);
        Route::post('charge-batches/generate', [ChargeBatchController::class, 'generate']);
        Route::post('charge-batches/{chargeBatch}/refresh-pending', [ChargeBatchController::class, 'refreshPending']);
        Route::put('charge-batches/{chargeBatch}/items/{chargeBatchItem}', [ChargeBatchController::class, 'updateItem']);
        Route::post('charge-batches/{chargeBatch}/tenants/{tenantId}/exclude', [ChargeBatchController::class, 'excludeTenant']);
        Route::post('charge-batches/{chargeBatch}/tenants/{tenantId}/approve', [ChargeBatchController::class, 'approveTenant']);
        Route::post('charge-batches/{chargeBatch}/tenants/{tenantId}/reopen', [ChargeBatchController::class, 'reopenTenant']);
        Route::post('charge-batches/{chargeBatch}/approve-all', [ChargeBatchController::class, 'approveAll']);

        Route::get('payments', [RentPaymentController::class, 'index']);
        Route::post('payments', [RentPaymentController::class, 'store']);
        Route::put('payments/{rentPayment}', [RentPaymentController::class, 'update']);
        Route::post('payments/{rentPayment}/void', [RentPaymentController::class, 'void']);

        Route::get('water-bills', [TenantWaterBillController::class, 'index']);
        Route::post('water-bills', [TenantWaterBillController::class, 'store']);
        Route::put('water-bills/{waterBill}', [TenantWaterBillController::class, 'update']);

        Route::get('bulk-meter-readings', [BulkMeterReadingController::class, 'index']);
        Route::post('bulk-meter-readings', [BulkMeterReadingController::class, 'store']);
        Route::get('meter-readings/context', [MeterReadingContextController::class, 'show']);

        Route::get('electricity-bills', [TenantElectricityBillController::class, 'index']);
        Route::post('electricity-bills', [TenantElectricityBillController::class, 'store']);
        Route::put('electricity-bills/{electricityBill}', [TenantElectricityBillController::class, 'update']);

        Route::get('utilities/nairobi-water', [BuildingUtilityController::class, 'nairobiWaterIndex']);
        Route::post('utilities/nairobi-water', [BuildingUtilityController::class, 'nairobiWaterStore']);
        Route::get('utilities/electricity', [BuildingUtilityController::class, 'electricityIndex']);
        Route::post('utilities/electricity', [BuildingUtilityController::class, 'electricityStore']);

        Route::apiResource('expenses', RentalExpenseController::class);
        Route::apiResource('employees', EmployeeController::class);
        Route::apiResource('payroll', PayrollEntryController::class)->parameters(['payroll' => 'payrollEntry']);
        Route::apiResource('shareholders', ShareholderController::class)->except(['show']);
        Route::apiResource('shareholder-bills', ShareholderBillController::class)->parameters(['shareholder-bill' => 'shareholderBill']);

        Route::prefix('reports')->group(function (): void {
            Route::get('tenant-balances', [RentalReportController::class, 'tenantBalances']);
            Route::get('payment-history', [RentalReportController::class, 'paymentHistory']);
            Route::get('charge-summary', [RentalReportController::class, 'chargeSummary']);
            Route::get('arrears-aging', [RentalReportController::class, 'arrearsAging']);
            Route::get('income-statement', [RentalReportController::class, 'incomeStatement']);
        });
    });

    Route::middleware(['auth:sanctum', 'role:sales'])->prefix('sales')->name('sales.')->group(function (): void {
        Route::get('dashboard', SalesDashboardController::class);
        Route::apiResource('buildings', SaleBuildingController::class);
        Route::apiResource('units', SaleUnitController::class);
        Route::get('clients/{client}/payment-summary', [ClientController::class, 'paymentSummary']);
        Route::post('clients/{client}/disable', [ClientController::class, 'disable']);
        Route::get('clients/{client}/documents', [ClientDocumentController::class, 'index']);
        Route::post('clients/{client}/documents', [ClientDocumentController::class, 'store']);
        Route::apiResource('clients', ClientController::class)->only(['index', 'store', 'show', 'update']);

        Route::get('payments', [SalesPaymentController::class, 'index']);
        Route::post('payments', [SalesPaymentController::class, 'store']);
        Route::put('payments/{payment}', [SalesPaymentController::class, 'update']);
        Route::post('payments/{payment}/cancel', [SalesPaymentController::class, 'cancel']);

        Route::apiResource('expenses', SalesExpenseController::class);

        Route::prefix('reports')->group(function (): void {
            Route::get('balance', [SalesReportController::class, 'balance']);
            Route::get('income-statement', [SalesReportController::class, 'incomeStatement']);
            Route::get('cancelled-clients', [SalesReportController::class, 'cancelledClients']);
            Route::get('cancelled-payments', [SalesReportController::class, 'cancelledPayments']);
            Route::get('payment-history', [SalesReportController::class, 'paymentHistory']);
        });
    });

    Route::middleware(['auth:sanctum'])->group(function (): void {
        Route::get('documents/{document}', [DocumentController::class, 'show']);
        Route::delete('documents/{document}', [DocumentController::class, 'destroy']);
    });

    Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('settings', [SystemSettingsController::class, 'show']);
        Route::post('settings/test-email', [SystemSettingsController::class, 'sendTestMail']);
        Route::get('activity-log', [ActivityLogController::class, 'index']);
        Route::get('recycle-bin/types', [RecycleBinController::class, 'types']);
        Route::get('recycle-bin', [RecycleBinController::class, 'index']);
        Route::post('recycle-bin/{type}/{id}/restore', [RecycleBinController::class, 'restore']);
        Route::apiResource('users', UserController::class);
    });
});

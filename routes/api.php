<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomersController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClustersController;
use App\Http\Controllers\UserController;


Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', function (Request $request) {
        return $request->user();
    });
    Route::put('/me', [AuthController::class, 'updateProfile']);

    // Sales Reports
    Route::get('/reports/sales', [ReportController::class, 'salesReport'])
         ->middleware('role:admin,sales,finance');
    Route::get('/reports/export', [ReportController::class, 'exportSales'])
         ->middleware('role:admin,sales,finance');

    // Sales Resource
    // Read: Admin, Finance, Sales
    Route::get('/sales', [SalesController::class, 'index'])->middleware('role:admin,finance,sales');
    Route::get('/sales/{sale}', [SalesController::class, 'show'])->middleware('role:admin,finance,sales');
    
    // Write: Admin, Sales (Sales Create)
    Route::post('/sales', [SalesController::class, 'store'])->middleware('role:admin,sales');
    // Update: Admin, Finance (For payment/status), Sales (Limited)
    Route::put('/sales/{sale}', [SalesController::class, 'update'])->middleware('role:admin,finance,sales');
    Route::delete('/sales/{sale}', [SalesController::class, 'destroy'])->middleware('role:admin,sales'); 
    
    // Payments History (Finance)
    Route::get('/payments', [\App\Http\Controllers\PaymentController::class, 'index'])->middleware('role:admin,finance');
    
    // Clusters
    // Admin: Full
    // Sales/Finance: Read-Only
    Route::get('/clusters/stats', [ClustersController::class, 'stats'])->middleware('role:admin,sales,finance');
    Route::get('/clusters', [ClustersController::class, 'index'])->middleware('role:admin,sales,finance');
    Route::get('/clusters/{cluster}', [ClustersController::class, 'show'])->middleware('role:admin,sales,finance');
    // Write operations: Admin only
    Route::post('/clusters', [ClustersController::class, 'store'])->middleware('role:admin');
    Route::put('/clusters/{cluster}', [ClustersController::class, 'update'])->middleware('role:admin');
    Route::delete('/clusters/{cluster}', [ClustersController::class, 'destroy'])->middleware('role:admin');

    // Customers
    // Admin: Full
    // Finance: Read-Only
    // Sales: Full
    // Customer Stats
    Route::get('/customers/stats', [CustomersController::class, 'stats'])->middleware('role:admin,sales,finance');
    
    Route::get('/customers', [CustomersController::class, 'index'])->middleware('role:admin,sales,finance');
    Route::get('/customers/{customer}', [CustomersController::class, 'show'])->middleware('role:admin,sales,finance');
    // Write operations: Admin and Sales
    Route::post('/customers', [CustomersController::class, 'store'])->middleware('role:admin,sales');
    Route::put('/customers/{customer}', [CustomersController::class, 'update'])->middleware('role:admin,sales');
    Route::delete('/customers/{customer}', [CustomersController::class, 'destroy'])->middleware('role:admin,sales');

    // Users (Management)
    // Admin Only
    Route::apiResource('users', UserController::class)->middleware('role:admin');

    // Rankings
    Route::get('/rankings', [\App\Http\Controllers\RankController::class, 'index'])->middleware('role:admin');
});

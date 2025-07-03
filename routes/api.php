<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\ShopifyApiController;
use App\Http\Controllers\Api\ScrapingApiController;
use App\Http\Controllers\Api\DashboardApiController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// Import
Route::post('/import/{type}', [ImportController::class, 'import'])->name('api.import');
Route::get('/import/template/{type}', [ImportController::class, 'template'])->name('api.import.template');

// Export
Route::get('/export/{type}', [ExportController::class, 'export'])->name('api.export');
Route::get('/exports', [ExportController::class, 'list'])->name('api.exports.list');

// Dashboard
Route::get('/dashboard/kpis', [DashboardApiController::class, 'kpis'])->name('api.dashboard.kpis');
Route::get('/dashboard/margin-trend', [DashboardApiController::class, 'marginTrend'])->name('api.dashboard.margin-trend');

// Automatisation
Route::post('/scraping/run', [ScrapingApiController::class, 'run'])->name('api.scraping.run');
Route::post('/shopify/sync', [ShopifyApiController::class, 'sync'])->name('api.shopify.sync');
Route::get('/jobs/{jobId}/status', [ScrapingApiController::class, 'jobStatus'])->name('api.jobs.status');

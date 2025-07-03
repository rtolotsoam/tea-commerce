<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StockController;
use App\Http\Controllers\MarginController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShopifyController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ScrapingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportExportController;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Dashboard
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard/export', [DashboardController::class, 'export'])->name('dashboard.export');

// Achats
Route::resource('purchases', PurchaseController::class);

// Produits
Route::resource('products', ProductController::class);

// Stocks
Route::get('/stocks', [StockController::class, 'index'])->name('stocks.index');
Route::post('/stocks/{stock}/adjust', [StockController::class, 'adjust'])->name('stocks.adjust');

// Marges
Route::get('/margins', [MarginController::class, 'index'])->name('margins.index');
Route::post('/margins/calculate', [MarginController::class, 'calculate'])->name('margins.calculate');

// Import/Export
Route::get('/import-export', [ImportExportController::class, 'index'])->name('import-export.index');
Route::get('/import-export/template/{type}', [ImportExportController::class, 'downloadTemplate'])->name('import-export.template');

// Automatisation
Route::get('/scraping', [ScrapingController::class, 'index'])->name('scraping.index');
Route::post('/scraping/run', [ScrapingController::class, 'run'])->name('scraping.run');

Route::get('/shopify/sync', [ShopifyController::class, 'index'])->name('shopify.sync');
Route::post('/shopify/sync', [ShopifyController::class, 'sync'])->name('shopify.sync.run');

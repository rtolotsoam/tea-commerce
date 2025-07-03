<?php

use Illuminate\Foundation\Application;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule) {
        // Scraping quotidien des prix à 6h du matin
        $schedule->command('scrape:prices')
            ->dailyAt('06:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/scraping.log'));

        // Synchronisation Shopify toutes les 4 heures
        $schedule->command('shopify:sync-prices')
            ->everyFourHours()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/shopify-sync.log'));

        // Calcul des marges toutes les heures
        $schedule->command('margins:calculate')
            ->hourly()
            ->withoutOverlapping();

        // Nettoyage des vieux fichiers d'export (plus de 7 jours)
        $schedule->call(function () {
            $storageService = app(\App\Services\StorageService::class);
            $files = $storageService->listFiles('exports');

            foreach ($files as $file) {
                if (preg_match('/(\d{4})\/(\d{2})\/(\d{2})/', $file, $matches)) {
                    $fileDate = "{$matches[1]}-{$matches[2]}-{$matches[3]}";
                    if (now()->diffInDays($fileDate) > 7) {
                        $storageService->delete($file);
                    }
                }
            }
        })->daily();
    })
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use App\Services\CsvExportService;
use App\Http\Controllers\Controller;

class ExportController extends Controller
{
    public function __construct(
        private CsvExportService $exportService,
        private StorageService $storageService
    ) {
    }

    public function export(Request $request, string $type): JsonResponse
    {
        try {
            $fileInfo = match($type) {
                'margins' => $this->exportService->exportMarginReport(),
                'stocks' => $this->exportService->exportStockAnalysis(),
                'purchases' => $this->exportService->exportPurchaseMargins($request->get('purchase_id')),
                default => throw new \InvalidArgumentException("Type d'export invalide: {$type}")
            };

            return response()->json([
                'success' => true,
                'message' => 'Export réussi',
                'file' => $fileInfo,
                'download_url' => $this->storageService->getTemporaryUrl($fileInfo['path'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    public function list(): JsonResponse
    {
        $files = $this->storageService->listFiles('exports');

        $formattedFiles = array_map(function ($path) {
            $info = pathinfo($path);
            return [
                'path' => $path,
                'name' => $info['basename'],
                'url' => $this->storageService->getTemporaryUrl($path),
                'created_at' => $this->getFileCreatedAt($path)
            ];
        }, $files);

        // Trier par date décroissante
        usort($formattedFiles, function ($a, $b) {
            return $b['created_at'] <=> $a['created_at'];
        });

        return response()->json([
            'files' => array_slice($formattedFiles, 0, 50) // Limiter à 50 fichiers
        ]);
    }

    private function getFileCreatedAt(string $path): string
    {
        // Extraire la date du chemin: exports/2025/07/02/filename.csv
        if (preg_match('/(\d{4})\/(\d{2})\/(\d{2})/', $path, $matches)) {
            return "{$matches[1]}-{$matches[2]}-{$matches[3]}T00:00:00Z";
        }
        return now()->toIso8601String();
    }
}

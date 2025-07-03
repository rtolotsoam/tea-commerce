<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use App\Services\CsvExportService;
use App\Services\CsvImportService;
use App\Http\Controllers\Controller;

class ImportExportController extends Controller
{
    public function __construct(
        private CsvImportService $importService,
        private CsvExportService $exportService,
        private StorageService $storageService
    ) {
    }

    public function index()
    {
        return view('import-export.index');
    }

    /**
     * Importer un fichier CSV de commandes
     */
    public function importPurchases(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240'
        ]);

        // Uploader le fichier dans MinIO
        $fileInfo = $this->storageService->uploadCsv($request->file('file'));

        // Télécharger le fichier pour le traiter
        $tempPath = tempnam(sys_get_temp_dir(), 'import_');
        file_put_contents($tempPath, $this->storageService->download($fileInfo['path']));

        // Importer les données
        $result = $this->importService->importPurchases($tempPath);

        // Nettoyer
        unlink($tempPath);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['success']
                ? "Import réussi: {$result['imported']} commandes importées"
                : "Erreur lors de l'import",
            'errors' => $result['errors'] ?? [],
            'file_info' => $fileInfo
        ]);
    }

    /**
     * Exporter le rapport des marges
     */
    public function exportMargins(): JsonResponse
    {
        $fileInfo = $this->exportService->exportMarginReport();

        return response()->json([
            'success' => true,
            'message' => 'Export réussi',
            'file' => $fileInfo,
            'download_url' => $this->storageService->getTemporaryUrl($fileInfo['path'])
        ]);
    }

    /**
     * Exporter l'analyse des stocks
     */
    public function exportStocks(): JsonResponse
    {
        $fileInfo = $this->exportService->exportStockAnalysis();

        return response()->json([
            'success' => true,
            'message' => 'Export réussi',
            'file' => $fileInfo,
            'download_url' => $this->storageService->getTemporaryUrl($fileInfo['path'])
        ]);
    }

    /**
     * Lister les fichiers exportés
     */
    public function listExports(): JsonResponse
    {
        $files = $this->storageService->listFiles('exports');

        return response()->json([
            'files' => array_map(function ($path) {
                return [
                    'path' => $path,
                    'name' => basename($path),
                    'url' => $this->storageService->getTemporaryUrl($path)
                ];
            }, $files)
        ]);
    }
}

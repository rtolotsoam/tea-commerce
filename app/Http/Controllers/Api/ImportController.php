<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use App\Services\CsvImportService;
use App\Http\Controllers\Controller;

class ImportController extends Controller
{
    public function __construct(
        private CsvImportService $importService,
        private StorageService $storageService
    ) {
    }

    public function import(Request $request, string $type): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240'
        ]);

        try {
            // Uploader le fichier
            $fileInfo = $this->storageService->uploadCsv($request->file('file'), "imports/{$type}");

            // Télécharger pour traitement
            $tempPath = tempnam(sys_get_temp_dir(), 'import_');
            file_put_contents($tempPath, $this->storageService->download($fileInfo['path']));

            // Importer selon le type
            $result = match($type) {
                'purchases' => $this->importService->importPurchases($tempPath),
                'products' => $this->importService->importProducts($tempPath),
                'conditions' => $this->importService->importPurchaseConditions($tempPath),
                default => throw new \InvalidArgumentException("Type d'import invalide: {$type}")
            };

            unlink($tempPath);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => "Import réussi: {$result['imported']} lignes importées",
                    'imported' => $result['imported'],
                    'errors' => $result['errors'] ?? [],
                    'file_info' => $fileInfo
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Erreur lors de l\'import',
                'errors' => $result['errors'] ?? []
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    public function template(string $type)
    {
        $templates = [
            'purchases' => [
                'filename' => 'template_commandes.csv',
                'headers' => ['purchase_number', 'supplier_code', 'order_date', 'delivery_date', 'product_sku', 'quantity', 'unit_price', 'discount_percent', 'tax_rate'],
                'example' => ['PO-2025-001', 'SUPP001', '2025-07-01', '2025-07-15', 'TEA-001', '10', '45.00', '5', '20']
            ],
            'products' => [
                'filename' => 'template_produits.csv',
                'headers' => ['sku', 'name', 'category', 'supplier_code', 'supplier_ref', 'unit_weight', 'unit_type', 'min_order_qty', 'shopify_product_id', 'active'],
                'example' => ['TEA-001', 'Thé Vert Sencha', 'thes-verts', 'SUPP003', 'JGT-SEN-001', '0.1', 'kg', '5', '7854321098765', '1']
            ],
            'conditions' => [
                'filename' => 'template_conditions.csv',
                'headers' => ['supplier_code', 'product_sku', 'qty_min', 'qty_max', 'unit_price', 'discount_percent', 'valid_from', 'valid_until'],
                'example' => ['SUPP001', 'TEA-003', '0', '49', '45.00', '0', '2025-01-01', '2025-12-31']
            ]
        ];

        if (!isset($templates[$type])) {
            abort(404, "Template non trouvé");
        }

        $template = $templates[$type];
        $csv = implode(',', $template['headers']) . "\n";
        $csv .= implode(',', $template['example']) . "\n";

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $template['filename'] . '"');
    }
}

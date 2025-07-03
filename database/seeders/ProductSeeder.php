<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            // Thés Verts
            [
        'sku' => 'TEA-001',
        'name' => 'Thé Vert Sencha Premium',
        'description' => 'Thé vert japonais de première récolte',
        'category_slug' => 'thes-verts',
        'supplier_code' => 'SUPP003',
        'supplier_ref' => 'JGT-SEN-001',
        'unit_weight' => 0.1,
        'unit_type' => 'kg',
        'min_order_quantity' => 5,
        'lead_time_days' => 7,
        'shopify_product_id' => '7854321098765',
        'shopify_variant_id' => '42345678901234',
            ],
            [
        'sku' => 'TEA-002',
        'name' => 'Thé Noir Ceylon OP',
        'description' => 'Thé noir de Ceylan Orange Pekoe',
        'category_slug' => 'thes-noirs',
        'supplier_code' => 'SUPP002',
        'supplier_ref' => 'CTI-CEY-OP1',
        'unit_weight' => 0.1,
        'unit_type' => 'kg',
        'min_order_quantity' => 10,
        'lead_time_days' => 14,
        'shopify_product_id' => '7854321098766',
        'shopify_variant_id' => '42345678901235',
            ],
            [
        'sku' => 'TEA-003',
        'name' => 'Thé Vert Gunpowder Bio',
        'description' => 'Thé vert chinois biologique en perles',
        'category_slug' => 'thes-verts',
        'supplier_code' => 'SUPP001',
        'supplier_ref' => 'TDC-GUN-BIO',
        'unit_weight' => 0.1,
        'unit_type' => 'kg',
        'min_order_quantity' => 20,
        'lead_time_days' => 10,
        'shopify_product_id' => '7854321098767',
        'shopify_variant_id' => '42345678901236',
            ],
            [
        'sku' => 'TEA-004',
        'name' => 'Thé Blanc Pai Mu Tan',
        'description' => 'Thé blanc chinois aux notes délicates',
        'category_slug' => 'thes-blancs',
        'supplier_code' => 'SUPP001',
        'supplier_ref' => 'TDC-PMT-001',
        'unit_weight' => 0.05,
        'unit_type' => 'kg',
        'min_order_quantity' => 10,
        'lead_time_days' => 10,
        'shopify_product_id' => '7854321098768',
        'shopify_variant_id' => '42345678901237',
            ],
            [
        'sku' => 'TEA-005',
        'name' => 'Thé Oolong Formosa',
        'description' => 'Thé oolong de Taiwan semi-fermenté',
        'category_slug' => 'thes-oolong',
        'supplier_code' => 'SUPP001',
        'supplier_ref' => 'TDC-OOL-FOR',
        'unit_weight' => 0.1,
        'unit_type' => 'kg',
        'min_order_quantity' => 5,
        'lead_time_days' => 12,
        'shopify_product_id' => '7854321098769',
        'shopify_variant_id' => '42345678901238',
            ],
            // Infusions
            [
        'sku' => 'INF-001',
        'name' => 'Infusion Camomille',
        'description' => 'Fleurs de camomille pour infusion apaisante',
        'category_slug' => 'infusions',
        'supplier_code' => 'SUPP004',
        'supplier_ref' => 'HP-CAM-001',
        'unit_weight' => 0.05,
        'unit_type' => 'kg',
        'min_order_quantity' => 10,
        'lead_time_days' => 7,
        'shopify_product_id' => '7854321098770',
        'shopify_variant_id' => '42345678901239',
            ],
            [
        'sku' => 'INF-002',
        'name' => 'Infusion Menthe Poivrée',
        'description' => 'Feuilles de menthe poivrée séchées',
        'category_slug' => 'infusions',
        'supplier_code' => 'SUPP004',
        'supplier_ref' => 'HP-MEN-001',
        'unit_weight' => 0.05,
        'unit_type' => 'kg',
        'min_order_quantity' => 10,
        'lead_time_days' => 7,
        'shopify_product_id' => '7854321098771',
        'shopify_variant_id' => '42345678901240',
            ],
            [
        'sku' => 'INF-003',
        'name' => 'Rooibos Vanille',
        'description' => 'Rooibos d\'Afrique du Sud aromatisé vanille',
        'category_slug' => 'rooibos',
        'supplier_code' => 'SUPP004',
        'supplier_ref' => 'HP-ROO-VAN',
        'unit_weight' => 0.1,
        'unit_type' => 'kg',
        'min_order_quantity' => 15,
        'lead_time_days' => 14,
        'shopify_product_id' => '7854321098772',
        'shopify_variant_id' => '42345678901241',
            ],
            // Thés aromatisés
            [
        'sku' => 'TEA-006',
        'name' => 'Earl Grey Premium',
        'description' => 'Thé noir aromatisé à la bergamote',
        'category_slug' => 'thes-noirs',
        'supplier_code' => 'SUPP002',
        'supplier_ref' => 'CTI-EARL-01',
        'unit_weight' => 0.1,
        'unit_type' => 'kg',
        'min_order_quantity' => 10,
        'lead_time_days' => 10,
        'shopify_product_id' => '7854321098773',
        'shopify_variant_id' => '42345678901242',
            ],
            [
        'sku' => 'TEA-007',
        'name' => 'Thé Vert Jasmin',
        'description' => 'Thé vert parfumé aux fleurs de jasmin',
        'category_slug' => 'thes-verts',
        'supplier_code' => 'SUPP001',
        'supplier_ref' => 'TDC-JAS-001',
        'unit_weight' => 0.1,
        'unit_type' => 'kg',
        'min_order_quantity' => 15,
        'lead_time_days' => 10,
        'shopify_product_id' => '7854321098774',
        'shopify_variant_id' => '42345678901243',
            ],
        ];

        foreach ($products as $productData) {
            $category = Category::where('slug', $productData['category_slug'])->first();
            $supplier = Supplier::where('code', $productData['supplier_code'])->first();

            unset($productData['category_slug'], $productData['supplier_code']);

            Product::create(array_merge($productData, [
                'category_id' => $category->id,
                'supplier_id' => $supplier->id,
                'is_active' => true,
            ]));
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
                    SupplierSeeder::class,
                    CategorySeeder::class,
                    ProductSeeder::class,
                    PurchaseConditionSeeder::class,
                    PurchaseSeeder::class,
                    StockSeeder::class,
                    ShopifyPriceSeeder::class,
                    ScrapedDataSeeder::class,
                ]);

        // Calculer les marges après avoir inséré toutes les données
        app(\App\Services\MarginCalculationService::class)->calculateAllMargins();
    }
}

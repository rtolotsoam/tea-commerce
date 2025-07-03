<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
           [
               'code' => 'SUPP001',
               'name' => 'Les Thés de Chine',
               'email' => 'contact@thesdechine.com',
               'phone' => '+33123456789',
               'address' => '123 Rue du Commerce, 75001 Paris',
               'country' => 'France',
               'currency' => 'EUR',
               'payment_terms' => '30 jours',
               'is_active' => true,
           ],
           [
               'code' => 'SUPP002',
               'name' => 'Ceylon Tea Import',
               'email' => 'info@ceylontea.lk',
               'phone' => '+94112345678',
               'address' => '45 Galle Road, Colombo',
               'country' => 'Sri Lanka',
               'currency' => 'EUR',
               'payment_terms' => '45 jours',
               'is_active' => true,
           ],
           [
               'code' => 'SUPP003',
               'name' => 'Japan Green Tea Co',
               'email' => 'sales@japangreentea.jp',
               'phone' => '+81312345678',
               'address' => '1-1-1 Shibuya, Tokyo',
               'country' => 'Japon',
               'currency' => 'EUR',
               'payment_terms' => 'Paiement comptant',
               'is_active' => true,
           ],
           [
               'code' => 'SUPP004',
               'name' => 'Herbal Paradise',
               'email' => 'orders@herbalparadise.de',
               'phone' => '+49301234567',
               'address' => 'Kurfürstendamm 21, Berlin',
               'country' => 'Allemagne',
               'currency' => 'EUR',
               'payment_terms' => '30 jours',
               'is_active' => true,
           ],
           [
               'code' => 'SUPP005',
               'name' => 'Indian Spice & Tea',
               'email' => 'export@indianspicetea.in',
               'phone' => '+911123456789',
               'address' => 'Connaught Place, New Delhi',
               'country' => 'Inde',
               'currency' => 'EUR',
               'payment_terms' => '60 jours',
               'is_active' => true,
           ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::create($supplier);
        }
    }
}

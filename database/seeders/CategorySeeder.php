<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Thés Verts', 'slug' => 'thes-verts'],
            ['name' => 'Thés Noirs', 'slug' => 'thes-noirs'],
            ['name' => 'Thés Blancs', 'slug' => 'thes-blancs'],
            ['name' => 'Thés Oolong', 'slug' => 'thes-oolong'],
            ['name' => 'Infusions', 'slug' => 'infusions'],
            ['name' => 'Rooibos', 'slug' => 'rooibos'],
            ['name' => 'Maté', 'slug' => 'mate'],
            ['name' => 'Coffrets', 'slug' => 'coffrets'],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}

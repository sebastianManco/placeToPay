<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Alimentos', 'description' => 'Productos alimenticios básicos como granos, cereales, harinas y enlatados.'],
            ['name' => 'Bebidas', 'description' => 'Jugos, leches, cafés, aguas y bebidas en general.'],
            ['name' => 'Lácteos', 'description' => 'Productos derivados de la leche como yogurt, mantequilla y quesos.'],
            ['name' => 'Panadería', 'description' => 'Pan, galletas, tortas y productos de panadería.'],
            ['name' => 'Snacks y Dulces', 'description' => 'Chocolates, galletas dulces, confitería y snacks.'],
            ['name' => 'Aseo del Hogar', 'description' => 'Detergentes, limpiadores, desinfectantes y productos de limpieza.'],
            ['name' => 'Cuidado Personal', 'description' => 'Jabones, champú, cremas y productos de higiene personal.'],
            ['name' => 'Frutas y Verduras', 'description' => 'Productos frescos de frutas y verduras.'],
            ['name' => 'Carnes y Embutidos', 'description' => 'Carnes rojas, pollo, cerdo y productos procesados de carne.'],
            ['name' => 'Papelería', 'description' => 'Papel higiénico, servilletas, toallas de papel y artículos de papelería.', 'is_active' => false],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}

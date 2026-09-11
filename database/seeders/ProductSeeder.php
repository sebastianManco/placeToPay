<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure categories exist (get by name)
        $alimentos = Category::where('name', 'Alimentos')->first();
        $bebidas = Category::where('name', 'Bebidas')->first();
        $lacteos = Category::where('name', 'Lácteos')->first();
        $panaderia = Category::where('name', 'Panadería')->first();
        $snacks = Category::where('name', 'Snacks y Dulces')->first();
        $aseoHogar = Category::where('name', 'Aseo del Hogar')->first();
        $cuidadoPersonal = Category::where('name', 'Cuidado Personal')->first();

        $products = [
            ['name' => 'Arroz x 5kg', 'description' => 'Arroz blanco de primera calidad, bolsa de 5 kilogramos.', 'price' => 18500, 'stock' => 50, 'category_id' => $alimentos?->id],
            ['name' => 'Aceite de Girasol x 1L', 'description' => 'Aceite vegetal de girasol, botella de 1 litro.', 'price' => 12300, 'stock' => 35, 'category_id' => $alimentos?->id],
            ['name' => 'Azúcar x 2.5kg', 'description' => 'Azúcar blanca refinada, paquete de 2.5 kilogramos.', 'price' => 8900, 'stock' => 60, 'category_id' => $alimentos?->id],
            ['name' => 'Leche Entera x 1L', 'description' => 'Leche entera pasteurizada, caja de 1 litro.', 'price' => 4200, 'stock' => 100, 'category_id' => $bebidas?->id],
            ['name' => 'Café Molido x 500g', 'description' => 'Café colombiano molido, paquete de 500 gramos.', 'price' => 15800, 'stock' => 40, 'category_id' => $bebidas?->id],
            ['name' => 'Pasta Spaghetti x 500g', 'description' => 'Pasta tipo spaghetti, paquete de 500 gramos.', 'price' => 3500, 'stock' => 80, 'category_id' => $alimentos?->id],
            ['name' => 'Atún en Lata x 170g', 'description' => 'Atún en aceite, lata de 170 gramos.', 'price' => 6200, 'stock' => 45, 'category_id' => $alimentos?->id],
            ['name' => 'Jabón de Tocador x 3 und', 'description' => 'Pack de 3 jabones de tocador con fragancia floral.', 'price' => 7800, 'stock' => 30, 'category_id' => $cuidadoPersonal?->id],
            ['name' => 'Detergente Líquido x 3L', 'description' => 'Detergente líquido multiusos, botella de 3 litros.', 'price' => 22500, 'stock' => 25, 'category_id' => $aseoHogar?->id],
            ['name' => 'Papel Higiénico x 12 rollos', 'description' => 'Paquete de 12 rollos de papel higiénico doble hoja.', 'price' => 16900, 'stock' => 55, 'category_id' => $cuidadoPersonal?->id],
            ['name' => 'Galletas de Chocolate x 300g', 'description' => 'Galletas con chispas de chocolate, paquete de 300 gramos.', 'price' => 5400, 'stock' => 70, 'category_id' => $snacks?->id],
            ['name' => 'Harina de Trigo x 1kg', 'description' => 'Harina de trigo todo uso, paquete de 1 kilogramo.', 'price' => 4800, 'stock' => 40, 'category_id' => $alimentos?->id],
            ['name' => 'Frijoles Rojos x 500g', 'description' => 'Frijoles rojos secos, paquete de 500 gramos.', 'price' => 5600, 'stock' => 35, 'category_id' => $alimentos?->id],
            ['name' => 'Salsa de Tomate x 400g', 'description' => 'Salsa de tomate natural, frasco de 400 gramos.', 'price' => 4100, 'stock' => 50, 'category_id' => $alimentos?->id],
            ['name' => 'Mantequilla x 250g', 'description' => 'Mantequilla sin sal, barra de 250 gramos.', 'price' => 7200, 'stock' => 20, 'category_id' => $lacteos?->id],
            ['name' => 'Huevos x 30 und', 'description' => 'Cubeta de 30 huevos de gallina tipo A.', 'price' => 19800, 'stock' => 15, 'category_id' => $alimentos?->id],
            ['name' => 'Pan Tajado x 500g', 'description' => 'Pan blanco tajado, paquete de 500 gramos.', 'price' => 6500, 'stock' => 0, 'is_active' => false, 'category_id' => $panaderia?->id],
            ['name' => 'Cereal de Maíz x 350g', 'description' => 'Cereal de hojuelas de maíz, caja de 350 gramos.', 'price' => 11200, 'stock' => 0, 'is_active' => false, 'category_id' => $snacks?->id],
            ['name' => 'Yogurt Natural x 1L', 'description' => 'Yogurt natural sin azúcar, botella de 1 litro.', 'price' => 8500, 'stock' => 28, 'category_id' => $lacteos?->id],
            ['name' => 'Chocolate en Polvo x 400g', 'description' => 'Chocolate en polvo para preparar bebida, lata de 400 gramos.', 'price' => 9800, 'stock' => 33, 'category_id' => $bebidas?->id],
        ];

        foreach ($products as $product) {
            Product::firstOrCreate(
                ['name' => $product['name']],
                $product
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Módulo de Clientes
            [
                'name' => 'Ver clientes',
                'slug' => 'clients.view',
                'module' => 'clients',
                'description' => 'Permite consultar el listado y detalle de los clientes registrados.',
            ],
            [
                'name' => 'Editar clientes',
                'slug' => 'clients.edit',
                'module' => 'clients',
                'description' => 'Permite modificar la información de los clientes y sus roles asignados.',
            ],
            [
                'name' => 'Activar o desactivar clientes',
                'slug' => 'clients.toggle-status',
                'module' => 'clients',
                'description' => 'Permite habilitar o deshabilitar la cuenta de un cliente.',
            ],

            // Módulo de Productos
            [
                'name' => 'Ver productos',
                'slug' => 'products.view',
                'module' => 'products',
                'description' => 'Permite listar y filtrar los productos en el panel administrativo.',
            ],
            [
                'name' => 'Crear productos',
                'slug' => 'products.create',
                'module' => 'products',
                'description' => 'Permite registrar nuevos productos en el catálogo comercial.',
            ],
            [
                'name' => 'Editar productos',
                'slug' => 'products.edit',
                'module' => 'products',
                'description' => 'Permite actualizar precios, stock y datos de productos existentes.',
            ],
            [
                'name' => 'Activar o desactivar productos',
                'slug' => 'products.toggle-status',
                'module' => 'products',
                'description' => 'Permite alternar el estado activo de los productos.',
            ],
            [
                'name' => 'Exportar productos',
                'slug' => 'products.export',
                'module' => 'products',
                'description' => 'Permite descargar el catálogo masivo en formato Excel o CSV.',
            ],
            [
                'name' => 'Importar productos',
                'slug' => 'products.import',
                'module' => 'products',
                'description' => 'Permite la importación y actualización masiva de productos.',
            ],

            // Módulo de Categorías
            [
                'name' => 'Ver categorías',
                'slug' => 'categories.view',
                'module' => 'categories',
                'description' => 'Permite consultar el catálogo de categorías.',
            ],
            [
                'name' => 'Crear categorías',
                'slug' => 'categories.create',
                'module' => 'categories',
                'description' => 'Permite crear nuevas categorías para la clasificación de productos.',
            ],
            [
                'name' => 'Editar categorías',
                'slug' => 'categories.edit',
                'module' => 'categories',
                'description' => 'Permite modificar el nombre y descripción de categorías existentes.',
            ],
            [
                'name' => 'Activar o desactivar categorías',
                'slug' => 'categories.toggle-status',
                'module' => 'categories',
                'description' => 'Permite alternar el estado activo de las categorías.',
            ],

            // Módulo de Pedidos
            [
                'name' => 'Ver pedidos',
                'slug' => 'orders.view',
                'module' => 'orders',
                'description' => 'Permite consultar el listado y detalle de todos los pedidos de la tienda.',
            ],
            [
                'name' => 'Actualizar estado de pedidos',
                'slug' => 'orders.update-status',
                'module' => 'orders',
                'description' => 'Permite modificar el estado logístico/comercial de los pedidos.',
            ],

            // Módulo de Reportes
            [
                'name' => 'Ver reportes',
                'slug' => 'reports.view',
                'module' => 'reports',
                'description' => 'Permite acceder al panel de inteligencia de negocios y reportes.',
            ],
            [
                'name' => 'Generar reportes',
                'slug' => 'reports.create',
                'module' => 'reports',
                'description' => 'Permite solicitar la generación de reportes analíticos.',
            ],
            [
                'name' => 'Descargar reportes',
                'slug' => 'reports.download',
                'module' => 'reports',
                'description' => 'Permite descargar los reportes generados en PDF o Excel.',
            ],

            // Módulo de Roles y Permisos (ACL)
            [
                'name' => 'Ver roles y permisos',
                'slug' => 'roles.view',
                'module' => 'roles',
                'description' => 'Permite ver los roles definidos y la matriz de permisos.',
            ],
            [
                'name' => 'Crear roles',
                'slug' => 'roles.create',
                'module' => 'roles',
                'description' => 'Permite registrar nuevos roles en el sistema.',
            ],
            [
                'name' => 'Editar roles',
                'slug' => 'roles.edit',
                'module' => 'roles',
                'description' => 'Permite modificar nombres y permisos asociados a los roles.',
            ],
            [
                'name' => 'Eliminar roles',
                'slug' => 'roles.delete',
                'module' => 'roles',
                'description' => 'Permite eliminar roles personalizados no protegidos.',
            ],
        ];

        $createdPermissions = [];
        foreach ($permissions as $permData) {
            $createdPermissions[$permData['slug']] = Permission::updateOrCreate(
                ['slug' => $permData['slug']],
                $permData
            );
        }

        // Roles por defecto
        $adminRole = Role::updateOrCreate(
            ['slug' => 'admin'],
            [
                'name' => 'Administrador General',
                'description' => 'Acceso total y control absoluto sobre todas las funcionalidades del sistema.',
                'is_system' => true,
            ]
        );
        $adminRole->syncPermissions(array_values($createdPermissions));

        $catalogManagerRole = Role::updateOrCreate(
            ['slug' => 'catalog_manager'],
            [
                'name' => 'Gestor de Catálogo',
                'description' => 'Administra productos, categorías, importaciones y exportaciones masivas.',
                'is_system' => false,
            ]
        );
        $catalogManagerRole->syncPermissions([
            'products.view',
            'products.create',
            'products.edit',
            'products.toggle-status',
            'products.export',
            'products.import',
            'categories.view',
            'categories.create',
            'categories.edit',
            'categories.toggle-status',
        ]);

        $orderManagerRole = Role::updateOrCreate(
            ['slug' => 'order_manager'],
            [
                'name' => 'Gestor de Pedidos',
                'description' => 'Supervisa y actualiza el estado de las órdenes y compras de clientes.',
                'is_system' => false,
            ]
        );
        $orderManagerRole->syncPermissions([
            'orders.view',
            'orders.update-status',
        ]);

        $auditorRole = Role::updateOrCreate(
            ['slug' => 'auditor'],
            [
                'name' => 'Auditor',
                'description' => 'Acceso de solo lectura para reportes, pedidos, productos y clientes.',
                'is_system' => false,
            ]
        );
        $auditorRole->syncPermissions([
            'clients.view',
            'products.view',
            'categories.view',
            'orders.view',
            'reports.view',
            'reports.download',
        ]);

        $clientRole = Role::updateOrCreate(
            ['slug' => 'client'],
            [
                'name' => 'Cliente',
                'description' => 'Usuario cliente con acceso a la tienda y su historial de compras.',
                'is_system' => true,
            ]
        );

        // Asignar roles a usuarios existentes según su campo role
        User::where('role', 'admin')->each(function (User $user) use ($adminRole) {
            $user->assignRole($adminRole);
        });

        User::where('role', 'client')->each(function (User $user) use ($clientRole) {
            $user->assignRole($clientRole);
        });
    }
}

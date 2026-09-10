<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'identification' => 10000001,
                'name' => 'Admin',
                'last_name' => 'PlaceToPay',
                'email' => 'admin@placetopay.com',
                'phone' => '3001234567',
                'direction' => 'Calle 10 # 20-30',
                'user_name' => 'admin',
                'password' => Hash::make('Admin123*'),
                'email_verified_at' => now(),
                'is_active' => true,
                'role' => 'admin',
            ],
            [
                'identification' => 10000002,
                'name' => 'Cliente',
                'last_name' => 'Prueba',
                'email' => 'cliente@placetopay.com',
                'phone' => '3007654321',
                'direction' => 'Carrera 45 # 50-60',
                'user_name' => 'cliente',
                'password' => Hash::make('Cliente123*'),
                'email_verified_at' => now(),
                'is_active' => true,
                'role' => 'client',
            ],
            [
                'identification' => 10000003,
                'name' => 'Cliente Inactivo',
                'last_name' => 'Prueba',
                'email' => 'inactivo@placetopay.com',
                'phone' => '3001112233',
                'direction' => 'Avenida 68 # 80-90',
                'user_name' => 'inactivo',
                'password' => Hash::make('Inactivo123*'),
                'email_verified_at' => now(),
                'is_active' => false,
                'role' => 'client',
            ],
        ];

        foreach ($users as $userData) {
            $roleSlug = $userData['role'] ?? 'client';
            unset($userData['role']);

            $user = User::updateOrCreate(
                ['identification' => $userData['identification']],
                $userData
            );

            $user->assignRole($roleSlug);
        }
    }
}

<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'role')) {
            // Asegurar que existan los roles base de sistema
            $adminRole = Role::firstOrCreate(
                ['slug' => 'admin'],
                ['name' => 'Administrador General', 'description' => 'Acceso total y control absoluto.', 'is_system' => true]
            );

            $clientRole = Role::firstOrCreate(
                ['slug' => 'client'],
                ['name' => 'Cliente', 'description' => 'Usuario cliente de la tienda.', 'is_system' => true]
            );

            // Migrar datos de la columna role a la tabla pivot role_user
            $users = DB::table('users')->select('id', 'role')->get();
            foreach ($users as $user) {
                $roleId = ($user->role === 'admin') ? $adminRole->id : $clientRole->id;

                $exists = DB::table('role_user')
                    ->where('user_id', $user->id)
                    ->where('role_id', $roleId)
                    ->exists();

                if (! $exists) {
                    DB::table('role_user')->insert([
                        'user_id' => $user->id,
                        'role_id' => $roleId,
                    ]);
                }
            }

            // Eliminar la columna role de la tabla users
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role', 20)->default('client')->after('is_active');
            });

            // Re-poblar columna role según relaciones existentes
            $adminRole = DB::table('roles')->where('slug', 'admin')->first();
            if ($adminRole) {
                $adminUserIds = DB::table('role_user')
                    ->where('role_id', $adminRole->id)
                    ->pluck('user_id');

                DB::table('users')->whereIn('id', $adminUserIds)->update(['role' => 'admin']);
            }

            DB::table('users')->whereNull('role')->orWhere('role', '')->update(['role' => 'client']);
        }
    }
};

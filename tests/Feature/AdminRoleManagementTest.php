<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    protected function createAdminUser(array $attributes = []): User
    {
        static $counter = 1;
        $id = 73000000 + ($counter++);

        return User::create(array_merge([
            'identification' => $id,
            'name' => "AdminRole{$counter}",
            'last_Name' => 'Supervisor',
            'email' => "admin_role{$counter}@example.com",
            'phone' => '3005554433',
            'direction' => 'Carrera 7 # 32',
            'user_name' => "adminroleuser{$counter}",
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'admin',
        ], $attributes));
    }

    public function test_admin_can_view_roles_list(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->get('/admin/roles');

        $response->assertStatus(200);
        $response->assertSee('Administrador General');
        $response->assertSee('Gestor de Catálogo');
        $response->assertSee('Control de Acceso (ACL)');
    }

    public function test_admin_can_view_create_role_page(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->get('/admin/roles/create');

        $response->assertStatus(200);
        $response->assertSee('Crear Nuevo Rol');
        $response->assertSee('products.view');
        $response->assertSee('clients.view');
    }

    public function test_admin_can_create_a_new_role_with_permissions(): void
    {
        $admin = $this->createAdminUser();

        $perm1 = Permission::where('slug', 'products.view')->firstOrFail();
        $perm2 = Permission::where('slug', 'orders.view')->firstOrFail();

        $response = $this->actingAs($admin)->post('/admin/roles', [
            'name' => 'Supervisor de Ventas',
            'slug' => 'sales_supervisor',
            'description' => 'Supervisa ventas y productos',
            'permissions' => [$perm1->id, $perm2->id],
        ]);

        $response->assertRedirect('/admin/roles');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('roles', [
            'name' => 'Supervisor de Ventas',
            'slug' => 'sales_supervisor',
        ]);

        $role = Role::where('slug', 'sales_supervisor')->firstOrFail();
        $this->assertTrue($role->hasPermission('products.view'));
        $this->assertTrue($role->hasPermission('orders.view'));
        $this->assertFalse($role->hasPermission('clients.view'));
    }

    public function test_role_creation_requires_unique_slug_and_valid_name(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->post('/admin/roles', [
            'name' => '',
            'slug' => 'admin', // already exists in seeder
        ]);

        $response->assertSessionHasErrors(['name', 'slug']);
    }

    public function test_admin_can_view_edit_role_page(): void
    {
        $admin = $this->createAdminUser();
        $role = Role::where('slug', 'catalog_manager')->firstOrFail();

        $response = $this->actingAs($admin)->get("/admin/roles/{$role->id}/edit");

        $response->assertStatus(200);
        $response->assertSee('Editar Rol:');
        $response->assertSee('Gestor de Catálogo');
    }

    public function test_admin_can_update_role_and_sync_permissions(): void
    {
        $admin = $this->createAdminUser();
        $role = Role::create([
            'name' => 'Custom Role',
            'slug' => 'custom_role',
            'description' => 'Initial description',
            'is_system' => false,
        ]);

        $perm = Permission::where('slug', 'reports.view')->firstOrFail();

        $response = $this->actingAs($admin)->put("/admin/roles/{$role->id}", [
            'name' => 'Updated Custom Role',
            'slug' => 'custom_role_updated',
            'description' => 'Updated description',
            'permissions' => [$perm->id],
        ]);

        $response->assertRedirect('/admin/roles');
        $response->assertSessionHas('success');

        $role->refresh();
        $this->assertEquals('Updated Custom Role', $role->name);
        $this->assertEquals('custom_role_updated', $role->slug);
        $this->assertTrue($role->hasPermission('reports.view'));
    }

    public function test_system_roles_cannot_be_deleted(): void
    {
        $admin = $this->createAdminUser();
        $adminRole = Role::where('slug', 'admin')->firstOrFail();

        $response = $this->actingAs($admin)->delete("/admin/roles/{$adminRole->id}");

        $response->assertRedirect('/admin/roles');
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['slug' => 'admin']);
    }

    public function test_custom_roles_can_be_deleted(): void
    {
        $admin = $this->createAdminUser();
        $customRole = Role::create([
            'name' => 'Temporary Role',
            'slug' => 'temp_role',
            'description' => 'Will be deleted',
            'is_system' => false,
        ]);

        $response = $this->actingAs($admin)->delete("/admin/roles/{$customRole->id}");

        $response->assertRedirect('/admin/roles');
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('roles', ['slug' => 'temp_role']);
    }

    public function test_admin_can_assign_roles_to_client_user(): void
    {
        $admin = $this->createAdminUser();
        $client = User::create([
            'identification' => 74000001,
            'name' => 'Carlos',
            'last_Name' => 'Gomez',
            'email' => 'carlos@example.com',
            'phone' => '3001112233',
            'direction' => 'Calle 10 # 20',
            'user_name' => 'carlosg',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'client',
        ]);

        $catalogRole = Role::where('slug', 'catalog_manager')->firstOrFail();

        $response = $this->actingAs($admin)->put("/admin/clients/{$client->identification}", [
            'name' => 'Carlos',
            'last_Name' => 'Gomez',
            'email' => 'carlos@example.com',
            'phone' => '3001112233',
            'direction' => 'Calle 10 # 20',
            'is_active' => true,
            'roles' => [$catalogRole->id],
        ]);

        $response->assertRedirect('/admin/clients');
        $response->assertSessionHas('success');

        $client->refresh();
        $this->assertTrue($client->hasRole('catalog_manager'));
        $this->assertTrue($client->hasPermission('products.view'));
    }
}

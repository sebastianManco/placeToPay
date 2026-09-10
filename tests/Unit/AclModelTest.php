<?php

namespace Tests\Unit;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AclModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to create a user for testing.
     */
    protected function createUser(array $attributes = []): User
    {
        static $counter = 1;
        $id = 70000000 + ($counter++);

        return User::create(array_merge([
            'identification' => $id,
            'name' => "User{$counter}",
            'last_Name' => 'Tester',
            'email' => "acl_user{$counter}@example.com",
            'phone' => '3009998888',
            'direction' => 'Calle 100 # 20',
            'user_name' => "acluser{$counter}",
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'client',
        ], $attributes));
    }

    public function test_can_create_role_and_assign_permissions(): void
    {
        $role = Role::create([
            'name' => 'Editor',
            'slug' => 'editor',
            'description' => 'Role for editors',
        ]);

        $perm1 = Permission::create([
            'name' => 'View Articles',
            'slug' => 'articles.view',
            'module' => 'articles',
        ]);

        $perm2 = Permission::create([
            'name' => 'Edit Articles',
            'slug' => 'articles.edit',
            'module' => 'articles',
        ]);

        $role->givePermission($perm1);
        $role->givePermission('articles.edit');

        $this->assertTrue($role->hasPermission('articles.view'));
        $this->assertTrue($role->hasPermission('articles.edit'));
        $this->assertFalse($role->hasPermission('articles.delete'));
        $this->assertCount(2, $role->permissions);
    }

    public function test_can_sync_role_permissions(): void
    {
        $role = Role::create(['name' => 'Manager', 'slug' => 'manager']);
        $p1 = Permission::create(['name' => 'P1', 'slug' => 'p1', 'module' => 'test']);
        $p2 = Permission::create(['name' => 'P2', 'slug' => 'p2', 'module' => 'test']);
        $p3 = Permission::create(['name' => 'P3', 'slug' => 'p3', 'module' => 'test']);

        $role->syncPermissions([$p1->id, 'p2']);
        $role->load('permissions');

        $this->assertTrue($role->hasPermission('p1'));
        $this->assertTrue($role->hasPermission('p2'));
        $this->assertFalse($role->hasPermission('p3'));

        $role->syncPermissions([$p3]);
        $role->load('permissions');

        $this->assertFalse($role->hasPermission('p1'));
        $this->assertFalse($role->hasPermission('p2'));
        $this->assertTrue($role->hasPermission('p3'));
    }

    public function test_user_can_be_assigned_and_removed_roles(): void
    {
        $user = $this->createUser();
        $role1 = Role::create(['name' => 'Role 1', 'slug' => 'role-1']);
        $role2 = Role::create(['name' => 'Role 2', 'slug' => 'role-2']);

        $user->assignRole('role-1');
        $this->assertTrue($user->hasRole('role-1'));
        $this->assertFalse($user->hasRole('role-2'));

        $user->syncRoles(['role-2']);
        $this->assertFalse($user->hasRole('role-1'));
        $this->assertTrue($user->hasRole('role-2'));

        $user->removeRole($role2);
        $this->assertFalse($user->hasRole('role-2'));
    }

    public function test_user_inherits_permissions_from_roles(): void
    {
        $user = $this->createUser();
        $role = Role::create(['name' => 'Catalog Admin', 'slug' => 'catalog_admin']);
        $permission = Permission::create([
            'name' => 'Create Products',
            'slug' => 'products.create',
            'module' => 'products',
        ]);

        $role->givePermission($permission);
        $user->assignRole($role);

        $this->assertTrue($user->hasPermission('products.create'));
        $this->assertFalse($user->hasPermission('products.delete'));
    }

    public function test_user_can_have_direct_permissions(): void
    {
        $user = $this->createUser();
        $perm = Permission::create([
            'name' => 'Direct Action',
            'slug' => 'direct.action',
            'module' => 'system',
        ]);

        $user->givePermission($perm);
        $this->assertTrue($user->hasPermission('direct.action'));

        $user->revokePermission('direct.action');
        $this->assertFalse($user->hasPermission('direct.action'));
    }

    public function test_user_permission_helpers(): void
    {
        $user = $this->createUser();
        $p1 = Permission::create(['name' => 'P1', 'slug' => 'p1', 'module' => 'm']);
        $p2 = Permission::create(['name' => 'P2', 'slug' => 'p2', 'module' => 'm']);
        $p3 = Permission::create(['name' => 'P3', 'slug' => 'p3', 'module' => 'm']);

        $user->givePermission($p1);
        $user->givePermission($p2);

        $this->assertTrue($user->hasAnyPermission(['p1', 'p3']));
        $this->assertFalse($user->hasAnyPermission(['p3', 'p4']));
        $this->assertTrue($user->hasAllPermissions(['p1', 'p2']));
        $this->assertFalse($user->hasAllPermissions(['p1', 'p3']));
    }

    public function test_admin_user_has_all_permissions_automatically(): void
    {
        // Admin created with role attribute mapped to ACL pivot
        $admin = $this->createUser(['role' => 'admin']);
        $this->assertTrue($admin->isAdmin());
        $this->assertEquals('admin', $admin->role);
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue($admin->hasPermission('any.nonexistent.permission'));
        $this->assertTrue($admin->hasAnyPermission(['nonexistent1', 'nonexistent2']));
        $this->assertTrue($admin->hasAllPermissions(['nonexistent1', 'nonexistent2']));
        $this->assertTrue($admin->hasAnyAdminPermission());

        // Also test user with role 'admin' assigned via ACL model explicitly
        $aclAdmin = $this->createUser(['role' => 'client']);
        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $aclAdmin->assignRole($adminRole);

        $this->assertTrue($aclAdmin->isAdmin());
        $this->assertTrue($aclAdmin->hasPermission('any.random.ability'));
    }

    public function test_has_any_admin_permission_detects_staff_users(): void
    {
        $regularClient = $this->createUser(['role' => 'client']);
        $this->assertFalse($regularClient->hasAnyAdminPermission());

        $staffUser = $this->createUser(['role' => 'client']);
        $managerRole = Role::create(['name' => 'Manager', 'slug' => 'manager']);
        $staffUser->assignRole($managerRole);

        $this->assertTrue($staffUser->hasAnyAdminPermission());
    }
}

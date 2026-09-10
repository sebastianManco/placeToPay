<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RoleMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test users table does not contain role column after migration.
     */
    public function test_users_table_does_not_contain_role_column(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'role'));
        $this->assertTrue(Schema::hasTable('role_user'));
        $this->assertTrue(Schema::hasTable('roles'));
    }

    /**
     * Test user creation assigns default client role via ACL pivot.
     */
    public function test_new_user_gets_default_client_role_via_acl(): void
    {
        $user = User::create([
            'identification' => 88000001,
            'name' => 'DefaultClient',
            'last_name' => 'Tester',
            'email' => 'defaultclient@example.com',
            'phone' => '3001234567',
            'direction' => 'Calle 1 # 2-3',
            'user_name' => 'defclient',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $this->assertTrue($user->hasRole('client'));
        $this->assertFalse($user->isAdmin());
        $this->assertEquals('client', $user->role);
        $this->assertDatabaseHas('role_user', [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Test user creation with role attribute assigns admin role via ACL pivot.
     */
    public function test_user_creation_with_admin_role_attribute_assigns_via_acl(): void
    {
        $admin = User::create([
            'identification' => 88000002,
            'name' => 'SuperAdmin',
            'last_name' => 'Tester',
            'email' => 'superadmin@example.com',
            'phone' => '3001234567',
            'direction' => 'Calle 1 # 2-3',
            'user_name' => 'superadmin',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'admin',
        ]);

        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue($admin->isAdmin());
        $this->assertEquals('admin', $admin->role);
        $this->assertDatabaseHas('role_user', [
            'user_id' => $admin->id,
        ]);
    }

    /**
     * Test assigning custom role dynamically updates roles relation.
     */
    public function test_dynamic_role_assignment_and_sync(): void
    {
        $user = User::create([
            'identification' => 88000003,
            'name' => 'CustomUser',
            'last_name' => 'Tester',
            'email' => 'customuser@example.com',
            'phone' => '3001234567',
            'direction' => 'Calle 1 # 2-3',
            'user_name' => 'customuser',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $managerRole = Role::create([
            'name' => 'Manager',
            'slug' => 'manager',
            'description' => 'Manager role',
        ]);

        $user->syncRoles([$managerRole->id]);
        $user->refresh();

        $this->assertTrue($user->hasRole('manager'));
        $this->assertEquals('manager', $user->role);
    }
}

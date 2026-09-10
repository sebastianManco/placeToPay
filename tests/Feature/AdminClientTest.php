<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminClientTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to create an admin user.
     */
    protected function createAdmin(array $attributes = []): User
    {
        $role = $attributes['role'] ?? 'admin';
        unset($attributes['role']);

        $user = User::create(array_merge([
            'identification' => 40000001,
            'name' => 'Admin',
            'last_name' => 'System',
            'email' => 'admin@example.com',
            'phone' => '123456789',
            'direction' => 'Headquarters',
            'user_name' => 'adminuser',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
        ], $attributes));

        $user->syncRoles([$role]);

        return $user;
    }

    /**
     * Helper to create a client user.
     */
    protected function createClient(array $attributes = []): User
    {
        static $counter = 1;
        $num = 41000000 + ($counter++);

        return User::create(array_merge([
            'identification' => $num,
            'name' => "Client{$counter}",
            'last_name' => 'Test',
            'email' => "client{$counter}@example.com",
            'phone' => '3001234567',
            'direction' => 'Street 100',
            'user_name' => "clientuser{$counter}",
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'client',
        ], $attributes));
    }

    /**
     * Test guests cannot access admin clients panel.
     */
    public function test_guests_cannot_access_admin_panel(): void
    {
        $response = $this->get('/admin/clients');

        $response->assertStatus(302);
        $response->assertRedirect('/home/login');
    }

    /**
     * Test non-admin client cannot access admin panel.
     */
    public function test_non_admin_client_receives_403(): void
    {
        $client = $this->createClient();

        $response = $this->actingAs($client)->get('/admin/clients');

        $response->assertStatus(403);
    }

    /**
     * Test admin can access clients list.
     */
    public function test_admin_can_view_clients_list(): void
    {
        $admin = $this->createAdmin();
        $clientA = $this->createClient(['name' => 'Maria', 'last_name' => 'Gomez']);
        $clientB = $this->createClient(['name' => 'Carlos', 'last_name' => 'Perez']);

        $response = $this->actingAs($admin)->get('/admin/clients');

        $response->assertStatus(200);
        $response->assertSee('Administración de Clientes');
        $response->assertSee('Maria Gomez');
        $response->assertSee('Carlos Perez');
    }

    /**
     * Test admin can filter clients by search term.
     */
    public function test_admin_can_filter_clients_by_search(): void
    {
        $admin = $this->createAdmin();
        $this->createClient(['name' => 'UniqueNameXYZ', 'email' => 'uniquexyz@example.com']);
        $this->createClient(['name' => 'OtherUserABC', 'email' => 'otherabc@example.com']);

        $response = $this->actingAs($admin)->get('/admin/clients?search=UniqueNameXYZ');

        $response->assertStatus(200);
        $response->assertSee('UniqueNameXYZ');
        $response->assertDontSee('OtherUserABC');
    }

    /**
     * Test admin can filter clients by status.
     */
    public function test_admin_can_filter_clients_by_status(): void
    {
        $admin = $this->createAdmin();
        $this->createClient(['name' => 'ActiveClient', 'is_active' => true]);
        $this->createClient(['name' => 'InactiveClient', 'is_active' => false]);

        $response = $this->actingAs($admin)->get('/admin/clients?status=inactive');

        $response->assertStatus(200);
        $response->assertSee('InactiveClient');
        $response->assertDontSee('ActiveClient');
    }

    /**
     * Test admin can toggle client status from active to inactive.
     */
    public function test_admin_can_deactivate_a_client(): void
    {
        $admin = $this->createAdmin();
        $client = $this->createClient(['is_active' => true]);

        $response = $this->actingAs($admin)->patch("/admin/clients/{$client->identification}/toggle-status");

        $response->assertStatus(302);
        $response->assertSessionHas('success');
        $this->assertFalse($client->fresh()->is_active);
    }

    /**
     * Test admin can toggle client status from inactive to active.
     */
    public function test_admin_can_activate_an_inactive_client(): void
    {
        $admin = $this->createAdmin();
        $client = $this->createClient(['is_active' => false]);

        $response = $this->actingAs($admin)->patch("/admin/clients/{$client->identification}/toggle-status");

        $response->assertStatus(302);
        $response->assertSessionHas('success');
        $this->assertTrue($client->fresh()->is_active);
    }

    /**
     * Test guests cannot access client edit page.
     */
    public function test_guests_cannot_access_client_edit_page(): void
    {
        $client = $this->createClient();

        $response = $this->get("/admin/clients/{$client->identification}/edit");

        $response->assertStatus(302);
        $response->assertRedirect('/home/login');
    }

    /**
     * Test non-admin cannot access client edit page.
     */
    public function test_non_admin_cannot_access_client_edit_page(): void
    {
        $clientA = $this->createClient();
        $clientB = $this->createClient();

        $response = $this->actingAs($clientA)->get("/admin/clients/{$clientB->identification}/edit");

        $response->assertStatus(403);
    }

    /**
     * Test admin can view client edit form.
     */
    public function test_admin_can_view_client_edit_form(): void
    {
        $admin = $this->createAdmin();
        $client = $this->createClient([
            'name' => 'Alejandro',
            'last_name' => 'Ramirez',
            'email' => 'alejandro@example.com',
        ]);

        $response = $this->actingAs($admin)->get("/admin/clients/{$client->identification}/edit");

        $response->assertStatus(200);
        $response->assertSee('Editar Cliente');
        $response->assertSee('Alejandro');
        $response->assertSee('Ramirez');
        $response->assertSee('alejandro@example.com');
    }

    /**
     * Test admin receives 404 when attempting to edit a non-client user.
     */
    public function test_admin_cannot_edit_non_client_user(): void
    {
        $admin = $this->createAdmin();
        $anotherAdmin = $this->createAdmin(['identification' => 40000002, 'email' => 'admin2@example.com', 'user_name' => 'admin2']);

        $response = $this->actingAs($admin)->get("/admin/clients/{$anotherAdmin->identification}/edit");

        $response->assertStatus(404);
    }

    /**
     * Test admin can update client successfully.
     */
    public function test_admin_can_update_client_successfully(): void
    {
        $admin = $this->createAdmin();
        $client = $this->createClient([
            'name' => 'OriginalName',
            'last_name' => 'OriginalLast',
            'email' => 'original@example.com',
            'phone' => '3000000000',
            'direction' => 'Original Address',
            'is_active' => true,
        ]);

        $updateData = [
            'name' => 'UpdatedName',
            'last_name' => 'UpdatedLast',
            'email' => 'updated@example.com',
            'phone' => '3119998877',
            'direction' => 'Updated Boulevard 45',
            'is_active' => 0,
        ];

        $response = $this->actingAs($admin)->put("/admin/clients/{$client->identification}", $updateData);

        $response->assertStatus(302);
        $response->assertRedirect('/admin/clients');
        $response->assertSessionHas('success');

        $client->refresh();
        $this->assertSame('UpdatedName', $client->name);
        $this->assertSame('UpdatedLast', $client->last_name);
        $this->assertSame('updated@example.com', $client->email);
        $this->assertSame('3119998877', $client->phone);
        $this->assertSame('Updated Boulevard 45', $client->direction);
        $this->assertFalse($client->is_active);
    }

    /**
     * Test admin can update client keeping the same email (ignores own ID).
     */
    public function test_admin_can_update_client_keeping_same_email(): void
    {
        $admin = $this->createAdmin();
        $client = $this->createClient(['email' => 'sameemail@example.com']);

        $updateData = [
            'name' => 'NewNameSameEmail',
            'last_name' => 'NewLastName',
            'email' => 'sameemail@example.com',
            'phone' => '3123456789',
            'direction' => 'Street 123',
        ];

        $response = $this->actingAs($admin)->put("/admin/clients/{$client->identification}", $updateData);

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $client->refresh();
        $this->assertSame('NewNameSameEmail', $client->name);
        $this->assertSame('sameemail@example.com', $client->email);
    }

    /**
     * Test update fails when email is already in use by another user.
     */
    public function test_admin_cannot_update_client_with_duplicate_email(): void
    {
        $admin = $this->createAdmin();
        $clientA = $this->createClient(['email' => 'clientA@example.com']);
        $clientB = $this->createClient(['email' => 'clientB@example.com']);

        $response = $this->actingAs($admin)->from("/admin/clients/{$clientA->identification}/edit")
            ->put("/admin/clients/{$clientA->identification}", [
                'name' => 'ClientA Modified',
                'last_name' => 'Modified',
                'email' => 'clientB@example.com', // Duplicate email from clientB
                'phone' => '3001112233',
                'direction' => 'Street 99',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');

        $this->assertSame('clientA@example.com', $clientA->fresh()->email);
    }

    /**
     * Test client update fails on required validation rules.
     */
    public function test_client_update_requires_valid_data(): void
    {
        $admin = $this->createAdmin();
        $client = $this->createClient();

        $response = $this->actingAs($admin)->put("/admin/clients/{$client->identification}", [
            'name' => '',
            'last_name' => '',
            'email' => 'not-an-email',
            'phone' => '',
            'direction' => '',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['name', 'last_name', 'email', 'phone', 'direction']);
    }

    /**
     * Test admin index view contains edit link for each client.
     */
    public function test_clients_index_contains_edit_link(): void
    {
        $admin = $this->createAdmin();
        $client = $this->createClient(['name' => 'TargetClient']);

        $response = $this->actingAs($admin)->get('/admin/clients');

        $response->assertStatus(200);
        $response->assertSee(route('admin.clients.edit', $client));
        $response->assertSee('Editar');
    }
}

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
        return User::create(array_merge([
            'identification' => 40000001,
            'name' => 'Admin',
            'last_Name' => 'System',
            'email' => 'admin@example.com',
            'phone' => '123456789',
            'direction' => 'Headquarters',
            'user_Name' => 'adminuser',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'admin',
        ], $attributes));
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
            'last_Name' => 'Test',
            'email' => "client{$counter}@example.com",
            'phone' => '3001234567',
            'direction' => 'Street 100',
            'user_Name' => "clientuser{$counter}",
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
        $clientA = $this->createClient(['name' => 'Maria', 'last_Name' => 'Gomez']);
        $clientB = $this->createClient(['name' => 'Carlos', 'last_Name' => 'Perez']);

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
}

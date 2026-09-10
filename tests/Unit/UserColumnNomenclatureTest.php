<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserColumnNomenclatureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the database schema contains standardized snake_case column names.
     */
    public function test_users_table_has_standardized_snake_case_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'last_name'));
        $this->assertTrue(Schema::hasColumn('users', 'direction'));
        $this->assertTrue(Schema::hasColumn('users', 'user_name'));
    }

    /**
     * Test creating and retrieving user with canonical snake_case attribute names.
     */
    public function test_user_can_be_created_with_canonical_snake_case_attributes(): void
    {
        $user = User::create([
            'identification' => 77770001,
            'name' => 'Ana',
            'last_name' => 'Martinez',
            'email' => 'ana.martinez@example.com',
            'phone' => '3119876543',
            'direction' => 'Carrera 7 # 45-20',
            'user_name' => 'anamartinez',
            'password' => 'secret1234',
        ]);

        $this->assertSame('Martinez', $user->last_name);
        $this->assertSame('Carrera 7 # 45-20', $user->direction);
        $this->assertSame('anamartinez', $user->user_name);

        $fresh = $user->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame('Martinez', $fresh->last_name);
        $this->assertSame('Carrera 7 # 45-20', $fresh->direction);
        $this->assertSame('anamartinez', $fresh->user_name);
    }

    /**
     * Test backward compatibility accessors for legacy casing conventions.
     */
    public function test_user_model_supports_backward_compatibility_accessors_and_mutators(): void
    {
        $user = User::create([
            'identification' => 77770002,
            'name' => 'Carlos',
            'last_name' => 'Gomez',
            'email' => 'carlos.gomez@example.com',
            'phone' => '3111112233',
            'direction' => 'Calle 100 # 15-20',
            'user_name' => 'carlosg',
            'password' => 'secret1234',
        ]);

        // Legacy accessors
        $this->assertSame('Gomez', $user->last_Name);
        $this->assertSame('Gomez', $user->lastName);
        $this->assertSame('Calle 100 # 15-20', $user->Direction);
        $this->assertSame('carlosg', $user->user_Name);
        $this->assertSame('carlosg', $user->userName);

        // Update via legacy attribute keys
        $user->update([
            'last_Name' => 'Rodriguez',
            'Direction' => 'Diagonal 45 # 10-30',
            'user_Name' => 'crodriguez',
        ]);

        $fresh = $user->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame('Rodriguez', $fresh->last_name);
        $this->assertSame('Diagonal 45 # 10-30', $fresh->direction);
        $this->assertSame('crodriguez', $fresh->user_name);
    }
}

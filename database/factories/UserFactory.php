<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected  = User::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'identification' => fake()->unique()->randomNumber(8, true),
            'name' => fake()->firstName(),
            'last_Name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'direction' => fake()->address(),
            'user_Name' => fake()->userName(),
            'password' => bcrypt('password'),
            'confirm_Password' => 'password',
            'remember_token' => Str::random(10),
        ];
    }
}

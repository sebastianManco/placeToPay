<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test registration dispatches Registered event and verification notification.
     */
    public function test_registration_dispatches_registered_event(): void
    {
        Event::fake([Registered::class]);

        $payload = [
            'identification' => 30000001,
            'name' => 'Verify',
            'lastName' => 'Event',
            'email' => 'verify.event@example.com',
            'phone' => '3009998877',
            'direction' => 'Street 123',
            'userName' => 'verifyevent',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ];

        $response = $this->post('/home/registered', $payload);

        $response->assertStatus(302);
        Event::assertDispatched(Registered::class);
    }

    /**
     * Test unverified user can view verification notice.
     */
    public function test_verification_notice_can_be_rendered(): void
    {
        $user = User::create([
            'identification' => 30000002,
            'name' => 'Unverified',
            'last_Name' => 'User',
            'email' => 'unverified@example.com',
            'phone' => '123456789',
            'direction' => 'Street 20',
            'user_Name' => 'unverified',
            'password' => 'secret123',
            'email_verified_at' => null,
            'is_active' => true,
            'role' => 'client',
        ]);

        $response = $this->actingAs($user)->get('/email/verify');

        $response->assertStatus(200);
        $response->assertSee('Verify Your Email Address');
    }

    /**
     * Test verified user visiting verification notice is redirected to dashboard.
     */
    public function test_verified_user_visiting_notice_is_redirected(): void
    {
        $user = User::create([
            'identification' => 30000003,
            'name' => 'Already',
            'last_Name' => 'Verified',
            'email' => 'already@example.com',
            'phone' => '123456789',
            'direction' => 'Street 21',
            'user_Name' => 'alreadyverified',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'client',
        ]);

        $response = $this->actingAs($user)->get('/email/verify');

        $response->assertStatus(302);
        $response->assertRedirect('/dashboard');
    }

    /**
     * Test email can be verified with valid signed URL.
     */
    public function test_email_can_be_verified(): void
    {
        Event::fake([Verified::class]);

        $user = User::create([
            'identification' => 30000004,
            'name' => 'Signing',
            'last_Name' => 'Test',
            'email' => 'signing@example.com',
            'phone' => '123456789',
            'direction' => 'Street 22',
            'user_Name' => 'signingtest',
            'password' => 'secret123',
            'email_verified_at' => null,
            'is_active' => true,
            'role' => 'client',
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->identification, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        $response->assertStatus(302);
        $response->assertRedirect('/dashboard');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Event::assertDispatched(Verified::class);
    }

    /**
     * Test email cannot be verified with invalid signature.
     */
    public function test_email_cannot_be_verified_with_invalid_signature(): void
    {
        $user = User::create([
            'identification' => 30000005,
            'name' => 'Invalid',
            'last_Name' => 'Sig',
            'email' => 'invalidsig@example.com',
            'phone' => '123456789',
            'direction' => 'Street 23',
            'user_Name' => 'invalidsig',
            'password' => 'secret123',
            'email_verified_at' => null,
            'is_active' => true,
            'role' => 'client',
        ]);

        $invalidUrl = "/email/verify/{$user->identification}/" . sha1('wrongemail@example.com');

        $response = $this->actingAs($user)->get($invalidUrl);

        $response->assertStatus(403);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    /**
     * Test resending email verification notification.
     */
    public function test_resending_verification_email(): void
    {
        Notification::fake();

        $user = User::create([
            'identification' => 30000006,
            'name' => 'Resend',
            'last_Name' => 'User',
            'email' => 'resend@example.com',
            'phone' => '123456789',
            'direction' => 'Street 24',
            'user_Name' => 'resenduser',
            'password' => 'secret123',
            'email_verified_at' => null,
            'is_active' => true,
            'role' => 'client',
        ]);

        $response = $this->actingAs($user)->post('/email/verification-notification');

        $response->assertStatus(302);
        $response->assertSessionHas('status', 'verification-link-sent');
        Notification::assertSentTo($user, VerifyEmail::class);
    }
}

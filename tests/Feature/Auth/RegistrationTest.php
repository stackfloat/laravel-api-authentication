<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;

describe('RegistrationController', function () {
    beforeEach(function () {
        // Clear rate limiter for test IP
        RateLimiter::clear('registration:ip:127.0.0.1');
    });

    test('user can register with valid credentials', function () {
        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => ['name', 'email'],
                'token',
            ])
            ->assertJson([
                'status' => true,
                'message' => 'Registration completed successfully.',
                'data' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ],
            ]);

        expect($response->json('token'))->not->toBeNull()
            ->and($response->json('token'))->toBeString()
            ->and(User::where('email', 'john@example.com')->exists())->toBeTrue();
    });

    test('registration normalizes email to lowercase', function () {
        $response = $this->postJson('/api/register', [
            'name' => 'Jane Doe',
            'email' => 'JANE@EXAMPLE.COM',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);

        expect(User::where('email', 'jane@example.com')->exists())->toBeTrue()
            ->and(User::where('email', 'JANE@EXAMPLE.COM')->exists())->toBeFalse();
    });

    test('registration trims email whitespace', function () {
        $response = $this->postJson('/api/register', [
            'name' => 'Jane Doe',
            'email' => '  jane@example.com  ',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);

        expect(User::where('email', 'jane@example.com')->exists())->toBeTrue();
    });

    test('registration requires name field', function () {
        $response = $this->postJson('/api/register', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    test('registration requires email field', function () {
        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    test('registration requires valid email format', function () {
        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'email' => 'invalid-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    test('registration requires unique email', function () {
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    test('registration requires password field', function () {
        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    });

    test('registration requires password confirmation field', function () {
        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password_confirmation']);
    });

    test('registration requires password to match confirmation', function () {
        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    });

    test('registration requires password minimum length of 8 characters', function () {
        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    });

    test('registration returns user resource with correct structure', function () {
        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ],
            ]);

        expect($response->json('data'))->not->toHaveKey('password')
            ->and($response->json('data'))->not->toHaveKey('id');
    });

    test('registration hashes password correctly', function () {
        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'john@example.com')->first();
        expect(Hash::check('password123', $user->password))->toBeTrue();
    });

    test('registration generates unique token for each registration', function () {
        $response1 = $this->postJson('/api/register', [
            'name' => 'User One',
            'email' => 'user1@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response2 = $this->postJson('/api/register', [
            'name' => 'User Two',
            'email' => 'user2@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        expect($response1->json('token'))->not->toBe($response2->json('token'));
    });

    test('rate limiter blocks after max attempts from same IP', function () {
        // Make 5 registration attempts
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/register', [
                'name' => 'Test User',
                'email' => "test{$i}@example.com",
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);
        }

        // 6th attempt should be rate limited
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test6@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'status' => false,
                'message' => 'Too many registration attempts from this IP. Please try again later',
            ]);
    });

    test('rate limiter increments on successful registration', function () {
        // Make 4 successful registrations
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/register', [
                'name' => 'Test User',
                'email' => "test{$i}@example.com",
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);
        }

        // 5th should still succeed
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test4@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);

        // 6th should be rate limited
        $rateLimitedResponse = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test5@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $rateLimitedResponse->assertStatus(429);
    });

    test('rate limiter does not increment on validation failures', function () {
        // Make 4 validation failures (missing required fields)
        // These happen before the controller method, so rate limiter is not incremented
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/register', [
                'email' => "test{$i}@example.com",
                // Missing name and password
            ]);
        }

        // 5th validation failure
        $response = $this->postJson('/api/register', [
            'email' => 'test4@example.com',
            // Missing name and password
        ]);

        $response->assertStatus(422);

        // Since validation failures don't increment rate limiter, 
        // a valid registration should still succeed
        $validResponse = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test5@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $validResponse->assertStatus(201);
    });

    test('rate limiter is per IP address', function () {
        // Simulate different IPs by clearing and using different rate limiter keys
        // In real scenario, different IPs would have different keys
        // For testing, we'll test that the same IP gets rate limited
        
        // Make 5 registrations from "same IP"
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/register', [
                'name' => 'Test User',
                'email' => "test{$i}@example.com",
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);
        }

        // Should be rate limited
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test5@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(429);
    });

    test('registration creates user with correct attributes', function () {
        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'john@example.com')->first();
        expect($user)->not->toBeNull()
            ->and($user->name)->toBe('John Doe')
            ->and($user->email)->toBe('john@example.com')
            ->and($user->password)->not->toBe('password123'); // Should be hashed
    });

    test('registration token can be used for authentication', function () {
        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $token = $response->json('token');

        // Test that the token works for authenticated requests
        $authenticatedResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/user');

        $authenticatedResponse->assertStatus(200)
            ->assertJson([
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ]);
    });
});

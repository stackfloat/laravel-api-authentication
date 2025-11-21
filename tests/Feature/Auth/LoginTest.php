<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

describe('LoginController', function () {
    beforeEach(function () {
        RateLimiter::clear('login:test@example.com');
    });

    test('user can login with valid credentials', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => ['name', 'email'],
                'token',
            ])
            ->assertJson([
                'status' => true,
                'message' => 'Login successful.',
            ]);

        expect($response->json('data.email'))->toBe('test@example.com')
            ->and($response->json('token'))->not->toBeNull()
            ->and($response->json('token'))->toBeString();
    });

    test('login fails with invalid password', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => false,
                'message' => 'Invalid credentials.',
            ]);

        expect($response->json('token'))->toBeNull();
    });

    test('login fails when user does not exist', function () {
        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => false,
                'message' => 'Invalid credentials.',
            ]);
    });

    test('login normalizes email to lowercase', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'TEST@EXAMPLE.COM',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Login successful.',
            ]);
    });

    test('login trims email whitespace', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => '  test@example.com  ',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Login successful.',
            ]);
    });

    test('rate limiter blocks after max attempts', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Make 5 failed attempts
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'test@example.com',
                'password' => 'wrong-password',
            ]);
        }

        // 6th attempt should be rate limited
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'status' => false,
                'message' => 'Too many login attempts. Please try again later.',
            ]);
    });

    test('rate limiter clears on successful login', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Make 3 failed attempts
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/login', [
                'email' => 'test@example.com',
                'password' => 'wrong-password',
            ]);
        }

        // Successful login should clear rate limiter
        $successResponse = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $successResponse->assertStatus(200);

        // After successful login, we should be able to make more attempts
        // (rate limiter was cleared, so we can fail again without hitting 429)
        $failedResponse = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $failedResponse->assertStatus(401); // Should not be 429
    });

    test('rate limiter is per email address', function () {
        $user1 = User::factory()->create([
            'email' => 'user1@example.com',
            'password' => Hash::make('password123'),
        ]);

        $user2 = User::factory()->create([
            'email' => 'user2@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Make 5 failed attempts for user1
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'user1@example.com',
                'password' => 'wrong-password',
            ]);
        }

        // user1 should be rate limited
        $response1 = $this->postJson('/api/login', [
            'email' => 'user1@example.com',
            'password' => 'wrong-password',
        ]);
        $response1->assertStatus(429);

        // user2 should still be able to attempt login
        $response2 = $this->postJson('/api/login', [
            'email' => 'user2@example.com',
            'password' => 'wrong-password',
        ]);
        $response2->assertStatus(401); // Invalid credentials, not rate limited
    });

    test('login requires email field', function () {
        $response = $this->postJson('/api/login', [
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    test('login requires password field', function () {
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    });

    test('login requires valid email format', function () {
        $response = $this->postJson('/api/login', [
            'email' => 'invalid-email',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    test('login returns user resource with correct structure', function () {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'John Doe',
                    'email' => 'test@example.com',
                ],
            ]);

        expect($response->json('data'))->not->toHaveKey('password')
            ->and($response->json('data'))->not->toHaveKey('id');
    });

    test('login generates unique token for each login', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response1 = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response2 = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        expect($response1->json('token'))->not->toBe($response2->json('token'));
    });
});

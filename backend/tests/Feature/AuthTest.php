<?php

use App\Enums\UserRole;
use App\Models\User;

describe('register', function () {
    it('creates a customer and returns a token', function () {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Sari',
            'email' => 'sari@example.com',
            'phone' => '081234567890',
            'password' => 'secret-password',
            'device_name' => 'Pixel 8',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'sari@example.com')
            ->assertJsonPath('data.user.role', 'customer');

        expect($response->json('data.token'))->toBeString()->not->toBeEmpty();
        expect(User::firstWhere('email', 'sari@example.com')->tokens()->first()->name)->toBe('Pixel 8');
    });

    it('ignores an attempt to register as staff', function () {
        $this->postJson('/api/auth/register', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'secret-password',
            'role' => 'staff',
        ])->assertCreated()->assertJsonPath('data.user.role', 'customer');

        expect(User::firstWhere('email', 'sneaky@example.com')->role)->toBe(UserRole::Customer);
    });

    it('rejects a duplicate email with the standard error format', function () {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Someone',
            'email' => 'taken@example.com',
            'password' => 'secret-password',
        ]);

        expectApiError($response, 422, 'validation_failed');
        $response->assertJsonStructure(['error' => ['details' => ['fields' => ['email']]]]);
    });
});

describe('login', function () {
    it('returns a token for valid credentials', function () {
        User::factory()->create(['email' => 'budi@example.com', 'password' => 'secret-password']);

        $this->postJson('/api/auth/login', ['email' => 'budi@example.com', 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'budi@example.com')
            ->assertJsonStructure(['data' => ['token']]);
    });

    it('gives the same error for a wrong password and an unknown email', function () {
        User::factory()->create(['email' => 'budi@example.com', 'password' => 'secret-password']);

        expectApiError($this->postJson('/api/auth/login', ['email' => 'budi@example.com', 'password' => 'wrong']), 401, 'invalid_credentials');
        expectApiError($this->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong']), 401, 'invalid_credentials');
    });

    it('is rate limited', function () {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'x@example.com', 'password' => 'wrong'])->assertStatus(401);
        }

        expectApiError($this->postJson('/api/auth/login', ['email' => 'x@example.com', 'password' => 'wrong']), 429, 'too_many_requests');
    });
});

describe('logout', function () {
    it('revokes only the current token', function () {
        $user = User::factory()->create();
        $phone = $user->createToken('phone')->plainTextToken;
        $user->createToken('tablet');

        $this->withToken($phone)->postJson('/api/auth/logout')->assertNoContent();

        expect($user->tokens()->pluck('name')->all())->toBe(['tablet']);
    });
});

describe('profile', function () {
    it('requires a token', function () {
        expectApiError($this->getJson('/api/me'), 401, 'unauthenticated');
    });

    it('returns the current user', function () {
        $user = actingAsCustomer();

        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.id', $user->id);
    });

    it('updates name and phone but never the role', function () {
        $user = actingAsCustomer();

        $this->patchJson('/api/me', ['name' => 'New Name', 'phone' => '+628111222333', 'role' => 'staff'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.phone', '+628111222333')
            ->assertJsonPath('data.role', 'customer');

        expect($user->fresh()->role)->toBe(UserRole::Customer);
    });

    it('validates the phone number', function () {
        actingAsCustomer();

        expectApiError($this->patchJson('/api/me', ['phone' => 'not-a-phone']), 422, 'validation_failed');
    });
});

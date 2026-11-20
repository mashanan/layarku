<?php

use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/*
| Feature tests run against the MySQL test database (see phpunit.xml) and are
| wrapped in a transaction that is rolled back after each test.
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
| Concurrency tests start separate PHP processes that must see the test data,
| so it has to be committed: tables are truncated before each test instead.
*/
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Concurrency');

/** Authenticate as a fresh customer (or the given user) for API calls. */
function actingAsCustomer(?User $user = null): User
{
    $user ??= User::factory()->create();
    Sanctum::actingAs($user);

    return $user;
}

/** A studio with a small seat layout: rows A.. x seats per row. */
function studioWithSeats(int $rows = 3, int $seatsPerRow = 4, array $aisleAfter = []): Studio
{
    return Studio::factory()->withSeats($rows, $seatsPerRow, $aisleAfter)->create();
}

/** Assert a response uses the standard API error shape with the given code. */
function expectApiError(TestResponse $response, int $status, string $code): void
{
    $response->assertStatus($status)
        ->assertJsonStructure(['error' => ['code', 'message', 'details']])
        ->assertJsonPath('error.code', $code);
}

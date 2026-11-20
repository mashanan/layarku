<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo accounts, documented in the README so reviewers can log in.
 * They hold no real data; the password is intentionally public.
 */
class DemoUserSeeder extends Seeder
{
    public const CUSTOMER_EMAIL = 'customer@layarku.test';

    public const STAFF_EMAIL = 'staff@layarku.test';

    /** Owns the "other people's" sample bookings so the seat map isn't empty. */
    public const CROWD_EMAIL = 'crowd@layarku.test';

    public function run(): void
    {
        $this->user(self::CUSTOMER_EMAIL, 'Demo Customer', UserRole::Customer, 'password', '081200000001');
        $this->user(self::STAFF_EMAIL, 'Demo Staff', UserRole::Staff, 'password', '081200000002');
        // Random password: nobody is meant to log in as the crowd.
        $this->user(self::CROWD_EMAIL, 'Other Moviegoers', UserRole::Customer, Str::random(40), null);
    }

    private function user(string $email, string $name, UserRole $role, string $password, ?string $phone): void
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->fill(['name' => $name, 'phone' => $phone, 'password' => $password]);
        $user->role = $role; // not mass assignable, set explicitly
        $user->email_verified_at = now();
        $user->save();
    }
}

<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        User::query()->updateOrCreate(
            ['email' => 'admin@inventory.test'],
            [
                'name' => 'مدير النظام',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'employee@inventory.test'],
            [
                'name' => 'موظف المخزن',
                'password' => Hash::make('password'),
                'role' => UserRole::Employee,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}

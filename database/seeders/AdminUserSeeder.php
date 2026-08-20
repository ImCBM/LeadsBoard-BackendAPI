<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Create a default admin user for dashboard access.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@leadsboard.local'],
            [
                'name'     => 'Admin',
                'password' => Hash::make('password'),
                'role'     => 'admin',
            ]
        );

        $this->command->info('Admin user created: admin@leadsboard.local / password');
    }
}

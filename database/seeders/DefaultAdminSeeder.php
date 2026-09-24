<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DefaultAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@scl.local'],
            [
                'name'              => 'Administrador',
                'password'          => Hash::make('admin123'),
                'email_verified_at' => now(),
            ]
        );
    }
}

<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DefaultAdminSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('ADMIN_PASSWORD') ?: Str::password(24, symbols: false);

        $user = User::firstOrCreate(
            ['email' => 'admin@scl.local'],
            [
                'name' => 'Administrador',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        if ($user->wasRecentlyCreated && $this->command) {
            $this->command->warn('Administrador creado: admin@scl.local');
            $this->command->warn('Contraseña generada (guárdala y cámbiala): '.$password);
        }
    }
}

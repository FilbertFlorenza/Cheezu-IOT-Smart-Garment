<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminEmail = (string) env('ADMIN_EMAIL', 'admin@garment.test');
        $adminPassword = (string) env('ADMIN_PASSWORD', '');
        $operatorPassword = (string) env('OPERATOR_PASSWORD', '');

        if ($adminPassword === '') {
            if (app()->environment('production')) {
                throw new RuntimeException('Set ADMIN_PASSWORD before seeding in production.');
            }

            $adminPassword = 'password';
        }

        if (app()->environment('production') && in_array($adminPassword, ['password', 'change-me-admin-password'], true)) {
            throw new RuntimeException('Refusing to seed production with a default administrator password.');
        }

        if ($operatorPassword === '') {
            // In production, never create an operator with a known password.
            $operatorPassword = app()->environment('production') ? Str::random(40) : 'password';
        }

        User::query()->updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'System Administrator',
                'password' => Hash::make($adminPassword),
                'role' => Role::Administrator,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'operator@garment.test'],
            [
                'name' => 'Quality Operator',
                'password' => Hash::make($operatorPassword),
                'role' => Role::Operator,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        // Machine-to-machine accounts. Tokens are issued with
        // `php artisan service:token` and are never stored in the repository.
        foreach ([
            'Inference Service' => 'inference-service@service.local',
            'Cloud Forecaster' => 'cloud-forecaster@service.local',
            'Network Forecaster' => 'network-forecaster@service.local',
        ] as $name => $email) {
            User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make(Str::random(40)),
                    'role' => Role::Service,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
        }

        if (app()->environment('local')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make((string) env('SEED_USER_PASSWORD', 'password'));

        foreach ([
            ['Giftopia Admin', 'admin@giftopia.test', UserRole::Admin],
            ['Bassant', 'bassant@giftopia.test', UserRole::Producer],
            ['Giftopia Customer', 'customer@giftopia.test', UserRole::Customer],
        ] as [$name, $email, $role]) {
            User::query()->updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'role' => $role->value, 'password' => $password],
            );
        }
    }
}

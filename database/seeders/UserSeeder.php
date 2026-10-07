<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        $users = [
            ['name' => 'ユーザー1', 'email' => 'user1@example.com', 'admin_status' => false],
            ['name' => 'ユーザー2', 'email' => 'user2@example.com', 'admin_status' => false],
            ['name' => 'ユーザー3', 'email' => 'user3@example.com', 'admin_status' => true],
        ];

        foreach ($users as $user) {
            User::create([
                'name' => $user['name'],
                'email' => $user['email'],
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'admin_status' => $user['admin_status'],
            ]);
        }
    }
}

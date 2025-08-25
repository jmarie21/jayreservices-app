<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       // Admin
        User::create([
            'name' => 'John Ygot',
            'email' => 'john@gmail.com',
            'password' => 'password', // Will be hashed automatically if using $casts['password'] = 'hashed'
            'role' => 'admin',
        ]);

        // Clients
        $clients = ['merose', 'jomari'];
        foreach ($clients as $client) {
            User::create([
                'name' => ucfirst($client),
                'email' => $client . '@gmail.com',
                'password' => 'password',
                'role' => 'client',
            ]);
        }
    }
}

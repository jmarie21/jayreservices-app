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

        // Editors
        $editors = ['nami', 'penny', 'christian'];
        foreach ($editors as $editor) {
            User::create([
                'name' => ucfirst($editor),
                'email' => $editor . '@gmail.com',
                'password' => 'password',
                'role' => 'editor',
            ]);
        }

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

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::truncate();

        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@gmail.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ],
            [
                'name' => 'Clinic Manager',
                'email' => 'manager@gmail.com',
                'password' => Hash::make('password'),
                'role' => 'manager',
            ],
            [
                'name' => 'Dr. Dentist',
                'email' => 'dentist@gmail.com',
                'password' => Hash::make('password'),
                'role' => 'dentist',
            ],
            [
                'name' => 'Frontdesk Staff',
                'email' => 'staff@gmail.com',
                'password' => Hash::make('password'),
                'role' => 'staff',
            ],
            [
                'name' => 'Juan Patient',
                'email' => 'juan.patient@gmail.com',
                'password' => Hash::make('password'),
                'role' => 'patient',
            ],
            [
                'name' => 'Maria Patient',
                'email' => 'maria.patient@gmail.com',
                'password' => Hash::make('password'),
                'role' => 'patient',
            ],
        ];

        foreach ($users as $user) {
            User::create($user);
        }
    }
}

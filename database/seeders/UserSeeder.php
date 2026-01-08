<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create agents
        User::create([
            'name' => 'Sarah Martinez',
            'email' => 'sarah.martinez@company.test',
            'password' => Hash::make('password'),
            'role' => Role::Agent,
        ]);

        User::create([
            'name' => 'James Chen',
            'email' => 'james.chen@company.test',
            'password' => Hash::make('password'),
            'role' => Role::Agent,
        ]);

        User::create([
            'name' => 'Aisha Patel',
            'email' => 'aisha.patel@company.test',
            'password' => Hash::make('password'),
            'role' => Role::Agent,
        ]);

        // Create employees
        User::create([
            'name' => 'Michael Brown',
            'email' => 'michael.brown@company.test',
            'password' => Hash::make('password'),
            'role' => Role::Employee,
        ]);

        User::create([
            'name' => 'Emily Johnson',
            'email' => 'emily.johnson@company.test',
            'password' => Hash::make('password'),
            'role' => Role::Employee,
        ]);

        User::create([
            'name' => 'David Kim',
            'email' => 'david.kim@company.test',
            'password' => Hash::make('password'),
            'role' => Role::Employee,
        ]);

        User::create([
            'name' => 'Jessica Williams',
            'email' => 'jessica.williams@company.test',
            'password' => Hash::make('password'),
            'role' => Role::Employee,
        ]);

        User::create([
            'name' => 'Robert Garcia',
            'email' => 'robert.garcia@company.test',
            'password' => Hash::make('password'),
            'role' => Role::Employee,
        ]);
    }
}

<?php

namespace Workbench\Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Workbench\App\Models\User;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@acme.test'],
            ['name' => 'Ada Admin', 'password' => Hash::make('password')],
        );

        User::query()->updateOrCreate(
            ['email' => 'member@acme.test'],
            ['name' => 'Mel Member', 'password' => Hash::make('password')],
        );
    }
}

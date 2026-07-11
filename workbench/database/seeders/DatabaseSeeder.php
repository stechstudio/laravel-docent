<?php

namespace Workbench\Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use STS\Docent\Content\Models\DocentPage;
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

        $this->seedDatabasePages();
    }

    /**
     * Seed the composite store demo: a database-authored page that appears in
     * navigation alongside the file pages, plus a database partial. Nothing
     * here shadows a file, so the browser demo stays clean.
     */
    private function seedDatabasePages(): void
    {
        DocentPage::write(
            'announcements',
            <<<'MD'
            This page lives in the **database store**, composed over the markdown files
            you author in the repository. It flows through the exact same parser,
            renderer, navigation, and search pipeline.

            :::tip title="Authored in the database"
            Database pages support every directive a file page does — callouts,
            gates, dynamic values, and includes.
            :::

            Editing this content in the admin panel writes a new revision;
            readers keep seeing the published one until you publish again.
            MD,
            ['title' => 'Announcements', 'description' => 'Product news, authored in the database store.', 'order' => 5],
        )->publish();

        DocentPage::write(
            '_partials/db-note',
            'This reusable note is stored in the database and included by name.',
            ['title' => 'Database note'],
        )->publish();
    }
}

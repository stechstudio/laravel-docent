<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The database store for Docent pages. Opt-in: published via
 * `vendor:publish --tag=docent-migrations` (or `docent:install --with-database`)
 * and enabled with `docent.database.enabled`. Author columns carry no foreign
 * keys — Docent stays host-agnostic about the users table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docent_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->longText('content');
            $table->string('format')->default('markdown');
            $table->json('front_matter')->nullable();
            $table->unsignedBigInteger('published_revision_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docent_pages');
    }
};

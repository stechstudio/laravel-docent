<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An immutable snapshot of a page's content taken on every write. A page's
 * `published_revision_id` points at whichever revision the reader pipeline
 * serves; the newest revision may be an unpublished draft.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docent_page_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('docent_page_id')->constrained()->cascadeOnDelete();
            $table->longText('content');
            $table->string('format');
            $table->json('front_matter')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docent_page_revisions');
    }
};

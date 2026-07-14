<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docent_ai_questions', function (Blueprint $table): void {
            $table->id();
            $table->string('question', 500);
            $table->string('status', 20);
            $table->string('thumbs', 4)->nullable();
            $table->string('viewer_class', 32);
            $table->char('answer_hash', 64)->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docent_ai_questions');
    }
};

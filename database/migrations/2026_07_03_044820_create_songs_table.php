<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('songs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('artist')->nullable();
            $table->text('content');
            $table->string('original_key', 5)->nullable();
            $table->unsignedTinyInteger('capo')->nullable();
            $table->string('source_url')->nullable();
            $table->timestamps();
            $table->index(['title', 'artist']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('songs');
    }
};

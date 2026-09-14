<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_references', function (Blueprint $table) {
            $table->id();
            $table->string('code_article')->unique();
            $table->string('designation')->nullable();
            $table->string('emplacement')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_references');
    }
};

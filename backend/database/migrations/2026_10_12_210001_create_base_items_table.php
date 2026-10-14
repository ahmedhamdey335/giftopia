<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('base_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('base_price')->comment('Minor units (piasters)');
            $table->unsignedTinyInteger('piece_count')->default(1);
            $table->unsignedSmallInteger('labor_minutes');
            $table->unsignedSmallInteger('cure_hours');
            $table->unsignedSmallInteger('max_text_chars')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('base_items');
    }
};

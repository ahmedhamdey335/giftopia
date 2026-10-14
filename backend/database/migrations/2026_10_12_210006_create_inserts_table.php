<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inserts', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['flower', 'foil', 'shell', 'glitter']);
            $table->string('name');
            $table->string('color')->nullable();
            $table->unsignedInteger('price')->default(0)->comment('Minor units (piasters)');
            $table->unsignedSmallInteger('labor_minutes')->default(0);
            $table->decimal('min_depth_mm', 6, 2)->default(0);
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_available']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inserts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('color_swatches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('hex', 7);
            $table->enum('role', ['base', 'accent']);
            $table->integer('price_delta')->default(0)->comment('Minor units (piasters)');
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->index(['role', 'is_available']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('color_swatches');
    }
};

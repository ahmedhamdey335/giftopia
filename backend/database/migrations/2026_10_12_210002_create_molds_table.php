<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('molds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('base_item_id')->constrained('base_items')->cascadeOnDelete();
            $table->string('name');
            $table->string('size_label');
            $table->decimal('depth_mm', 6, 2);
            $table->integer('price_delta')->default(0)->comment('Minor units (piasters)');
            $table->timestamps();

            $table->index(['base_item_id', 'size_label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('molds');
    }
};

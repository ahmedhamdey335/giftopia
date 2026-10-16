<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('base_item_id')->constrained('base_items')->restrictOnDelete();
            $table->json('configuration');
            $table->json('price_breakdown');
            $table->unsignedInteger('total_price')->comment('Minor units (piasters)');
            $table->timestamp('ready_at');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('expires_at');
            $table->index('ready_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};

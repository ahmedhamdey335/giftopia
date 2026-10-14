<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('techniques', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('price_delta')->default(0)->comment('Minor units (piasters)');
            $table->unsignedSmallInteger('labor_minutes')->default(0);
            $table->boolean('requires_accent')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('techniques');
    }
};
